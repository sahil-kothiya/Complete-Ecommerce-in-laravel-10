<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class EnterpriseSkuSeeder extends Seeder
{
    private const CHUNK_SIZE = 500;
    private const MAX_RETRIES = 100;
    private const MEMORY_LIMIT_CHECK = 50;

    private array $generatedSkus = [];
    private int $memoryCheckCounter = 0;
    private int $startFromId = 2404631; // change to 8951426 if resuming from there

    private array $stats = [
        'processed' => 0,
        'generated' => 0,
        'updated' => 0,
        'duplicates_resolved' => 0,
        'errors' => 0,
        'chunks_processed' => 0,
        'memory_peak' => 0,
    ];
    
    public function run(): void
    {
        $this->info("🚀 Starting Enterprise SKU Seeder");

        $start = microtime(true);

        try {
            $this->processAllRecords();
        } catch (Exception $e) {
            $this->error("❌ Seeder stopped: " . $e->getMessage());
            throw $e;
        } finally {
            $this->printStats(microtime(true) - $start);
            $this->cleanup();
        }
    }

    private function processAllRecords(): void
    {
        $this->info("📦 Processing ALL products in chunks of " . self::CHUNK_SIZE . " (newest first)");

        // $lastId = null;
        $lastId = $this->startFromId;
        $processed = 0;
        $chunkCount = 0;

        do {
            $query = Product::with(['category', 'brand'])
                ->select(['id', 'sku', 'cat_id', 'brand_id', 'size', 'created_at'])
                ->orderByDesc('id');

            if ($lastId !== null) {
                $query->where('id', '<', $lastId); // fetch IDs < last processed
            }

            $products = $query->limit(self::CHUNK_SIZE)->get();

            if ($products->isEmpty()) break;

            $chunkCount++;
            $this->info("🔄 Chunk {$chunkCount} | Processing {$products->count()} products");

            $this->processChunk($products, $chunkCount);

            $processed += $products->count();
            $this->stats['chunks_processed']++;
            $this->manageMemory();

            $this->info("✅ Total Processed: {$processed}");

            $lastId = $products->last()->id;
        } while (true);

        $this->info("🏁 All products processed.");
    }


    private function processChunk(Collection $products, int $chunkId): void
    {
        DB::beginTransaction();

        try {
            $updates = [];
            $startTime = microtime(true);

            foreach ($products as $product) {
                $this->stats['processed']++;

                $sku = $this->generateUniqueSKU($product);

                if (!$sku) {
                    $this->stats['errors']++;
                    $this->warn("⚠️  Skipped product ID {$product->id}: could not generate unique SKU after " . self::MAX_RETRIES . " attempts");
                    continue;
                }

                $updates[] = [
                    'id' => $product->id,
                    'sku' => $sku,
                    'updated_at' => now(),
                ];

                $this->generatedSkus[$sku] = true;
                $this->stats['generated']++;

                $cat = $product->category->title ?? 'N/A';
                $brand = $product->brand->title ?? 'N/A';
                $this->info("{$product->id}");
                // $this->info("🧬 ID {$product->id} | SKU: {$sku} | Cat: {$cat} | Brand: {$brand}");
            }

            if (!empty($updates)) {
                $this->applySkuUpdates($updates);
                $this->stats['updated'] += count($updates);

                $timeTaken = round(microtime(true) - $startTime, 2);
                $this->info("✅ Chunk {$chunkId} completed in {$timeTaken}s | SKUs Updated: " . count($updates));
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->stats['errors']++;
            $this->error("❌ Error in chunk {$chunkId}: " . $e->getMessage());
            throw $e;
        }
    }

    private function applySkuUpdates(array $updates): void
    {
        if (empty($updates)) return;

        $ids = array_column($updates, 'id');
        $skuCases = [];
        $timestampCases = [];

        foreach ($updates as $row) {
            $skuCases[] = "WHEN {$row['id']} THEN " . DB::getPdo()->quote($row['sku']);
            $timestampCases[] = "WHEN {$row['id']} THEN " . DB::getPdo()->quote($row['updated_at']) . "::timestamp";
        }

        DB::statement("
            UPDATE products
            SET
                sku = CASE id " . implode(' ', $skuCases) . " END,
                updated_at = CASE id " . implode(' ', $timestampCases) . " END
            WHERE id IN (" . implode(',', $ids) . ")
        ");
    }

    private function generateUniqueSKU(Product $product): ?string
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $sku = $this->generateSKU($product, $attempt);

            if (isset($this->generatedSkus[$sku])) {
                $this->stats['duplicates_resolved']++;
                continue;
            }

            if (!Product::where('sku', $sku)->exists()) {
                return $sku;
            }

            $this->stats['duplicates_resolved']++;
        }

        return null;
    }

    private function generateSKU(Product $product, int $attempt): string
    {
        $cat = $this->getCode($product->category->code ?? $product->category->name ?? 'GEN');
        $brand = $this->getCode($product->brand->code ?? $product->brand->name ?? 'GEN');
        $variant = $this->mapSize($product->size) . $this->hashDigit($product->id);
        $unique = $this->generateUniqueID($product->id, $attempt);
        $checksum = $this->crc16Checksum($cat . $brand . $variant . $unique);

        return $cat . $brand . $variant . $unique . $checksum;
    }

    private function getCode(string $value): string
    {
        return strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', strtolower($value)), 0, 3)) ?: 'XXX';
    }

    private function mapSize(?string $size): string
    {
        $map = ['XS' => '1', 'S' => '2', 'M' => '3', 'L' => '4', 'XL' => '5'];
        return $map[strtoupper($size ?? '')] ?? $this->hashDigit($size ?? '0');
    }

    private function hashDigit($input): string
    {
        return substr(dechex(crc32((string) $input)), -1);
    }

    private function generateUniqueID(int $productId, int $attempt): string
    {
        $idPart = str_pad(substr((string)$productId, -2), 2, '0', STR_PAD_LEFT);
        $timePart = substr(dechex(time()), -1);
        $retryPart = dechex($attempt % 16);
        return $idPart . $timePart . $retryPart;
    }

    private function crc16Checksum(string $input): string
    {
        $crc = 0xFFFF;
        $poly = 0x1021;

        for ($i = 0; $i < strlen($input); $i++) {
            $crc ^= (ord($input[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ $poly) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc & 0xFF), 2, '0', STR_PAD_LEFT));
    }

    private function manageMemory(): void
    {
        $this->memoryCheckCounter++;

        if ($this->memoryCheckCounter % self::MEMORY_LIMIT_CHECK !== 0) return;

        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        $this->stats['memory_peak'] = max($this->stats['memory_peak'], $peak);

        if ($current > 256 * 1024 * 1024) {
            $this->info("🧹 Memory cleanup triggered");
            $this->generatedSkus = [];
            gc_collect_cycles();
        }

        $percent = round(($current / (512 * 1024 * 1024)) * 100, 1);
        $this->info("💾 Memory: {$this->formatBytes($current)} ({$percent}%) | Peak: " . $this->formatBytes($peak));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = min((int)log($bytes, 1024), count($units) - 1);
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    private function cleanup(): void
    {
        $this->generatedSkus = [];
        gc_collect_cycles();
    }

    private function printStats(float $duration): void
    {
        $rate = $this->stats['processed'] > 0 ? round($this->stats['processed'] / $duration, 2) : 0;
        $this->info("============== SKU Seeder Complete ==============");
        $this->info("🔄 Processed           : {$this->stats['processed']}");
        $this->info("✅ SKUs Generated      : {$this->stats['generated']}");
        $this->info("📝 SKUs Updated        : {$this->stats['updated']}");
        $this->info("🔁 Duplicates Resolved : {$this->stats['duplicates_resolved']}");
        $this->info("📦 Chunks Processed    : {$this->stats['chunks_processed']}");
        $this->info("❌ Errors              : {$this->stats['errors']}");
        $this->info("💾 Peak Memory         : " . $this->formatBytes($this->stats['memory_peak']));
        $this->info("⚡ Processing Rate     : {$rate} records/sec");
        $this->info("⏱️  Total Time         : " . round($duration, 2) . "s");
        $this->info("=================================================");
    }

    private function info(string $message): void
    {
        echo "[" . now() . "] {$message}" . PHP_EOL;
    }

    private function warn(string $message): void
    {
        echo "\033[33m[" . now() . "] {$message}\033[0m" . PHP_EOL;
    }

    private function error(string $message): void
    {
        echo "\033[31m[" . now() . "] {$message}\033[0m" . PHP_EOL;
    }
}
