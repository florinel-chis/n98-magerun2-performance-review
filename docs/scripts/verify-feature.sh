#!/bin/bash
# Quick verification script

echo "Verifying extensibility features..."
echo ""

# Test 1: List analyzers
echo "1. Testing --list-analyzers option:"
~/fch/n98-magerun2/n98-magerun2.phar --root-dir ~/fch/magento248/ performance:review --list-analyzers

echo ""
echo "2. Testing --skip-analyzer option:"
echo "Running: performance:review --skip-analyzer=database --skip-analyzer=modules"
~/fch/n98-magerun2/n98-magerun2.phar --root-dir ~/fch/magento248/ performance:review --skip-analyzer=database --skip-analyzer=modules 2>&1 | grep -E "(Checking|Analyzing)" | head -10

echo ""
echo "You should NOT see 'Analyzing database' or 'Checking modules' in the output above."