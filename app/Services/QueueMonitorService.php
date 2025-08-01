<?php

namespace App\Services;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\QueueAlertNotification;

class QueueMonitorService
{
    private array $config;
    private array $queueStats = [];

    public function __construct()
    {
        $this->config = config('product_queues');
    }

    /**
     * Get comprehensive queue status.
     */
    public function getQueueStatus(): array
    {
        $status = [
            'timestamp' => now()->toISOString(),
            'queues' => [],
            'overall_health' => 'healthy',
            'alerts' => [],
        ];

        foreach (['search', 'cache'] as $queueName) {
            $queueStats = $this->getQueueStats($queueName);
            $status['queues'][$queueName] = $queueStats;

            // Check for alerts
            $alerts = $this->checkQueueAlerts($queueName, $queueStats);
            if (!empty($alerts)) {
                $status['alerts'] = array_merge($status['alerts'], $alerts);
                $status['overall_health'] = 'warning';
            }
        }

        // Check failed jobs
        $failedJobs = $this->getFailedJobsCount();
        $status['failed_jobs'] = $failedJobs;

        if ($failedJobs > $this->config['monitoring']['alert_thresholds']['failed_jobs_warning']) {
            $status['alerts'][] = [
                'type' => 'failed_jobs',
                'severity' => 'warning',
                'message' => "High number of failed jobs: {$failedJobs}",
            ];
            $status['overall_health'] = 'warning';
        }

        return $status;
    }

    /**
     * Get statistics for a specific queue.
     */
    public function getQueueStats(string $queueName): array
    {
        try {
            $size = Queue::size($queueName);

            // Get additional Redis-specific stats if using Redis
            $redisStats = $this->getRedisQueueStats($queueName);

            return [
                'name' => $queueName,
                'size' => $size,
                'status' => $this->determineQueueStatus($queueName, $size),
                'redis_stats' => $redisStats,
                'performance' => $this->getQueuePerformanceStats($queueName),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get stats for queue {$queueName}: " . $e->getMessage());
            return [
                'name' => $queueName,
                'size' => -1,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Redis-specific queue statistics.
     */
    private function getRedisQueueStats(string $queueName): array
    {
        try {
            $connection = config('queue.connections.redis.connection', 'default');
            $redis = Redis::connection($connection);

            $queueKey = "queues:{$queueName}";
            $delayedKey = "queues:{$queueName}:delayed";
            $reservedKey = "queues:{$queueName}:reserved";

            return [
                'waiting' => $redis->llen($queueKey),
                'delayed' => $redis->zcard($delayedKey),
                'reserved' => $redis->zcard($reservedKey),
                'total' => $redis->llen($queueKey) + $redis->zcard($delayedKey) + $redis->zcard($reservedKey),
            ];
        } catch (\Exception $e) {
            Log::warning("Failed to get Redis stats for queue {$queueName}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get performance statistics for a queue.
     */
    private function getQueuePerformanceStats(string $queueName): array
    {
        // This would require implementing job tracking
        // For now, return basic performance indicators
        return [
            'avg_processing_time' => $this->getAverageProcessingTime($queueName),
            'jobs_per_minute' => $this->getJobsPerMinute($queueName),
            'success_rate' => $this->getSuccessRate($queueName),
        ];
    }

    /**
     * Determine queue status based on size and configuration.
     */
    private function determineQueueStatus(string $queueName, int $size): string
    {
        $thresholds = $this->config['monitoring']['alert_thresholds'];

        if ($size >= $thresholds['queue_size_critical']) {
            return 'critical';
        } elseif ($size >= $thresholds['queue_size_warning']) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Check for queue alerts.
     */
    private function checkQueueAlerts(string $queueName, array $queueStats): array
    {
        $alerts = [];
        $thresholds = $this->config['monitoring']['alert_thresholds'];

        // Check queue size
        if ($queueStats['size'] >= $thresholds['queue_size_critical']) {
            $alerts[] = [
                'type' => 'queue_size',
                'queue' => $queueName,
                'severity' => 'critical',
                'message' => "Queue {$queueName} has {$queueStats['size']} jobs (critical threshold: {$thresholds['queue_size_critical']})",
            ];
        } elseif ($queueStats['size'] >= $thresholds['queue_size_warning']) {
            $alerts[] = [
                'type' => 'queue_size',
                'queue' => $queueName,
                'severity' => 'warning',
                'message' => "Queue {$queueName} has {$queueStats['size']} jobs (warning threshold: {$thresholds['queue_size_warning']})",
            ];
        }

        return $alerts;
    }

    /**
     * Get failed jobs count.
     */
    public function getFailedJobsCount(): int
    {
        try {
            return Queue::size('failed');
        } catch (\Exception $e) {
            Log::error("Failed to get failed jobs count: " . $e->getMessage());
            return -1;
        }
    }

    /**
     * Monitor queues and send alerts if necessary.
     */
    public function monitorAndAlert(): void
    {
        if (!$this->config['monitoring']['enabled']) {
            return;
        }

        $status = $this->getQueueStatus();

        if ($status['overall_health'] !== 'healthy' && !empty($status['alerts'])) {
            $this->sendAlerts($status['alerts']);
        }

        // Log performance metrics
        if ($this->config['monitoring']['log_performance']) {
            $this->logPerformanceMetrics($status);
        }
    }

    /**
     * Send alerts for queue issues.
     */
    private function sendAlerts(array $alerts): void
    {
        foreach ($alerts as $alert) {
            Log::warning("Queue Alert: {$alert['message']}", $alert);

            // Send notifications if configured
            $this->sendNotification($alert);
        }
    }

    /**
     * Send notification for alert.
     */
    private function sendNotification(array $alert): void
    {
        try {
            $notificationConfig = $this->config['monitoring']['notifications'];

            if (!empty($notificationConfig['email_alerts'])) {
                // Send email notification
                // Implementation depends on your notification setup
                Log::info("Would send email alert to: " . $notificationConfig['email_alerts']);
            }

            if (!empty($notificationConfig['slack_webhook'])) {
                // Send Slack notification
                // Implementation depends on your Slack integration
                Log::info("Would send Slack alert to webhook");
            }
        } catch (\Exception $e) {
            Log::error("Failed to send notification: " . $e->getMessage());
        }
    }

    /**
     * Log performance metrics.
     */
    private function logPerformanceMetrics(array $status): void
    {
        $metrics = [
            'timestamp' => $status['timestamp'],
            'overall_health' => $status['overall_health'],
            'total_queued_jobs' => array_sum(array_column($status['queues'], 'size')),
            'failed_jobs' => $status['failed_jobs'],
            'alert_count' => count($status['alerts']),
        ];

        foreach ($status['queues'] as $queue) {
            $metrics["queue_{$queue['name']}_size"] = $queue['size'];
            $metrics["queue_{$queue['name']}_status"] = $queue['status'];
        }

        Log::info("Queue Performance Metrics", $metrics);
    }

    /**
     * Get average processing time for a queue (placeholder).
     */
    private function getAverageProcessingTime(string $queueName): ?float
    {
        // This would require implementing job timing tracking
        // Return null for now, implement based on your tracking needs
        return null;
    }

    /**
     * Get jobs per minute for a queue (placeholder).
     */
    private function getJobsPerMinute(string $queueName): ?float
    {
        // This would require implementing job rate tracking
        // Return null for now, implement based on your tracking needs
        return null;
    }

    /**
     * Get success rate for a queue (placeholder).
     */
    private function getSuccessRate(string $queueName): ?float
    {
        // This would require implementing job success/failure tracking
        // Return null for now, implement based on your tracking needs
        return null;
    }

    /**
     * Clear stale jobs from queues.
     */
    public function clearStaleJobs(): array
    {
        $results = [];

        foreach (['search', 'cache'] as $queueName) {
            try {
                // Clear reserved jobs that are older than timeout
                $cleared = $this->clearStaleReservedJobs($queueName);
                $results[$queueName] = [
                    'status' => 'success',
                    'cleared_jobs' => $cleared,
                ];
            } catch (\Exception $e) {
                $results[$queueName] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Clear stale reserved jobs for a queue.
     */
    private function clearStaleReservedJobs(string $queueName): int
    {
        try {
            $connection = config('queue.connections.redis.connection', 'default');
            $redis = Redis::connection($connection);

            $reservedKey = "queues:{$queueName}:reserved";
            $timeout = $this->config['queues'][$queueName]['timeout'] ?? 60;
            $cutoffTime = now()->subSeconds($timeout)->getTimestamp();

            // Remove jobs reserved before cutoff time
            $removed = $redis->zremrangebyscore($reservedKey, 0, $cutoffTime);

            if ($removed > 0) {
                Log::info("Cleared {$removed} stale reserved jobs from queue {$queueName}");
            }

            return $removed;
        } catch (\Exception $e) {
            Log::error("Failed to clear stale jobs from queue {$queueName}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get queue health summary.
     */
    public function getHealthSummary(): array
    {
        $status = $this->getQueueStatus();

        return [
            'overall_health' => $status['overall_health'],
            'total_queued' => array_sum(array_column($status['queues'], 'size')),
            'failed_jobs' => $status['failed_jobs'],
            'active_alerts' => count($status['alerts']),
            'last_check' => $status['timestamp'],
        ];
    }
}
