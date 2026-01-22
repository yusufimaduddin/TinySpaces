#!/bin/bash
# TinySpaces Setup Verification Script

echo "🔍 TinySpaces Setup Verification"
echo "================================="
echo ""

# Check PHP version
echo "✓ Checking PHP version..."
if command -v php &> /dev/null; then
    php_version=$(php -v | head -n 1)
    echo "  ✓ PHP installed: $php_version"
else
    echo "  ✗ PHP is not installed"
fi

echo ""
echo "✓ Checking Node.js..."
if command -v node &> /dev/null; then
    node_version=$(node -v)
    echo "  ✓ Node.js installed: $node_version"
else
    echo "  ✗ Node.js is not installed"
fi

echo ""
echo "✓ Checking npm..."
if command -v npm &> /dev/null; then
    npm_version=$(npm -v)
    echo "  ✓ npm installed: v$npm_version"
else
    echo "  ✗ npm is not installed"
fi

echo ""
echo "✓ Checking directory structure..."
directories=(
    "app/controllers"
    "app/models"
    "app/views"
    "public/css"
    "public/js"
    "public/uploads"
    "storage"
    "tmp"
)

for dir in "${directories[@]}"; do
    if [ -d "$dir" ]; then
        echo "  ✓ $dir exists"
    else
        echo "  ✗ $dir is missing"
    fi
done

echo ""
echo "✓ Checking files..."
files=(
    "index.php"
    "app/database.php"
    "app/controllers/AuthController.php"
    "app/models/User.php"
    "public/js/app.js"
    "package.json"
)

for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file exists"
    else
        echo "  ✗ $file is missing"
    fi
done

echo ""
echo "✓ Checking permissions..."
if [ -w "storage" ]; then
    echo "  ✓ storage/ is writable"
else
    echo "  ✗ storage/ is not writable (run: chmod 755 storage)"
fi

if [ -w "public/uploads" ]; then
    echo "  ✓ public/uploads/ is writable"
else
    echo "  ✗ public/uploads/ is not writable (run: chmod 755 public/uploads)"
fi

if [ -w "tmp" ]; then
    echo "  ✓ tmp/ is writable"
else
    echo "  ✗ tmp/ is not writable (run: chmod 755 tmp)"
fi

echo ""
echo "================================="
echo "Setup verification complete!"
echo ""
echo "Next steps:"
echo "1. npm install (install dependencies)"
echo "2. npm run build (compile CSS)"
echo "3. Start your web server"
echo "4. Visit http://localhost/tinyspace"
