#!/bin/bash
# Debug script for custom analyzer issues

echo "Debugging Custom Analyzer Setup..."
echo "================================="

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Check where we are
MAGENTO_ROOT="${1:-~/fch/magento248}"
echo -e "\n${YELLOW}1. Checking Magento root:${NC} $MAGENTO_ROOT"

# Check if n98-magerun2.yaml exists
echo -e "\n${YELLOW}2. Looking for configuration files:${NC}"
if [ -f "$MAGENTO_ROOT/app/etc/n98-magerun2.yaml" ]; then
    echo -e "${GREEN}✓${NC} Found: $MAGENTO_ROOT/app/etc/n98-magerun2.yaml"
    echo -e "\n${YELLOW}Contents:${NC}"
    cat "$MAGENTO_ROOT/app/etc/n98-magerun2.yaml" | head -20
else
    echo -e "${RED}✗${NC} Not found: $MAGENTO_ROOT/app/etc/n98-magerun2.yaml"
fi

if [ -f "$MAGENTO_ROOT/n98-magerun2.yaml" ]; then
    echo -e "${GREEN}✓${NC} Found: $MAGENTO_ROOT/n98-magerun2.yaml"
    echo -e "\n${YELLOW}Contents:${NC}"
    cat "$MAGENTO_ROOT/n98-magerun2.yaml" | head -20
else
    echo -e "${RED}✗${NC} Not found: $MAGENTO_ROOT/n98-magerun2.yaml"
fi

# Check home directory config
if [ -f ~/.n98-magerun2.yaml ]; then
    echo -e "${GREEN}✓${NC} Found: ~/.n98-magerun2.yaml"
    echo -e "\n${YELLOW}Contents:${NC}"
    cat ~/.n98-magerun2.yaml | head -20
else
    echo -e "${RED}✗${NC} Not found: ~/.n98-magerun2.yaml"
fi

# Check if example analyzers exist
echo -e "\n${YELLOW}3. Checking example analyzer files:${NC}"
EXAMPLE_DIR=~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers
if [ -d "$EXAMPLE_DIR" ]; then
    echo -e "${GREEN}✓${NC} Example directory exists"
    ls -la "$EXAMPLE_DIR"
else
    echo -e "${RED}✗${NC} Example directory not found: $EXAMPLE_DIR"
fi

# Test with verbose mode
echo -e "\n${YELLOW}4. Running with verbose mode to see loading details:${NC}"
echo "Command: ~/fch/n98-magerun2/n98-magerun2.phar --root-dir $MAGENTO_ROOT performance:review --list-analyzers -vvv"
~/fch/n98-magerun2/n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review --list-analyzers -vvv 2>&1 | grep -E "(Loading|custom|Custom|analyzer|config)" | head -20

# Check if custom analyzers are in the list
echo -e "\n${YELLOW}5. Checking if custom analyzers appear in list:${NC}"
~/fch/n98-magerun2/n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review --list-analyzers 2>&1 | grep -E "(redis-memory|elasticsearch|simple-test|custom)"

# Create a minimal test config
echo -e "\n${YELLOW}6. Creating minimal test configuration:${NC}"
cat > "$MAGENTO_ROOT/app/etc/n98-magerun2.yaml" << 'EOF'
# Minimal test configuration
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      # Test by disabling a core analyzer
      core:
        api:
          enabled: false
EOF

echo -e "${GREEN}✓${NC} Created minimal test config"
echo "Testing if API analyzer is disabled..."
~/fch/n98-magerun2/n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review 2>&1 | grep -c "API Analysis"
echo "If the count above is 0, configuration is being loaded correctly."

echo -e "\n${YELLOW}Recommendations:${NC}"
echo "1. Make sure n98-magerun2.yaml is in the correct location"
echo "2. Check YAML syntax (indentation matters!)"
echo "3. Verify class paths are correct"
echo "4. Try clearing n98-magerun2 cache: rm -rf ~/.n98-magerun2/cache/*"