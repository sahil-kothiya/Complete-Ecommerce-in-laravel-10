<?php

// Create product placeholder directory
$productDir = 'storage/app/public/products';
if (!is_dir($productDir)) {
    mkdir($productDir, 0755, true);
    echo "Created product directory\n";
}

// Create variant placeholder directory
$variantDir = 'storage/app/public/products/variants';
if (!is_dir($variantDir)) {
    mkdir($variantDir, 0755, true);
    echo "Created variant directory\n";
}

// Simple 1x1 transparent WebP (base64 decoded)
$webpData = base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAQAcJaQAA3AA/v3AgAA=');

// Create simple 1x1 transparent WebP placeholder for product
$productPlaceholder = $productDir . '/placeholder.webp';
if (!file_exists($productPlaceholder)) {
    file_put_contents($productPlaceholder, $webpData);
    echo "Created product placeholder\n";
}

// Create simple 1x1 transparent WebP placeholder for variants
$variantPlaceholder = $variantDir . '/placeholder.webp';
if (!file_exists($variantPlaceholder)) {
    file_put_contents($variantPlaceholder, $webpData);
    echo "Created variant placeholder\n";
}

echo "Done! Placeholder images created.\n";
