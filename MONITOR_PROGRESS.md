# 📊 Monitor Seeding Progress

## Quick Status Check (PowerShell)

```powershell
# Check product count
$env:PGPASSWORD="your_password"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d varintbase-db-lg -c "SELECT COUNT(*) as products FROM products;"

# Check all tables
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d varintbase-db-lg -c "
SELECT 
    'products' as table_name, COUNT(*) as records FROM products
UNION ALL
SELECT 'product_variants', COUNT(*) FROM product_variants
UNION ALL
SELECT 'product_images', COUNT(*) FROM product_images
UNION ALL
SELECT 'variant_images', COUNT(*) FROM variant_images
ORDER BY table_name;
"
```

## Expected Progress (Current Run at 399/s)

| Time Elapsed | Products Seeded | % Complete | Remaining Time |
|--------------|----------------|------------|----------------|
| 1 hour       | ~1,436,400     | 14.4%      | ~6 hours       |
| 2 hours      | ~2,872,800     | 28.7%      | ~5 hours       |
| 3 hours      | ~4,309,200     | 43.1%      | ~4 hours       |
| 4 hours      | ~5,745,600     | 57.5%      | ~3 hours       |
| 5 hours      | ~7,182,000     | 71.8%      | ~2 hours       |
| 6 hours      | ~8,618,400     | 86.2%      | ~1 hour        |
| 7 hours      | ~10,054,800    | 100.5%     | Done!          |

## Visual Progress Monitor

Save this as `monitor-seeding.ps1`:

```powershell
$target = 10000000
$startCount = 96001

while ($true) {
    Clear-Host
    Write-Host "═══════════════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host "     POSTGRESQL SEEDING PROGRESS MONITOR" -ForegroundColor Cyan
    Write-Host "═══════════════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host ""
    
    $env:PGPASSWORD="your_password"
    $result = & "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d varintbase-db-lg -t -c "SELECT COUNT(*) FROM products;" 2>$null
    $current = [int]$result.Trim()
    
    $seeded = $current - $startCount
    $percent = ($seeded / $target) * 100
    $remaining = $target - $seeded
    
    Write-Host "  Target:     " -NoNewline
    Write-Host ("{0:N0}" -f $target) -ForegroundColor Green
    Write-Host "  Current:    " -NoNewline
    Write-Host ("{0:N0}" -f $current) -ForegroundColor Yellow
    Write-Host "  Seeded:     " -NoNewline
    Write-Host ("{0:N0}" -f $seeded) -ForegroundColor Cyan
    Write-Host "  Remaining:  " -NoNewline
    Write-Host ("{0:N0}" -f $remaining) -ForegroundColor Magenta
    Write-Host ""
    Write-Host "  Progress:   " -NoNewline
    Write-Host ("{0:F2}%" -f $percent) -ForegroundColor Green
    Write-Host ""
    
    # Progress bar
    $barLength = 50
    $filled = [int](($percent / 100) * $barLength)
    $empty = $barLength - $filled
    Write-Host "  [" -NoNewline
    Write-Host ("█" * $filled) -ForegroundColor Green -NoNewline
    Write-Host ("░" * $empty) -ForegroundColor DarkGray -NoNewline
    Write-Host "]"
    Write-Host ""
    Write-Host "═══════════════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host "  Press Ctrl+C to stop monitoring" -ForegroundColor DarkGray
    Write-Host "  Refreshing every 30 seconds..." -ForegroundColor DarkGray
    
    if ($seeded -ge $target) {
        Write-Host ""
        Write-Host "  ✅ SEEDING COMPLETE!" -ForegroundColor Green
        break
    }
    
    Start-Sleep -Seconds 30
}
```

Run with:
```powershell
.\monitor-seeding.ps1
```

## Database Size Monitor

```powershell
# Check database size growth
$env:PGPASSWORD="your_password"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d varintbase-db-lg -c "
SELECT 
    pg_size_pretty(pg_database_size('varintbase-db-lg')) as database_size;
"

# Check table sizes
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d varintbase-db-lg -c "
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables
WHERE schemaname = 'public'
AND tablename LIKE '%product%'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;
"
```

## Performance Metrics

```powershell
# Check current insert rate (samples over 1 minute)
$env:PGPASSWORD="your_password"
$count1 = & "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d varintbase-db-lg -t -c "SELECT COUNT(*) FROM products;" 2>$null
$count1 = [int]$count1.Trim()

Start-Sleep -Seconds 60

$count2 = & "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d varintbase-db-lg -t -c "SELECT COUNT(*) FROM products;" 2>$null
$count2 = [int]$count2.Trim()

$rate = $count2 - $count1
Write-Host "Current insert rate: $rate products/minute (~$([int]($rate/60)) products/second)"
```

## Check for Issues

```powershell
# Check for locks
$env:PGPASSWORD="your_password"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d varintbase-db-lg -c "
SELECT 
    pid,
    state,
    wait_event_type,
    wait_event,
    query
FROM pg_stat_activity
WHERE datname = 'varintbase-db-lg'
AND state != 'idle'
ORDER BY query_start;
"

# Check for blocking queries
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -d varintbase-db-lg -c "
SELECT 
    blocked_locks.pid AS blocked_pid,
    blocking_locks.pid AS blocking_pid,
    blocked_activity.query AS blocked_query
FROM pg_catalog.pg_locks blocked_locks
JOIN pg_catalog.pg_stat_activity blocked_activity ON blocked_activity.pid = blocked_locks.pid
JOIN pg_catalog.pg_locks blocking_locks ON blocking_locks.locktype = blocked_locks.locktype
JOIN pg_catalog.pg_stat_activity blocking_activity ON blocking_activity.pid = blocking_locks.pid
WHERE NOT blocked_locks.granted;
"
```

## System Resource Monitor

```powershell
# CPU and Memory usage
while ($true) {
    Clear-Host
    Write-Host "═══════════════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host "           SYSTEM RESOURCE MONITOR" -ForegroundColor Cyan
    Write-Host "═══════════════════════════════════════════════════" -ForegroundColor Cyan
    
    # CPU
    $cpu = Get-Counter '\Processor(_Total)\% Processor Time' | Select-Object -ExpandProperty CounterSamples | Select-Object -ExpandProperty CookedValue
    Write-Host ""
    Write-Host "  CPU Usage: " -NoNewline
    Write-Host ("{0:F1}%" -f $cpu) -ForegroundColor $(if($cpu -gt 80){"Red"}else{"Green"})
    
    # Memory
    $os = Get-CimInstance Win32_OperatingSystem
    $totalMem = [math]::Round($os.TotalVisibleMemorySize / 1MB, 2)
    $freeMem = [math]::Round($os.FreePhysicalMemory / 1MB, 2)
    $usedMem = $totalMem - $freeMem
    $memPercent = ($usedMem / $totalMem) * 100
    
    Write-Host "  Memory:    " -NoNewline
    Write-Host ("{0:F2} GB / {1:F2} GB ({2:F1}%)" -f $usedMem, $totalMem, $memPercent) -ForegroundColor $(if($memPercent -gt 90){"Red"}else{"Green"})
    
    # Disk
    $disk = Get-PSDrive D | Select-Object Used,Free
    $totalDisk = ($disk.Used + $disk.Free) / 1GB
    $usedDisk = $disk.Used / 1GB
    $freeDisk = $disk.Free / 1GB
    $diskPercent = ($usedDisk / $totalDisk) * 100
    
    Write-Host "  Disk (D:): " -NoNewline
    Write-Host ("{0:F2} GB free / {1:F2} GB total" -f $freeDisk, $totalDisk) -ForegroundColor $(if($freeDisk -lt 20){"Red"}else{"Green"})
    Write-Host ""
    Write-Host "═══════════════════════════════════════════════════" -ForegroundColor Cyan
    
    Start-Sleep -Seconds 5
}
```

## Quick Commands Reference

```powershell
# 1. Check progress
psql -U postgres -d varintbase-db-lg -c "SELECT COUNT(*) FROM products;"

# 2. Check rate (wait 60 seconds between commands)
psql -U postgres -d varintbase-db-lg -c "SELECT COUNT(*) FROM products;"

# 3. Estimate remaining time
# (10,000,000 - current_count) / 399 seconds

# 4. Check disk space
Get-PSDrive D

# 5. Check PostgreSQL process
Get-Process postgres | Select-Object CPU,WorkingSet,Handles
```

## Troubleshooting

### If rate drops significantly:
1. Check disk space
2. Check system resources (CPU, memory)
3. Check PostgreSQL logs
4. Check for blocking queries

### If seeder stops:
1. Check terminal for errors
2. Check PostgreSQL logs
3. Verify sequences are correct
4. Resume or restart seeding

---

**Happy monitoring!** 📊
