#!/bin/bash
# Quick verification script
# Usage: ./verify-feature.sh <magento-root>

if [ -z "$1" ]; then
    echo "Error: Please provide Magento root directory"
    echo "Usage: $0 <magento-root>"
    exit 1
fi

MAGENTO_ROOT="$1"

echo "Verifying extensibility features..."
echo ""

# Test 1: List analyzers
echo "1. Testing --list-analyzers option:"
n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review --list-analyzers

echo ""
echo "2. Testing --skip-analyzer option:"
echo "Running: performance:review --skip-analyzer=database --skip-analyzer=modules"
n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review --skip-analyzer=database --skip-analyzer=modules 2>&1 | grep -E "(Checking|Analyzing)" | head -10

echo ""
echo "You should NOT see 'Analyzing database' or 'Checking modules' in the output above."