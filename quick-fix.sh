#!/bin/bash
# Quick fix for custom analyzer issues

echo "Quick Custom Analyzer Fix"
echo "========================"

MAGENTO_ROOT="${1:-$HOME/fch/magento248}"
echo "Using Magento root: $MAGENTO_ROOT"

# Step 1: Test configuration loading by disabling a core analyzer
echo -e "\n1. Testing configuration loading..."

cat > "$MAGENTO_ROOT/app/etc/n98-magerun2.yaml" << 'EOF'
commands:
  PerformanceReview\\Command\\PerformanceReviewCommand:
    analyzers:
      core:
        modules:
          enabled: false
EOF

# Count how many analyzers run (should be 10, not 11)
COUNT=$(~/fch/n98-magerun2/n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review 2>&1 | grep -c "✓")
echo "Analyzers that ran: $COUNT"

if [ "$COUNT" -eq "10" ]; then
    echo "✅ SUCCESS: Configuration is loading! (modules analyzer was disabled)"
    echo -e "\n2. Now let's add a custom analyzer..."
    
    # Create the simplest possible analyzer
    mkdir -p "$MAGENTO_ROOT/TestAnalyzer"
    
    cat > "$MAGENTO_ROOT/TestAnalyzer/Test.php" << 'EOF'
<?php
namespace TestAnalyzer;
use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Model\Issue\Collection;
class Test implements AnalyzerCheckInterface {
    public function analyze(Collection $results): void {
        throw new \Exception("CUSTOM ANALYZER EXECUTED!");
    }
}
EOF

    # Update config
    cat > "$MAGENTO_ROOT/app/etc/n98-magerun2.yaml" << EOF
autoloaders_psr4:
  TestAnalyzer\\\\: 'TestAnalyzer'

commands:
  PerformanceReview\\\\Command\\\\PerformanceReviewCommand:
    analyzers:
      test:
        - id: quick-test
          class: 'TestAnalyzer\\\\Test'
          description: 'Quick test'
EOF

    echo -e "\n3. Running custom analyzer test..."
    echo "If you see an error 'CUSTOM ANALYZER EXECUTED!', it means custom analyzers work!"
    
    ~/fch/n98-magerun2/n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review --category=test 2>&1 | grep -E "(CUSTOM ANALYZER EXECUTED|Quick test)"
    
else
    echo "❌ FAIL: Configuration is NOT loading"
    echo ""
    echo "Try alternative location:"
    cp "$MAGENTO_ROOT/app/etc/n98-magerun2.yaml" "$MAGENTO_ROOT/n98-magerun2.yaml"
    echo "Copied to: $MAGENTO_ROOT/n98-magerun2.yaml"
    echo ""
    echo "Test again with: $0 $MAGENTO_ROOT"
fi

echo -e "\nClearing cache..."
rm -rf ~/.n98-magerun2/cache/*

echo -e "\nDone! Check the output above."