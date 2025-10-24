#!/bin/bash
# Digital GYM - Installation Test Script

echo "🏋️‍♂️ Digital GYM - Installation Test"
echo "======================================"
echo ""

# Check PHP
echo "1. Checking PHP version..."
php -v | head -n 1
if [ $? -eq 0 ]; then
    echo "   ✓ PHP is installed"
else
    echo "   ✗ PHP is not installed"
    exit 1
fi
echo ""

# Check PHP syntax
echo "2. Checking PHP syntax..."
php_errors=0
for file in $(find . -name "*.php" -not -path './.git/*'); do
    php -l "$file" > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "   ✗ Syntax error in: $file"
        php_errors=1
    fi
done

if [ $php_errors -eq 0 ]; then
    echo "   ✓ All PHP files have valid syntax"
else
    echo "   ✗ Some PHP files have syntax errors"
    exit 1
fi
echo ""

# Check required directories
echo "3. Checking required directories..."
dirs=("css" "js" "includes" "pages" "uploads")
for dir in "${dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo "   ✓ $dir/ exists"
    else
        echo "   ✗ $dir/ is missing"
        exit 1
    fi
done
echo ""

# Check required files
echo "4. Checking required files..."
files=("database.sql" "index.php" "login.php" "README.md" "includes/config.php" "includes/functions.php")
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✓ $file exists"
    else
        echo "   ✗ $file is missing"
        exit 1
    fi
done
echo ""

# Check permissions
echo "5. Checking directory permissions..."
if [ -w "uploads" ]; then
    echo "   ✓ uploads/ is writable"
else
    echo "   ⚠ uploads/ might not be writable"
fi
echo ""

echo "======================================"
echo "✓ Installation test completed successfully!"
echo ""
echo "Next steps:"
echo "1. Import database.sql into MySQL"
echo "2. Configure includes/config.php"
echo "3. Access http://localhost/DigitalGYM"
echo "4. Login with username: admin, password: admin123"
echo ""
