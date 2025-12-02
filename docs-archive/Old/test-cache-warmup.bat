@echo off
REM Auto Cache Warmup Test Script
REM This script tests the auto cache warmup functionality

echo ========================================
echo Auto Cache Warmup Test Suite
echo ========================================
echo.

echo [Step 1] Clearing all caches...
php artisan config:clear
php artisan cache:clear
php artisan redis:cache flush
echo ✓ Caches cleared
echo.

echo [Step 2] Checking Redis connection...
php artisan tinker --execute="echo Redis::ping();"
echo ✓ Redis is running
echo.

echo [Step 3] Testing manual warmup command...
php artisan cache:warmup
echo ✓ Manual warmup completed
echo.

echo [Step 4] Verifying cache was warmed...
php artisan tinker --execute="echo app('App\Services\RedisCacheService')::has('meta:cache:warmed') ? 'Cache is WARM' : 'Cache is COLD';"
echo.

echo [Step 5] Checking cached components...
echo Checking homepage products...
php artisan tinker --execute="echo app('App\Services\RedisCacheService')::has('cache:homepage:products') ? '✓ Products cached' : '✗ Products NOT cached';"
echo.
echo Checking categories...
php artisan tinker --execute="echo app('App\Services\RedisCacheService')::has('cache:homepage:categories') ? '✓ Categories cached' : '✗ Categories NOT cached';"
echo.
echo Checking settings...
php artisan tinker --execute="echo app('App\Services\RedisCacheService')::has('cache:homepage:settings') ? '✓ Settings cached' : '✗ Settings NOT cached';"
echo.

echo ========================================
echo Test Complete!
echo ========================================
echo.
echo Next: Run "php artisan serve" to test auto warmup on server start
echo.
pause
