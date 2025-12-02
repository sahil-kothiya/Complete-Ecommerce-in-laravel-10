<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$reflection = new ReflectionClass(App\Http\Controllers\FrontendController::class);
echo $reflection->getFileName() . "\n";
