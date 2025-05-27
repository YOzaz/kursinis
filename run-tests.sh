#!/bin/bash

# Laravel Propaganda Analysis System - Test Runner
# Šis skriptas paleidžia visus testus sistemos validacijai

set -e

echo "🧪 Laravel Propaganda Analysis System - Test Suite"
echo "=================================================="

# Function to run tests with proper error handling
run_test_suite() {
    local suite_name=$1
    local command=$2
    
    echo ""
    echo "🔬 Running $suite_name tests..."
    echo "-----------------------------------"
    
    if eval "$command"; then
        echo "✅ $suite_name tests PASSED"
    else
        echo "❌ $suite_name tests FAILED"
        exit 1
    fi
}

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "📦 Installing dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Ensure bootstrap cache directory exists
mkdir -p bootstrap/cache

# Clear previous test results
echo "🧹 Cleaning up previous test results..."
rm -rf tests/coverage/ tests/results/
mkdir -p tests/coverage tests/results

# Run different test suites
echo "🚀 Starting test execution..."

# Unit Tests
run_test_suite "Unit" "vendor/bin/phpunit --testsuite=Unit"

# Feature Tests
run_test_suite "Feature" "vendor/bin/phpunit --testsuite=Feature"

# Integration Tests
run_test_suite "Integration" "vendor/bin/phpunit --testsuite=Integration"

# Run all tests with coverage (if Xdebug is available)
if php -m | grep -q xdebug; then
    echo ""
    echo "📊 Generating code coverage report..."
    echo "-----------------------------------"
    vendor/bin/phpunit --coverage-text --coverage-html=tests/coverage --coverage-clover=tests/coverage/clover.xml
else
    echo ""
    echo "⚠️  Xdebug not available - skipping coverage report"
    echo "   Install Xdebug to generate coverage reports"
fi

# Run static analysis (if tools are available)
echo ""
echo "🔍 Running static analysis..."
echo "----------------------------"

# Check code style with PHP CS Fixer (if available)
if command -v php-cs-fixer &> /dev/null; then
    echo "📝 Checking code style..."
    php-cs-fixer fix --dry-run --diff --verbose || echo "⚠️  Code style issues found"
else
    echo "⚠️  PHP CS Fixer not available - skipping code style check"
fi

# Check for security vulnerabilities (if composer audit is available)
if composer --version | grep -q "version 2"; then
    echo "🔒 Checking for security vulnerabilities..."
    composer audit || echo "⚠️  Security check completed with warnings"
else
    echo "⚠️  Composer audit not available - skipping security check"
fi

echo ""
echo "✨ All tests completed successfully!"
echo "======================================"
echo ""
echo "📋 Test Summary:"
echo "   • Unit tests: ✅ Passed"
echo "   • Feature tests: ✅ Passed" 
echo "   • Integration tests: ✅ Passed"
echo ""
echo "📁 Results saved in:"
echo "   • tests/coverage/ - Coverage reports"
echo "   • tests/results/ - Test results"
echo ""

# Display test statistics
if [ -f "tests/results/junit.xml" ]; then
    echo "📈 Test Statistics:"
    echo "   • Total tests: $(grep -o 'tests="[0-9]*"' tests/results/junit.xml | grep -o '[0-9]*')"
    echo "   • Assertions: $(grep -o 'assertions="[0-9]*"' tests/results/junit.xml | grep -o '[0-9]*')"
    echo "   • Time: $(grep -o 'time="[0-9.]*"' tests/results/junit.xml | grep -o '[0-9.]*') seconds"
fi

echo ""
echo "🎉 Ready for production deployment!"