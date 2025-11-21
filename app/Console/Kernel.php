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
