<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckQueueStatus extends Command
{
    protected $signature = 'queue:check-status';
    protected $description = 'Check if queue worker is needed and show pending jobs';

    public function handle()
    {
        $queueConnection = config('queue.default');
        
        $this->info("Queue Connection: {$queueConnection}");
        
        if ($queueConnection === 'sync') {
            $this->error("⚠️  Queue is set to 'sync' - delayed jobs will execute immediately!");
            $this->info("Set QUEUE_CONNECTION=database in .env file");
            return 1;
        }
        
        // Check for pending jobs
        $pendingJobs = DB::table('jobs')
            ->where('queue', 'default')
            ->where('available_at', '<=', now()->timestamp)
            ->count();
            
        $futureJobs = DB::table('jobs')
            ->where('queue', 'default')
            ->where('available_at', '>', now()->timestamp)
            ->count();
            
        $this->info("Pending jobs (ready to execute): {$pendingJobs}");
        $this->info("Scheduled jobs (future): {$futureJobs}");
        
        if ($pendingJobs > 0) {
            $this->warn("⚠️  You have {$pendingJobs} pending job(s) waiting to be processed!");
            $this->info("Start the queue worker: php artisan queue:work");
        }
        
        if ($futureJobs > 0) {
            $this->info("✓ You have {$futureJobs} job(s) scheduled for the future");
        }
        
        if ($pendingJobs === 0 && $futureJobs === 0) {
            $this->info("✓ No jobs in queue");
        }
        
        return 0;
    }
}

