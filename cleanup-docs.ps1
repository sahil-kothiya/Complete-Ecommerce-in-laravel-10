# Documentation Cleanup Script
# Moves old/duplicate documentation files to an archive folder

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "DOCUMENTATION CLEANUP" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

$archiveDir = "docs-archive-$(Get-Date -Format 'yyyy-MM-dd')"

# Create archive directory
if (!(Test-Path $archiveDir)) {
    New-Item -ItemType Directory -Path $archiveDir | Out-Null
    Write-Host "✅ Created archive directory: $archiveDir`n" -ForegroundColor Green
}

# List of files to archive (outdated/duplicate)
$filesToArchive = @(
    # Filter-related (outdated)
    "FILTER_CACHING_STRATEGY.md",
    "FILTER_CACHING_COMPARISON.md",
    "FILTER_PAGE_OPTIMIZATION_GUIDE.md",
    "FILTER_OPTIMIZATION_README.md",
    "SMART_FILTER_CACHING_GUIDE.md",

    # Homepage (duplicate)
    "HOME_PAGE_DEBUG_SUMMARY.md",
    "HOMEPAGE_FIRST_LOAD_FIXED.md",
    "HOMEPAGE_OPTIMIZATION_README.md",

    # Redis (duplicate)
    "REDIS_STRUCTURE_FIXED_FINAL.md",
    "REDIS_MIGRATION_COMPLETE.md",
    "REDIS_CACHING_ARCHITECTURE.md",
    "REDIS_CACHE_TRACKING.md",
    "REDIS_CACHE_STRUCTURE.md",
    "REDIS_ARCHITECTURE_V2.md",

    # Cache warmup (duplicate)
    "CACHE_WARMUP_SUMMARY.md",
    "CACHE_WARMUP_QUICK_START.md",
    "CACHE_WARMUP_GUIDE.md",
    "CACHE_WARMUP_CHECKLIST.md",
    "CACHE_STRUCTURE_FIXED.md",
    "CACHE_QUICK_REFERENCE.md",

    # Status/Progress (outdated)
    "SUCCESS_STATUS.md",
    "STATUS_REPORT.md",
    "CURRENT_STATUS_AND_NEXT_STEPS.md",
    "STRUCTURE_CLEANUP_SUMMARY.md",
    "SCRIPTS_CLEANUP.md",

    # Implementation (duplicate)
    "IMPLEMENTATION_SUMMARY.md",
    "IMPLEMENTATION_GUIDE.md",
    "OPTIMIZATION_IMPLEMENTATION_SUMMARY.md",
    "OPTIMIZATION_SUMMARY.md",
    "SPEED_BOOST_GUIDE.md",

    # Other
    "ARCHITECTURE_VISUAL.md",
    "MONITOR_PROGRESS.md",
    "NEXT_RUN_FASTER.md",
    "QUICK_SETUP_GUIDE.md",
    "INDEXING_QUICK_START.md",
    "ELASTICSEARCH_SETUP.md",
    "ELASTICSEARCH_STARTUP_FIX.md",
    "SEEDER_QUICK_START.md",
    "SEEDER_PERFORMANCE_GUIDE.md"
)

$movedCount = 0
$notFoundCount = 0

foreach ($file in $filesToArchive) {
    if (Test-Path $file) {
        Move-Item -Path $file -Destination "$archiveDir\$file" -Force
        Write-Host "  Archived: $file" -ForegroundColor Gray
        $movedCount++
    } else {
        Write-Host "  Not found: $file" -ForegroundColor Yellow
        $notFoundCount++
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "SUMMARY" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "✅ Files archived: $movedCount" -ForegroundColor Green
Write-Host "⚠️  Files not found: $notFoundCount" -ForegroundColor Yellow
Write-Host "`nArchive location: $archiveDir`n" -ForegroundColor Cyan

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "CURRENT DOCUMENTATION (KEEP THESE)" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

$keepFiles = @(
    "README.md",
    "DOCUMENTATION_INDEX.md",
    "INSTALLATION_GUIDE.md",
    "10M_PRODUCTS_SETUP_GUIDE.md",
    "README_HOMEPAGE_FIX.md",
    "HOMEPAGE_OPTIMIZATION_COMPLETE.md",
    "DATABASE_INDEX_ANALYSIS.md",
    "DEPLOYMENT_CHECKLIST.md",
    "TESTING_GUIDE.md"
)

foreach ($file in $keepFiles) {
    if (Test-Path $file) {
        Write-Host "  ✅ $file" -ForegroundColor Green
    } else {
        Write-Host "  ❌ $file (missing)" -ForegroundColor Red
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Cleanup complete!" -ForegroundColor Green
Write-Host "========================================`n" -ForegroundColor Cyan
