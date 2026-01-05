<?php

namespace App\Console\Commands;

use App\Http\Controllers\ReminderController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SendDueReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send FCM notifications for due reminders';

    /**
     * Execute the console command.
     * 
     * This is a BACKUP method that runs periodically to catch any reminders
     * that might have been missed by the delayed job system (e.g., if queue worker crashed).
     * 
     * Primary method: Delayed jobs scheduled at exact reminder time
     * Backup method: This cron job (runs less frequently)
     */
    public function handle()
    {
        $this->info('Checking for due reminders (backup check)...');
        $this->comment('Note: Primary method uses delayed jobs for exact timing.');
        $this->comment('This is a backup to catch any missed reminders.');
        
        $controller = app(ReminderController::class);
        $request = Request::create('/api/reminders/send-due', 'POST');
        
        $response = $controller->sendDueReminders();
        $responseData = json_decode($response->getContent(), true);
        
        if ($responseData && $responseData['success']) {
            $sentCount = $responseData['message'] ?? '0';
            $this->info($responseData['message']);
            
            // If we sent any, it means some reminders were missed by the job system
            if (preg_match('/Sent (\d+) reminder/', $sentCount, $matches) && $matches[1] > 0) {
                $this->warn("⚠️  Sent {$matches[1]} reminder(s) via backup method. Check queue worker status!");
            }
        } else {
            $this->error('Failed to send reminder notifications');
        }
        
        return 0;
    }
}

