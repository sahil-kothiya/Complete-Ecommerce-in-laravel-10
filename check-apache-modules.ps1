# Apache Module Checker for WAMP
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "APACHE MODULE VERIFICATION" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""

$apacheConf = "D:\wamp64\bin\apache\apache2.4.62.1\conf\httpd.conf"

if (Test-Path $apacheConf) {
    Write-Host "Checking Apache configuration..." -ForegroundColor Yellow
    Write-Host ""
    
    $requiredModules = @{
        "mod_deflate" = "deflate_module"
        "mod_headers" = "headers_module"
        "mod_expires" = "expires_module"
        "mod_filter" = "filter_module"
        "mod_setenvif" = "setenvif_module"
        "mod_rewrite" = "rewrite_module"
    }
    
    $content = Get-Content $apacheConf -Raw
    
    foreach ($module in $requiredModules.GetEnumerator()) {
        $moduleName = $module.Key
        $moduleDirective = $module.Value
        
        # Check if module is enabled (not commented)
        $pattern = "^\s*LoadModule\s+$moduleDirective"
        $commentedPattern = "^\s*#\s*LoadModule\s+$moduleDirective"
        
        if ($content -match $pattern) {
            Write-Host "[OK] $moduleName is ENABLED" -ForegroundColor Green
        } elseif ($content -match $commentedPattern) {
            Write-Host "[WARN] $moduleName is DISABLED (commented)" -ForegroundColor Red
            Write-Host "       Enable it via: WAMP -> Apache -> Apache Modules -> $moduleDirective" -ForegroundColor Yellow
        } else {
            Write-Host "[INFO] $moduleName not found in config" -ForegroundColor Yellow
        }
    }
    
    Write-Host ""
    Write-Host "==================================" -ForegroundColor Cyan
    Write-Host "HOW TO ENABLE MODULES:" -ForegroundColor Cyan
    Write-Host "1. Right-click WAMP tray icon" -ForegroundColor White
    Write-Host "2. Apache -> Apache Modules" -ForegroundColor White
    Write-Host "3. Check modules listed above" -ForegroundColor White
    Write-Host "4. Restart WAMP" -ForegroundColor White
    Write-Host "==================================" -ForegroundColor Cyan
    
} else {
    Write-Host "[ERROR] Apache config not found at: $apacheConf" -ForegroundColor Red
    Write-Host "Update the path in this script" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Press any key to continue..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
