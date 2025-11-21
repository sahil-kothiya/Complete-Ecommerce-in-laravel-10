<?php

namespace App\Console\Commands;

use App\Jobs\WarmHomepageCacheJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * CacheWarmupCommand
 *
 * Manually trigger homepage cache warmup.
 * Usage: php artisan cache:warmup
 */
class CacheWarmupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warmup
                            {--force : Force warmup even if cache exists}
                            {--async : Run warmup as background job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up homepage cache for instant load times';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!Config::get('redis_cache.enabled.master', false)) {
            $this->error('❌ Redis cache is disabled. Enable it in .env with REDIS_CACHE_ENABLED=true');
            return 1;
        }

        $this->info('🔥 Starting homepage cache warmup...');
        $this->newLine();

        $startTime = microtime(true);
        $async = $this->option('async');

        try {
            if ($async && Config::get('queue.default') !== 'sync') {
                // Dispatch as background job
                WarmHomepageCacheJob::dispatch();

                $this->info('✅ Cache warmup job dispatched to queue');
                $this->info('⏱️  Cache will be ready in ~30-60 seconds');

            } else {
                // Execute synchronously with progress
                if ($async) {
                    $this->warn('⚠️  Queue is set to sync, running warmup synchronously instead');
                }

                $this->line('Warming cache components:');

                $job = new WarmHomepageCacheJob();

                $bar = $this->output->createProgressBar(5);
                $bar->start();

                // Execute the job
                $job->handle();

                $bar->finish();
                $this->newLine(2);

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                $this->info("✅ Cache warmup completed in {$duration}ms");
                $this->info('🚀 Homepage will now load in ~10-20ms!');
            }

            $this->newLine();
            return 0;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Cache warmup failed: ' . $e->getMessage());

            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }
    }
}
