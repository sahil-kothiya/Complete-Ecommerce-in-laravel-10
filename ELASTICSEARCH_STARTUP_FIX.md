# ⚡ ELASTICSEARCH STARTUP GUIDE

## Issue: Permission Denied on Log Files

This happens when Elasticsearch log files are locked. Here's how to fix it:

### Option 1: Delete Old Logs (Quickest)

**Run as Administrator:**
```powershell
# Stop any existing Elasticsearch processes
Get-Process | Where-Object {$_.ProcessName -like "*java*"} | Stop-Process -Force

# Delete old log files
Remove-Item D:\elasticsearch-9.0.2\logs\* -Force

# Start Elasticsearch
cd D:\elasticsearch-9.0.2\bin
.\elasticsearch.bat
```

### Option 2: Run PowerShell as Administrator

1. Close all PowerShell windows
2. Right-click PowerShell → **Run as Administrator**
3. Run:
```powershell
cd D:\elasticsearch-9.0.2\bin
.\elasticsearch.bat
```

### Option 3: Change Log Directory Permissions

```powershell
# Give full permissions to logs directory
icacls D:\elasticsearch-9.0.2\logs /grant Everyone:F /T
```

---

## After Elasticsearch Starts Successfully

You'll see this message:
```
[node-1] started
```

Then in a **NEW terminal**, go back to your project:
```powershell
cd D:\wamp64\www\Enterprice-Ecommerce
php artisan elasticsearch:index-all --chunk=2000
```

---

## Quick Check

Verify Elasticsearch is running:
```powershell
curl http://localhost:9200
```

Should return:
```json
{
  "name" : "node-1",
  "cluster_name" : "ecommerce-cluster",
  "version" : { "number" : "9.0.2" }
}
```

---

## Current Status

✅ Redis indexes: Built (35 keys)  
❌ Elasticsearch: Permission issue - use Option 1 above  
✅ Database: Ready (100,000 products)

**You're almost there!** Just need to start Elasticsearch properly.
