#!/bin/bash
# build.sh

echo "Building optimized assets..."

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Install dependencies
npm ci

# Build with maximum optimization
NODE_ENV=production npm run production

# Custom optimization
php artisan assets:optimize

# Generate service worker for caching
php artisan make:pwa

echo "Build complete!"