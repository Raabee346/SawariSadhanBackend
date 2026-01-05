<?php

namespace App\Jobs;

use App\Models\Reminder;
use App\Services\FCMNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReminderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $reminder;

    /**
     * Create a new job instance.
     */
    public function __construct(Reminder $reminder)
    {
        $this->reminder = $reminder;
    }

    /**
     * Execute the job.
     */
    public function handle(FCMNotificationService $fcmService): void
    {
        Log::info("SendReminderNotification job started", [
            'reminder_id' => $this->reminder->id ?? 'unknown',
            'current_time' => now()->toDateTimeString(),
        ]);
        
        // Reload reminder to ensure we have latest data
        // If reminder was deleted, refresh() will throw ModelNotFoundException
        try {
            $this->reminder->refresh();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::info("Reminder was deleted, skipping notification", [
                'reminder_id' => $this->reminder->id ?? 'unknown',
            ]);
            return;
        }
        
        $this->reminder->load(['user', 'vehicle']);

        // Check if reminder was already notified
        if ($this->reminder->is_notified) {
            Log::info("Reminder already notified, skipping", [
                'reminder_id' => $this->reminder->id,
            ]);
            return;
        }
        
        // Double-check that reminder date has actually arrived
        $reminderDate = \Carbon\Carbon::parse($this->reminder->reminder_date);
        if ($reminderDate->isFuture()) {
            Log::warning("Reminder date is still in the future, job executed too early", [
                'reminder_id' => $this->reminder->id,
                'reminder_date' => $reminderDate->toDateTimeString(),
                'current_time' => now()->toDateTimeString(),
                'seconds_until_reminder' => $reminderDate->diffInSeconds(now()),
            ]);
            // Don't send notification yet - job will be retried or rescheduled
            return;
        }

        $user = $this->reminder->user;
        if (!$user || !$user->fcm_token) {
            Log::warning("No user or FCM token for reminder", [
                'reminder_id' => $this->reminder->id,
                'has_user' => $user !== null,
                'has_fcm_token' => $user && $user->fcm_token !== null,
            ]);
            return;
        }

        $vehicle = $this->reminder->vehicle;
        $vehicleInfo = $vehicle ? $vehicle->registration_number : 'Unknown Vehicle';

        $title = $this->reminder->title;
        $body = $this->reminder->message ?: "Reminder for {$vehicleInfo}";

        try {
            $fcmService->sendToUser(
                $user,
                $title,
                $body,
                [
                    'type' => 'reminder',
                    'reminder_id' => (string) $this->reminder->id,
                    'vehicle_id' => (string) $this->reminder->vehicle_id,
                ]
            );

            // Mark reminder as notified
            $this->reminder->update([
                'is_notified' => true,
                'notified_at' => now(),
            ]);

            Log::info("Reminder FCM notification sent successfully", [
                'reminder_id' => $this->reminder->id,
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send reminder FCM notification", [
                'reminder_id' => $this->reminder->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw exception - allow job to complete even if FCM fails
        }
    }
}

