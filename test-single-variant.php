<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ProductVariant;

$variantId = 38017;
$variant = ProductVariant::with('variantOptions.variantType')->find($variantId);

if ($variant) {
    echo "✅ Variant ID: {$variant->id}\n";
    echo "SKU: {$variant->sku}\n";
    echo "Display Name: {$variant->display_name}\n";
    echo "Option Assignments: {$variant->optionAssignments->count()}\n";
    echo "Options:\n";
    foreach ($variant->variantOptions as $option) {
        $typeName = $option->variantType->display_name ?? 'Unknown';
        echo "  - {$typeName}: {$option->display_value}\n";
    }
}
