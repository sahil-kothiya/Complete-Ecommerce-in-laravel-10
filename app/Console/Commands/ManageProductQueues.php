<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;

class ManageProductQueues extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:manage-product-queues 
                          {action : start|stop|status|restart}
                          {--workers=3 : Number of workers to start}';

    /**
     * The console command description.
     */
    protected $description = 'Manage product-related queue workers (search and cache)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $workers = $this->option('workers');

        return match ($action) {
            'start' => $this->startWorkers($workers),
            'stop' => $this->stopWorkers(),
            'status' => $this->checkStatus(),
            'restart' => $this->restartWorkers($workers),
            default => $this->showUsage()
        };
    }

    /**
     * Start queue workers.
     */
    private function startWorkers(int $workers): int
    {
        $this->info("Starting product queue workers...");

        try {
            // Start search queue workers (for Elasticsearch operations)
            for ($i = 1; $i <= $workers; $i++) {
                $this->info("Starting search worker {$i}...");
                Artisan::call('queue:work', [
                    '--queue' => 'search',
                    '--tries' => 3,
                    '--timeout' => 60,
                    '--sleep' => 3,
                    '--max-jobs' => 100,
                    '--max-time' => 3600, // 1 hour
                    '--daemon' => true,
                ]);
            }

            // Start cache queue workers (for cache operations)
            for ($i = 1; $i <= $workers; $i++) {
                $this->info("Starting cache worker {$i}...");
                Artisan::call('queue:work', [
                    '--queue' => 'cache',
                    '--tries' => 2,
                    '--timeout' => 120,
                    '--sleep' => 1,
                    '--max-jobs' => 200,
                    '--max-time' => 3600, // 1 hour
                    '--daemon' => true,
                ]);
            }

            $this->info("✅ Started {$workers} workers for each queue (search, cache)");
            $this->info("💡 Use 'queue:manage-product-queues status' to check worker status");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to start workers: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Stop queue workers.
     */
    private function stopWorkers(): int
    {
        $this->info("Stopping product queue workers...");

        try {
            // Send stop signal to workers
            Artisan::call('queue:restart');

            $this->info("✅ Stop signal sent to all queue workers");
            $this->info("⏳ Workers will stop after completing current jobs");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to stop workers: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Check queue status.
     */
    private function checkStatus(): int
    {
        $this->info("📊 Queue Status Report");
        $this->line(str_repeat('=', 50));

        try {
            // Check search queue
            $searchSize = Queue::size('search');
            $this->info("🔍 Search Queue: {$searchSize} jobs pending");

            // Check cache queue
            $cacheSize = Queue::size('cache');
            $this->info("💾 Cache Queue: {$cacheSize} jobs pending");

            // Check failed jobs
            $failedJobs = Queue::size('failed');
            if ($failedJobs > 0) {
                $this->warn("⚠️  Failed Jobs: {$failedJobs}");
                $this->info("💡 Run 'queue:failed' to see details or 'queue:retry all' to retry");
            } else {
                $this->info("✅ No failed jobs");
            }

            // Show recent job statistics if available
            $this->showRecentJobStats();

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to check status: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Restart queue workers.
     */
    private function restartWorkers(int $workers): int
    {
        $this->info("🔄 Restarting product queue workers...");

        // Stop existing workers
        $this->stopWorkers();

        // Wait a bit for graceful shutdown
        sleep(5);

        // Start new workers
        return $this->startWorkers($workers);
    }

    /**
     * Show command usage.
     */
    private function showUsage(): int
    {
        $this->error("❌ Invalid action specified");
        $this->line("");
        $this->info("Usage:");
        $this->line("  queue:manage-product-queues start [--workers=3]  Start queue workers");
        $this->line("  queue:manage-product-queues stop                 Stop queue workers");
        $this->line("  queue:manage-product-queues status               Check queue status");
        $this->line("  queue:manage-product-queues restart [--workers=3] Restart workers");

        return 1;
    }

    /**
     * Show recent job statistics.
     */
    private function showRecentJobStats(): void
    {
        $this->line("");
        $this->info("📈 Recent Job Performance:");

        try {
            // This would require implementing job tracking in your application
            // For now, we'll show basic Redis queue info if available
            $this->line("   Search Queue Jobs: Processing Elasticsearch operations");
            $this->line("   Cache Queue Jobs: Processing cache invalidation");
            $this->line("   💡 For detailed metrics, implement job tracking in your app");
        } catch (\Exception $e) {
            $this->line("   Unable to fetch job statistics");
        }
    }

    /**
     * Monitor queue in real-time.
     */
    public function monitorQueues(): void
    {
        $this->info("🔍 Real-time Queue Monitor (Press Ctrl+C to stop)");
        $this->line(str_repeat('=', 60));

        while (true) {
            // Clear screen
            system('clear');

            $this->info("Queue Monitor - " . now()->format('Y-m-d H:i:s'));
            $this->line(str_repeat('-', 40));

            // Show queue sizes
            $searchSize = Queue::size('search');
            $cacheSize = Queue::size('cache');
            $failedSize = Queue::size('failed');

            $this->line("Search Queue: {$searchSize} jobs");
            $this->line("Cache Queue:  {$cacheSize} jobs");
            $this->line("Failed Jobs:  {$failedSize} jobs");

            if ($searchSize > 10) {
                $this->warn("⚠️  Search queue backlog detected!");
            }

            if ($cacheSize > 20) {
                $this->warn("⚠️  Cache queue backlog detected!");
            }

            sleep(2);
        }
    }
}
