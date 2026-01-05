<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Vehicle;
use App\Jobs\SendReminderNotification;
use App\Services\FCMNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReminderController extends Controller
{
    protected $fcmService;

    public function __construct(FCMNotificationService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Get user's reminders
     * Query params: type (upcoming|past|all)
     */
    public function index(Request $request)
    {
        $type = $request->query('type', 'all');
        $user = $request->user();

        $query = Reminder::where('user_id', $user->id)
            ->with(['vehicle'])
            ->orderBy('reminder_date', 'asc');

        switch ($type) {
            case 'upcoming':
                $query->where('is_notified', false)
                    ->where('reminder_date', '>', now());
                break;
            case 'past':
                $query->where(function ($q) {
                    $q->where('is_notified', true)
                        ->orWhere('reminder_date', '<=', now());
                });
                break;
            // 'all' - no additional filtering
        }

        $reminders = $query->get();

        return response()->json([
            'success' => true,
            'data' => $reminders,
        ]);
    }

    /**
     * Store a new reminder
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'title' => 'required|string|max:255',
            'message' => 'nullable|string',
            'reminder_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $reminderDate = Carbon::parse($value);
                    $now = now();
                    
                    // Allow current date if time is in the future, or any future date
                    if ($reminderDate->lte($now)) {
                        $fail('The reminder date and time must be in the future. You can set a reminder for today, but the time must be later than the current time.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Verify that the vehicle belongs to the user and is verified
        $vehicle = Vehicle::where('id', $request->vehicle_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found or does not belong to you',
            ], 404);
        }

        if ($vehicle->verification_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only verified vehicles can be used for reminders',
            ], 422);
        }

        // Parse reminder date
        // The date string from Android is in format "yyyy-MM-dd HH:mm:ss" (local device time)
        // We need to interpret it as being in the application timezone
        $appTimezone = config('app.timezone', 'UTC');
        
        // Parse the date string and interpret it as being in the app timezone
        // Using createFromFormat ensures we control the timezone interpretation
        try {
            $reminderDate = Carbon::createFromFormat('Y-m-d H:i:s', $request->reminder_date, $appTimezone);
        } catch (\Exception $e) {
            // Fallback to parse if format doesn't match exactly
            $reminderDate = Carbon::parse($request->reminder_date, $appTimezone);
        }
        
        $now = Carbon::now($appTimezone);
        
        // Debug logging
        \Log::debug("Reminder date parsing", [
            'input_date' => $request->reminder_date,
            'parsed_date' => $reminderDate->toDateTimeString(),
            'parsed_tz' => $reminderDate->timezone->getName(),
            'current_time' => $now->toDateTimeString(),
            'current_tz' => $now->timezone->getName(),
            'is_future' => $reminderDate->gt($now),
            'diff_seconds_raw' => $reminderDate->timestamp - $now->timestamp,
        ]);
        
        // Ensure reminder date is in the future (allows current date with future time)
        if ($reminderDate->lte($now)) {
            \Log::warning("Reminder date validation failed", [
                'reminder_date' => $reminderDate->toDateTimeString(),
                'reminder_date_tz' => $reminderDate->timezone->getName(),
                'current_time' => $now->toDateTimeString(),
                'current_time_tz' => $now->timezone->getName(),
                'diff_seconds' => $reminderDate->timestamp - $now->timestamp,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'The reminder date and time must be in the future. You can set a reminder for today, but the time must be later than the current time.',
            ], 422);
        }
        
        $reminder = Reminder::create([
            'user_id' => $user->id,
            'vehicle_id' => $request->vehicle_id,
            'title' => $request->title,
            'message' => $request->message,
            'reminder_date' => $reminderDate,
        ]);

        $reminder->load('vehicle');

        // Schedule FCM notification job to run at exact reminder time
        // Calculate delay using timestamp difference (more reliable than diffInSeconds)
        $delaySeconds = $reminderDate->timestamp - $now->timestamp;
        
        // If negative, it means reminderDate is in the past (shouldn't happen after validation, but double-check)
        if ($delaySeconds <= 0) {
            \Log::error("Reminder date calculation error - delay is not positive", [
                'reminder_id' => $reminder->id,
                'reminder_date' => $reminderDate->toDateTimeString(),
                'reminder_date_tz' => $reminderDate->timezone->getName(),
                'reminder_timestamp' => $reminderDate->timestamp,
                'current_time' => $now->toDateTimeString(),
                'current_time_tz' => $now->timezone->getName(),
                'current_timestamp' => $now->timestamp,
                'delay_seconds' => $delaySeconds,
            ]);
            // Still create the reminder, but don't schedule notification
            return response()->json([
                'success' => true,
                'message' => 'Reminder created successfully, but notification scheduling failed. Please check server logs.',
                'data' => $reminder,
            ], 201);
        }
        
        // Check queue connection - if 'sync', jobs execute immediately (not good for delays)
        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            \Log::error("Queue connection is set to 'sync' - delayed jobs will execute immediately!", [
                'reminder_id' => $reminder->id,
                'reminder_date' => $reminderDate->toDateTimeString(),
                'queue_connection' => $queueConnection,
                'warning' => 'Set QUEUE_CONNECTION=database in .env and run queue worker',
            ]);
        }
        
        // Schedule the job (delaySeconds is guaranteed to be > 0 at this point)
        \Log::info("Scheduling FCM notification for reminder", [
            'reminder_id' => $reminder->id,
            'reminder_date' => $reminderDate->toDateTimeString(),
            'reminder_date_tz' => $reminderDate->timezone->getName(),
            'current_time' => $now->toDateTimeString(),
            'current_time_tz' => $now->timezone->getName(),
            'delay_seconds' => $delaySeconds,
            'delay_minutes' => round($delaySeconds / 60, 2),
            'delay_hours' => round($delaySeconds / 3600, 2),
            'scheduled_for' => $reminderDate->diffForHumans(),
            'queue_connection' => $queueConnection,
            'is_today' => $reminderDate->isToday(),
        ]);
        
        // Dispatch with delay - using Carbon instance directly
        // The delay() method accepts a Carbon instance or DateTime
        SendReminderNotification::dispatch($reminder)
            ->delay($reminderDate);
        
        \Log::info("FCM notification job dispatched successfully", [
            'reminder_id' => $reminder->id,
            'job_will_execute_at' => $reminderDate->toDateTimeString(),
            'job_will_execute_at_tz' => $reminderDate->timezone->getName(),
            'important_note' => 'Make sure queue worker is running: php artisan queue:work',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reminder created successfully',
            'data' => $reminder,
        ], 201);
    }

    /**
     * Get a specific reminder
     */
    public function show(Request $request, $id)
    {
        $reminder = Reminder::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['vehicle'])
            ->first();

        if (!$reminder) {
            return response()->json([
                'success' => false,
                'message' => 'Reminder not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $reminder,
        ]);
    }

    /**
     * Update a reminder
     */
    public function update(Request $request, $id)
    {
        $reminder = Reminder::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$reminder) {
            return response()->json([
                'success' => false,
                'message' => 'Reminder not found',
            ], 404);
        }

        // If already notified, only allow updating message
        if ($reminder->is_notified) {
            $validator = Validator::make($request->all(), [
                'message' => 'nullable|string',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'vehicle_id' => 'sometimes|exists:vehicles,id',
                'title' => 'sometimes|string|max:255',
                'message' => 'nullable|string',
                'reminder_date' => [
                    'sometimes',
                    'date',
                    function ($attribute, $value, $fail) {
                        if ($value) {
                            $reminderDate = Carbon::parse($value);
                            $now = now();
                            
                            // Allow current date if time is in the future, or any future date
                            if ($reminderDate->lte($now)) {
                                $fail('The reminder date and time must be in the future. You can set a reminder for today, but the time must be later than the current time.');
                            }
                        }
                    },
                ],
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // If vehicle_id is being updated, verify it belongs to user and is verified
        if ($request->has('vehicle_id') && $request->vehicle_id != $reminder->vehicle_id) {
            $vehicle = Vehicle::where('id', $request->vehicle_id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found or does not belong to you',
                ], 404);
            }

            if ($vehicle->verification_status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only verified vehicles can be used for reminders',
                ], 422);
            }
        }

        $updateData = [];
        if ($request->has('vehicle_id')) $updateData['vehicle_id'] = $request->vehicle_id;
        if ($request->has('title')) $updateData['title'] = $request->title;
        if ($request->has('message')) $updateData['message'] = $request->message;
        
        $reminderDateChanged = false;
        if ($request->has('reminder_date')) {
            $newReminderDate = Carbon::parse($request->reminder_date);
            $now = now();
            
            // Ensure new reminder date is in the future (allows current date with future time)
            if ($newReminderDate->lte($now)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The reminder date and time must be in the future. You can set a reminder for today, but the time must be later than the current time.',
                ], 422);
            }
            
            $updateData['reminder_date'] = $newReminderDate;
            $reminderDateChanged = $reminder->reminder_date->ne($newReminderDate);
        }

        $reminder->update($updateData);
        $reminder->load('vehicle');

        // If reminder date changed and is in the future, reschedule FCM notification
        if ($reminderDateChanged && isset($newReminderDate) && !$reminder->is_notified) {
            // Calculate delay in seconds from now
            $delaySeconds = $newReminderDate->diffInSeconds(now());
            
            if ($delaySeconds > 0) {
                // Cancel any existing jobs for this reminder (if using unique jobs)
                // Note: Laravel doesn't have built-in job cancellation, but we can check in the job itself
                
                // Schedule new FCM notification at the updated time
                SendReminderNotification::dispatch($reminder)
                    ->delay($newReminderDate);
                
                \Log::info("Rescheduled FCM notification for updated reminder", [
                    'reminder_id' => $reminder->id,
                    'new_reminder_date' => $newReminderDate->toDateTimeString(),
                    'current_time' => now()->toDateTimeString(),
                    'delay_seconds' => $delaySeconds,
                    'delay_minutes' => round($delaySeconds / 60, 2),
                    'is_today' => $newReminderDate->isToday(),
                ]);
            } else {
                \Log::warning("Updated reminder date is not in the future, skipping job dispatch", [
                    'reminder_id' => $reminder->id,
                    'new_reminder_date' => $newReminderDate->toDateTimeString(),
                    'current_time' => now()->toDateTimeString(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Reminder updated successfully',
            'data' => $reminder,
        ]);
    }

    /**
     * Delete a reminder
     */
    public function destroy(Request $request, $id)
    {
        $reminder = Reminder::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$reminder) {
            return response()->json([
                'success' => false,
                'message' => 'Reminder not found',
            ], 404);
        }

        // Note: Laravel doesn't have built-in job cancellation, but the job will check
        // if reminder is already notified or doesn't exist, so it's safe to delete
        // The job will simply skip execution if reminder is deleted
        
        $reminder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reminder deleted successfully',
        ]);
    }

    /**
     * Send reminder notification (called by scheduled job)
     * This endpoint can be called by a cron job to send notifications for due reminders
     */
    public function sendDueReminders()
    {
        $dueReminders = Reminder::where('is_notified', false)
            ->where('reminder_date', '<=', now())
            ->with(['user', 'vehicle'])
            ->get();

        $sentCount = 0;

        foreach ($dueReminders as $reminder) {
            $user = $reminder->user;
            if (!$user || !$user->fcm_token) {
                continue;
            }

            $vehicle = $reminder->vehicle;
            $vehicleInfo = $vehicle ? $vehicle->registration_number : 'Unknown Vehicle';

            $title = $reminder->title;
            $body = $reminder->message ?: "Reminder for {$vehicleInfo}";

            try {
                $this->fcmService->sendToUser(
                    $user,
                    $title,
                    $body,
                    [
                        'type' => 'reminder',
                        'reminder_id' => (string) $reminder->id,
                        'vehicle_id' => (string) $reminder->vehicle_id,
                    ]
                );

                $reminder->update([
                    'is_notified' => true,
                    'notified_at' => now(),
                ]);

                $sentCount++;
            } catch (\Exception $e) {
                \Log::error("Failed to send reminder notification: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Sent {$sentCount} reminder notifications",
        ]);
    }
}

