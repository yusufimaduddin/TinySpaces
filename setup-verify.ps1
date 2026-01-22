# TinySpaces Setup Verification Script for Windows PowerShell

Write-Host "🔍 TinySpaces Setup Verification" -ForegroundColor Cyan
Write-Host "=================================" -ForegroundColor Cyan
Write-Host ""

# Check PHP version
Write-Host "✓ Checking PHP version..." -ForegroundColor Green
try {
    $phpVersion = php -v | Select-Object -First 1
    Write-Host "  ✓ PHP installed: $phpVersion" -ForegroundColor Green
}
catch {
    Write-Host "  ✗ PHP is not installed" -ForegroundColor Red
}

Write-Host ""
Write-Host "✓ Checking Node.js..." -ForegroundColor Green
try {
    $nodeVersion = node -v
    Write-Host "  ✓ Node.js installed: $nodeVersion" -ForegroundColor Green
}
catch {
    Write-Host "  ✗ Node.js is not installed" -ForegroundColor Red
}

Write-Host ""
Write-Host "✓ Checking npm..." -ForegroundColor Green
try {
    $npmVersion = npm -v
    Write-Host "  ✓ npm installed: v$npmVersion" -ForegroundColor Green
}
catch {
    Write-Host "  ✗ npm is not installed" -ForegroundColor Red
}

Write-Host ""
Write-Host "✓ Checking directory structure..." -ForegroundColor Green
$directories = @(
    "app/controllers",
    "app/models",
    "app/views",
    "public/css",
    "public/js",
    "public/uploads",
    "storage",
    "tmp"
)

foreach ($dir in $directories) {
    if (Test-Path $dir) {
        Write-Host "  ✓ $dir exists" -ForegroundColor Green
    }
    else {
        Write-Host "  ✗ $dir is missing" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "✓ Checking files..." -ForegroundColor Green
$files = @(
    "index.php",
    "app/database.php",
    "app/controllers/AuthController.php",
    "app/models/User.php",
    "public/js/app.js",
    "package.json"
)

foreach ($file in $files) {
    if (Test-Path $file) {
        Write-Host "  ✓ $file exists" -ForegroundColor Green
    }
    else {
        Write-Host "  ✗ $file is missing" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "✓ Checking permissions..." -ForegroundColor Green
try {
    $acl = Get-Acl "storage"
    Write-Host "  ✓ storage/ is accessible" -ForegroundColor Green
}
catch {
    Write-Host "  ✗ storage/ has permission issues" -ForegroundColor Red
}

try {
    $acl = Get-Acl "public/uploads"
    Write-Host "  ✓ public/uploads/ is accessible" -ForegroundColor Green
}
catch {
    Write-Host "  ✗ public/uploads/ has permission issues" -ForegroundColor Red
}

try {
    $acl = Get-Acl "tmp"
    Write-Host "  ✓ tmp/ is accessible" -ForegroundColor Green
}
catch {
    Write-Host "  ✗ tmp/ has permission issues" -ForegroundColor Red
}

Write-Host ""
Write-Host "=================================" -ForegroundColor Cyan
Write-Host "Setup verification complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. npm install (install dependencies)" -ForegroundColor White
Write-Host "2. npm run build (compile CSS)" -ForegroundColor White
Write-Host "3. Start your web server (Laragon, XAMPP, or php -S localhost:8000)" -ForegroundColor White
Write-Host "4. Visit http://localhost/tinyspace" -ForegroundColor White
