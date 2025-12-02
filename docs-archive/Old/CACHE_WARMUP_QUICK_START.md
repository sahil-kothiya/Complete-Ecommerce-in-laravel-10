# ⚡ Quick Setup - Auto Cache Warmup

## 1️⃣ Update Your .env File

Add these lines to your `.env`:

```env
# Auto Cache Warmup - Master Switch
CACHE_WARMUP_ENABLED=true
CACHE_WARMUP_MODE=on_serve
CACHE_WARMUP_EXECUTION_MODE=sync
```

## 2️⃣ Clear Config Cache

```bash
php artisan config:clear
php artisan cache:clear
```

## 3️⃣ Test It!

```bash
php artisan serve
```

**Expected Output:**
```
┌─────────────────────────────────────────────────────────┐
│  🔥 Warming Homepage Cache...                           │
│  ⏳ Please wait...                                      │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  ✅ Cache Warmup Complete!                              │
│  ⏱️  Duration: 143ms                                    │
│  🚀 Homepage will load in ~10-20ms!                     │
└─────────────────────────────────────────────────────────┘

Laravel development server started: http://127.0.0.1:8000
```

## 4️⃣ Visit Homepage

Open browser: http://127.0.0.1:8000

**Result**: Homepage loads in ~10-20ms! 🚀

---

## 🎯 That's It!

Your homepage cache is now automatically warmed on every server start.

### To Disable:
```env
CACHE_WARMUP_ENABLED=false
```

### Manual Warmup:
```bash
php artisan cache:warmup
```

---

## 🔍 Verify It's Working

```bash
# Check cache status
php artisan tinker
>>> app('App\Services\RedisCacheService')::has('meta:cache:warmed')
=> true

# View cached components
>>> app('App\Services\RedisCacheService')::has('cache:homepage:products')
=> true

# Exit tinker
>>> exit
```

---

## 📚 Full Documentation

See `CACHE_WARMUP_GUIDE.md` for detailed documentation.

---

**🎉 Enjoy 25x faster homepage loads!**
