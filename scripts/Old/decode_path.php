<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Helpers\UrlEncryptor;
use App\Models\Category;

$encoded = $argv[1] ?? '';
if (!$encoded) {
    echo "Provide encoded path\n";
    exit(1);
}

$decoded = UrlEncryptor::decodePath($encoded);

echo "Decoded path: {$decoded}\n";

$segments = array_filter(explode('/', trim($decoded, '/')));
if (!$segments) {
    exit(0);
}

$category = Category::whereNull('parent_id')
    ->where('status', 'active')
    ->where('slug', $segments[0])
    ->first();

if ($category) {
    echo "Category: {$category->title} (ID {$category->id})\n";
} else {
    echo "Category not found\n";
}
