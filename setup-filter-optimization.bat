@echo off
echo ========================================
echo FILTER PAGE OPTIMIZATION - QUICK SETUP
echo ========================================
echo.

cd /d D:\wamp64\www\Enterprice-Ecommerce

echo Step 1: Checking current system status...
echo.
php check-systems.php
echo.

echo ========================================
echo What needs to be done:
echo ========================================
echo.
echo 1. START ELASTICSEARCH (if not running)
echo    Open NEW PowerShell window:
echo    cd D:\elasticsearch-9.0.2\bin
echo    .\elasticsearch.bat
echo.
echo 2. BUILD REDIS INDEXES (one-time, 5-10 min)
echo    php artisan indexes:manage build
echo.
echo 3. INDEX PRODUCTS IN ELASTICSEARCH (one-time, 10-20 min for 100K products)
echo    php artisan elasticsearch:index-all --chunk=2000
echo.
echo ========================================
echo After completing above steps:
echo ========================================
echo.
echo Test your filter page:
echo http://127.0.0.1:8000/product-cat/YOUR_PATH?brand[]=nike^&price_range=100-500
echo.
echo Expected result: Page loads in ^< 2 seconds!
echo.
echo ========================================
echo.

pause
