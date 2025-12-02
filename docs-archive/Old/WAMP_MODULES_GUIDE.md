# 🎯 WAMP MODULE ENABLEMENT GUIDE

## VISUAL STEP-BY-STEP INSTRUCTIONS

### 1️⃣ OPEN WAMP MENU
```
Location: Bottom-right corner of Windows (system tray)
Look for: Green "W" icon (or red/orange if stopped)
Action: RIGHT-CLICK the icon
```

### 2️⃣ NAVIGATE TO APACHE MODULES
```
In the popup menu:
1. Hover over "Apache" (DO NOT CLICK)
2. A submenu will appear
3. Click "Apache Modules"
4. Another menu will appear with a LONG list
```

### 3️⃣ ENABLE REQUIRED MODULES
```
In the modules list, CLICK on these (to add checkmark):

✓ deflate_module          <- CRITICAL for compression
✓ headers_module          <- CRITICAL for headers
✓ expires_module          <- For browser caching
✓ filter_module           <- For compression
✓ setenvif_module         <- For compression
✓ rewrite_module          <- Usually already enabled

NOTE: 
- Modules WITH checkmark = ENABLED
- Modules WITHOUT checkmark = DISABLED
- Click to toggle on/off
```

### 4️⃣ VERIFY CHECKMARKS
```
After clicking each module, the menu closes.
Repeat steps 1-2 to open menu again and verify:
- You should see ✓ next to all modules above
```

### 5️⃣ RESTART WAMP
```
1. Right-click WAMP icon again
2. Click "Restart All Services"
   OR
   Click "Stop All Services", wait 5 seconds, then "Start All Services"
   
3. Icon should turn GREEN when ready
```

---

## 🔍 MODULE VERIFICATION

After restart, check if modules are loaded:

```powershell
# Method 1: Check PHP info
php -r "phpinfo();" | Select-String "deflate"

# Method 2: Test compression directly
$response = Invoke-WebRequest -Uri "http://localhost/Enterprice-Ecommerce/public" -Headers @{"Accept-Encoding"="gzip"}
$response.Headers["Content-Encoding"]
# Should output: gzip
```

---

## 🆘 TROUBLESHOOTING

### Can't find WAMP icon?
- Press Windows key
- Type "WAMP"
- Right-click "Wampserver"
- Click "Open"
- Icon will appear in system tray

### Icon is RED?
- Services aren't running
- Click icon → "Start All Services"
- Wait for GREEN

### Icon is ORANGE?
- Some services running, some not
- Click icon → "Restart All Services"

### Modules menu too long to find modules?
- Use mouse scroll wheel
- Or look for modules alphabetically:
  - d: deflate_module
  - e: expires_module
  - f: filter_module
  - h: headers_module
  - r: rewrite_module
  - s: setenvif_module

### Don't see checkmark after clicking?
- Menu closes after click (normal)
- Re-open menu to verify
- Checkmark = enabled

---

## ✅ SUCCESS CONFIRMATION

You've successfully enabled modules when:

1. ✓ All 6 modules have checkmarks
2. ✓ WAMP icon is GREEN
3. ✓ Apache service is running
4. ✓ Can access: http://localhost

Then proceed to run Lighthouse test!

---

## 📋 COMPLETE WORKFLOW

```
STEP 1: Enable modules (via WAMP menu)          ← YOU ARE HERE
STEP 2: Restart WAMP (icon should turn green)
STEP 3: Clear browser cache (Ctrl+Shift+Del)
STEP 4: Visit homepage twice
STEP 5: Run Lighthouse test
STEP 6: Celebrate 95-100 score! 🎉
```

---

**Time required: 2-3 minutes**
**Difficulty: Easy (just clicking checkboxes)**
**Result: 760+ KiB saved, 95-100 Lighthouse score**
