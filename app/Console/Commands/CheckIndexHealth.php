<?php

namespace App\Console\Commands;

use App\Services\IndexHealthService;
use Illuminate\Console\Command;

/**
 * Check Index Health Command
 * 
 * Quick diagnostic tool to check if filter indexes are healthy
 */
class CheckIndexHealth extends Command
{
    protected $signature = 'indexes:health 
                            {--rebuild : Trigger rebuild if unhealthy}
                            {--json : Output as JSON}';

    protected $description = 'Check the health of product filter indexes';

    private IndexHealthService $healthService;

    public function __construct(IndexHealthService $healthService)
    {
        parent::__construct();
        $this->healthService = $healthService;
    }

    public function handle(): int
    {
        $this->info('🔍 Checking filter index health...');
        $this->newLine();
        
        $report = $this->healthService->getHealthReport();
        
        // JSON output
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return $report['healthy'] ? 0 : 1;
        }
        
        // Human-readable output
        $this->displayHealthReport($report);
        
        // Trigger rebuild if requested
        if (!$report['healthy'] && $this->option('rebuild')) {
            $this->newLine();
            $this->warn('Indexes are unhealthy. Triggering rebuild...');
            
            if ($this->healthService->triggerBackgroundRebuild()) {
                $this->info('✅ Background rebuild triggered successfully');
                $this->info('Check logs for progress: tail -f storage/logs/laravel.log');
            } else {
                $this->error('❌ Failed to trigger rebuild');
                return 1;
            }
        }
        
        return $report['healthy'] ? 0 : 1;
    }
    
    private function displayHealthReport(array $report): void
    {
        // Overall status
        $statusIcon = $report['healthy'] ? '✅' : '❌';
        $statusText = $report['healthy'] ? 'HEALTHY' : 'UNHEALTHY';
        $this->line("{$statusIcon} Overall Status: <fg=white;bg=" . ($report['healthy'] ? 'green' : 'red') . ">{$statusText}</>");
        $this->newLine();
        
        // Redis availability
        $redisIcon = $report['redis_available'] ? '✅' : '❌';
        $this->line("{$redisIcon} Redis Available: " . ($report['redis_available'] ? 'Yes' : 'No'));
        
        // Index counts
        $this->line("📊 Total Indexes: {$report['total_indexes']}");
        $this->newLine();
        
        // Critical indexes
        if (!empty($report['critical_indexes_status'])) {
            $this->info('🔑 Critical Indexes:');
            
            $tableData = [];
            foreach ($report['critical_indexes_status'] as $key => $status) {
                $healthIcon = $status['healthy'] ? '✅' : '❌';
                $tableData[] = [
                    $healthIcon,
                    $key,
                    $status['exists'] ? 'Yes' : 'No',
                    number_format($status['count'])
                ];
            }
            
            $this->table(['', 'Index Key', 'Exists', 'Count'], $tableData);
            $this->newLine();
        }
        
        // Missing indexes
        if (!empty($report['missing_indexes'])) {
            $this->warn('⚠️  Missing Indexes (' . count($report['missing_indexes']) . '):');
            foreach ($report['missing_indexes'] as $missing) {
                $this->line("  - {$missing}");
            }
            $this->newLine();
        }
        
        // Stale indexes
        if (!empty($report['stale_indexes'])) {
            $this->warn('⏰ Stale Indexes (' . count($report['stale_indexes']) . '):');
            foreach (array_slice($report['stale_indexes'], 0, 5) as $stale) {
                $this->line("  - {$stale}");
            }
            if (count($report['stale_indexes']) > 5) {
                $this->line('  ... and ' . (count($report['stale_indexes']) - 5) . ' more');
            }
            $this->newLine();
        }
        
        // Rebuild status
        if ($report['rebuild_in_progress']) {
            $this->info('🔄 Rebuild Status: In Progress');
        } elseif ($report['rebuild_recommended']) {
            $this->warn('🔄 Rebuild Status: Recommended');
            $this->line("   Run: <fg=cyan>php artisan indexes:health --rebuild</>");
        } else {
            $this->info('🔄 Rebuild Status: Not Needed');
        }
        
        // Last rebuild time
        if ($report['last_rebuild_time']) {
            $this->line("⏱️  Last Rebuild: {$report['last_rebuild_time']}");
        }
        
        // Recommendations
        if (!$report['healthy']) {
            $this->newLine();
            $this->warn('📋 Recommendations:');
            
            if (!$report['redis_available']) {
                $this->line('  1. Check Redis service is running');
                $this->line('  2. Verify Redis connection in .env');
            }
            
            if ($report['rebuild_recommended']) {
                $this->line('  3. Run: php artisan indexes:health --rebuild');
                $this->line('  4. Or manually: php -d memory_limit=2G artisan indexes:manage build --force');
            }
        }
    }
}
