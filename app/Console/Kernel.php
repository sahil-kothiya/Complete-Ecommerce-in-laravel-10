<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\SeedBulkProducts::class,
        \App\Console\Commands\ManageHomepageCache::class,
        \App\Console\Commands\SyncSequences::class,
        \App\Console\Commands\SequencesInfo::class,
        \App\Console\Commands\IndexProducts::class,
        \App\Console\Commands\ReindexProducts::class,
        \App\Console\Commands\MinifyAssets::class,
        \App\Console\Commands\OptimizeImages::class,
        \App\Console\Commands\CacheWarmupCommand::class,
        \App\Console\Commands\ManageProductIndexes::class,
        \App\Console\Commands\CheckIndexHealth::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('discounts:sync-status')->everyMinute()->withoutOverlapping();
        $schedule->command('cache:prune-stale-tags')->daily();
        $schedule->command('ratings:cache')->dailyAt('02:00');
        
        // Filter index maintenance (ROBUST SYSTEM)
        // Full rebuild daily at 2 AM (low traffic time)
        $schedule->command('indexes:manage build --force')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground();
        
        // Incremental sync every 10 minutes (keeps indexes fresh)
        $schedule->job(new \App\Jobs\IncrementalIndexSyncJob(15))
                 ->everyTenMinutes()
                 ->withoutOverlapping();
        
        // Health check and auto-recovery every hour
        $schedule->command('indexes:health --rebuild')
                 ->hourly()
                 ->withoutOverlapping();
        
        // Clean up temporary Redis keys daily
        $schedule->command('indexes:manage clean')
                 ->daily()
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
