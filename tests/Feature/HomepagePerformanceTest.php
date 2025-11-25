<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HomepagePerformanceTest extends TestCase
{
    /**
     * Test homepage loads within performance threshold
     *
     * @return void
     */
    public function test_homepage_loads_within_performance_threshold()
    {
        // Clear cache to test first load
        Cache::flush();

        $startTime = microtime(true);
        $response = $this->get('/');
        $duration = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);

        // First load should be < 500ms (without cache)
        $this->assertLessThan(500, $duration,
            "First load took {$duration}ms, should be < 500ms"
        );

        echo "\n✅ First load (no cache): " . round($duration, 2) . "ms\n";
    }

    /**
     * Test homepage loads from cache very quickly
     *
     * @return void
     */
    public function test_homepage_cached_load_is_fast()
    {
        // Warm up cache
        $this->get('/');

        // Test cached load
        $startTime = microtime(true);
        $response = $this->get('/');
        $duration = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);

        // Cached load should be < 50ms
        $this->assertLessThan(50, $duration,
            "Cached load took {$duration}ms, should be < 50ms"
        );

        echo "\n✅ Cached load: " . round($duration, 2) . "ms\n";
    }

    /**
     * Test database queries are optimized
     *
     * @return void
     */
    public function test_homepage_uses_minimal_queries()
    {
        Cache::flush();

        DB::enableQueryLog();
        $this->get('/');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $queryCount = count($queries);

        // Should use less than 20 queries on first load
        $this->assertLessThan(20, $queryCount,
            "Homepage used {$queryCount} queries, should be < 20"
        );

        echo "\n✅ Database queries: {$queryCount}\n";
    }
}
