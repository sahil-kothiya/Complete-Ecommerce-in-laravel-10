<?php
// Boot Laravel and write a few test log lines to verify locale settings
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Carbon\Carbon;

Log::info('TEST LOG: Locale: ' . App::getLocale());
Log::info('TEST LOG: setlocale LC_ALL: ' . setlocale(LC_ALL, 0));
Log::info('TEST LOG: Carbon locale: ' . Carbon::getLocale());
Log::info('TEST LOG: Carbon now isoFormat LLLL: ' . Carbon::now()->isoFormat('LLLL'));

echo "Wrote test logs\n";
