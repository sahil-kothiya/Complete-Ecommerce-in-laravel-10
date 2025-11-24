<?php

$file = 'app/Services/ProductIndexService.php';
$content = file_get_contents($file);

// Replace all echo statements with Log statements
$content = str_replace('echo "\n[2/5] Building brand indexes for {$totalBrands} brands...\n";', 'Log::info("[2/5] Building brand indexes for {$totalBrands} brands...");', $content);
$content = str_replace('echo "  Progress: {$processed}/{$totalBrands} ({$percentComplete}%) - {$elapsed}s\r";', 'if ($processed % 5 == 0 || $processed == $totalBrands) { Log::debug("  Brand progress: {$processed}/{$totalBrands} ({$percentComplete}%) - {$elapsed}s"); }', $content);
$content = str_replace('echo "\n✅ [2/5] Brands: {$brandsIndexed} indexed in {$totalTime}s\n";', '// Removed duplicate echo', $content);

$content = str_replace('echo "\n[3/5] Building price range indexes ({$total} ranges)...\n";', 'Log::info("[3/5] Building price range indexes ({$total} ranges)...");', $content);
$content = str_replace('echo "  {$key}: " . count($allProductIds) . " products ({$rangeTime}s)\n";', 'Log::debug("  {$key}: " . count($allProductIds) . " products ({$rangeTime}s)");', $content);
$content = str_replace('echo "\n✅ [3/5] Price ranges: {$total} indexed in {$totalTime}s\n";', '// Removed duplicate echo', $content);

$content = str_replace('echo "\n[4/5] Building rating indexes ({$total} levels)...\n";', 'Log::info("[4/5] Building rating indexes ({$total} levels)...");', $content);
$content = str_replace('echo "  {$minRating}+ stars: " . count($productIds) . " products ({$ratingTime}s)\n";', 'Log::debug("  {$minRating}+ stars: " . count($productIds) . " products ({$ratingTime}s)");', $content);
$content = str_replace('echo "\n✅ [4/5] Ratings: {$total} levels indexed in {$totalTime}s\n";', '// Removed duplicate echo', $content);

$content = str_replace('echo "\n[5/5] Building discount indexes ({$total} levels)...\n";', 'Log::info("[5/5] Building discount indexes ({$total} levels)...");', $content);
$content = str_replace('echo "  {$minDiscount}%+: " . count($allProductIds) . " products ({$discountTime}s)\n";', 'Log::debug("  {$minDiscount}%+: " . count($allProductIds) . " products ({$discountTime}s)");', $content);
$content = str_replace('echo "\n✅ [5/5] Discounts: {$total} levels indexed in {$totalTime}s\n";', '// Removed duplicate echo', $content);

// Fix the duplicate progress logging
$content = str_replace('if ($processed % 10 == 0 || $processed == $totalBrands) {', 'if ($processed % 5 == 0 || $processed == $totalBrands) {', $content);

file_put_contents($file, $content);

echo "✅ All echo statements removed and replaced with Log statements\n";
echo "✅ Progress logging optimized to every 5 items\n";
