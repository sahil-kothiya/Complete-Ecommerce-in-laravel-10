<?php

namespace App\Console\Commands;

use App\Http\Controllers\FrontendController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WarmupHomepageCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'homepage:warmup-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up Redis cache for homepage to ensure instant first load';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔥 Warming up homepage cache...');
        $startTime = microtime(true);

        try {
            // Create a mock request to the homepage
            $app = app();
            $controller = $app->make(FrontendController::class);

            $this->info('📊 Loading homepage data...');

            // Trigger the home method which will populate all caches
            $response = $controller->home();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->newLine();
            $this->info("✅ Homepage cache warmup complete!");
            $this->info("   Duration: {$duration}ms");
            $this->newLine();
            $this->info("💡 Next homepage load will be served from Redis cache in < 15ms");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to warm up cache: ' . $e->getMessage());
            Log::error('Homepage cache warmup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
