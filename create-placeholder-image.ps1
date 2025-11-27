# Create a "No Image Available" placeholder for products
Write-Host "Creating product placeholder image..." -ForegroundColor Cyan

$publicPath = "public/images"
$imagePath = "$publicPath/no-product-image.png"

# Create directory if it doesn't exist
if (-not (Test-Path $publicPath)) {
    New-Item -ItemType Directory -Path $publicPath -Force | Out-Null
    Write-Host "Created images directory" -ForegroundColor Green
}

# Check if ImageMagick is available
$hasImageMagick = Get-Command "magick" -ErrorAction SilentlyContinue

if ($hasImageMagick) {
    Write-Host "Using ImageMagick to create placeholder..." -ForegroundColor Yellow
    
    # Create a 800x800 placeholder with text
    magick -size 800x800 xc:#f0f0f0 `
        -gravity center `
        -pointsize 48 `
        -fill "#999999" `
        -annotate +0+0 "No Image Available" `
        -pointsize 24 `
        -fill "#cccccc" `
        -annotate +0+60 "Product image coming soon" `
        $imagePath
    
    Write-Host "✓ Placeholder created successfully!" -ForegroundColor Green
} else {
    Write-Host "⚠ ImageMagick not found. Downloading placeholder..." -ForegroundColor Yellow
    
    # Download a generic placeholder from placeholder service
    try {
        $placeholderUrl = "https://via.placeholder.com/800x800/f0f0f0/999999?text=No+Image+Available"
        Invoke-WebRequest -Uri $placeholderUrl -OutFile $imagePath -UseBasicParsing
        Write-Host "✓ Downloaded placeholder from placeholder.com" -ForegroundColor Green
    } catch {
        Write-Host "✗ Failed to download placeholder. Creating simple fallback..." -ForegroundColor Red
        
        # Create a simple text file as last resort
        "This is a placeholder. Replace with an actual image.`nCreate a 800x800 PNG image named 'no-product-image.png'" | Out-File -FilePath "$publicPath/README.txt"
        
        Write-Host "✗ Please manually create: $imagePath" -ForegroundColor Red
    }
}

Write-Host "`nPlaceholder path: $imagePath" -ForegroundColor Cyan
Write-Host "URL will be: http://localhost:8000/images/no-product-image.png" -ForegroundColor Cyan
