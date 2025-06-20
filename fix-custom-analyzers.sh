#!/bin/bash
# Fix custom analyzer configuration issues

echo "Fixing Custom Analyzer Configuration..."
echo "======================================"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

MAGENTO_ROOT="${1:-$HOME/fch/magento248}"
MODULE_PATH="$HOME/.n98-magerun2/modules/performance-review"

echo -e "${YELLOW}Magento Root:${NC} $MAGENTO_ROOT"
echo -e "${YELLOW}Module Path:${NC} $MODULE_PATH"

# Step 1: Create a working configuration
echo -e "\n${YELLOW}Step 1: Creating working configuration...${NC}"

# First, let's test if config loading works at all
cat > "$MAGENTO_ROOT/app/etc/n98-magerun2.yaml" << EOF
# Test configuration - disabling API analyzer to verify config loads
commands:
  PerformanceReview\\Command\\PerformanceReviewCommand:
    analyzers:
      core:
        api:
          enabled: false
EOF

echo -e "${GREEN}✓${NC} Created test configuration"

# Test if config is loaded
echo -e "\n${YELLOW}Testing if configuration is loaded...${NC}"
API_COUNT=$(~/fch/n98-magerun2/n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review 2>&1 | grep -c "API Analysis")

if [ "$API_COUNT" -eq "0" ]; then
    echo -e "${GREEN}✓${NC} Configuration is being loaded correctly!"
    
    # Step 2: Now add custom analyzers
    echo -e "\n${YELLOW}Step 2: Adding custom analyzer configuration...${NC}"
    
    # Create a simple inline test analyzer first
    mkdir -p "$MAGENTO_ROOT/app/code/TestAnalyzers"
    
    cat > "$MAGENTO_ROOT/app/code/TestAnalyzers/SimpleAnalyzer.php" << 'ANALYZER_EOF'
<?php
declare(strict_types=1);

namespace TestAnalyzers;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Model\Issue\Collection;

class SimpleAnalyzer implements AnalyzerCheckInterface
{
    public function analyze(Collection $results): void
    {
        $results->createIssue()
            ->setPriority('low')
            ->setCategory('Test')
            ->setIssue('Custom analyzer is working!')
            ->setDetails('This confirms that custom analyzers are being loaded and executed correctly.')
            ->setCurrentValue('Working')
            ->setRecommendedValue('No action needed')
            ->add();
    }
}
ANALYZER_EOF

    echo -e "${GREEN}✓${NC} Created test analyzer at app/code/TestAnalyzers/SimpleAnalyzer.php"
    
    # Update configuration with custom analyzer
    cat > "$MAGENTO_ROOT/app/etc/n98-magerun2.yaml" << EOF
# Working configuration with custom analyzer
autoloaders_psr4:
  TestAnalyzers\\: 'app/code/TestAnalyzers'
  MyCompany\\PerformanceAnalyzer\\: '$MODULE_PATH/examples/CustomAnalyzers'

commands:
  PerformanceReview\\Command\\PerformanceReviewCommand:
    analyzers:
      # Disable API analyzer as a test
      core:
        api:
          enabled: false
      
      # Custom analyzers
      test:
        - id: simple-test
          class: 'TestAnalyzers\\SimpleAnalyzer'
          description: 'Test analyzer to verify extensibility'
          category: test
      
      custom:
        - id: redis-memory
          class: 'MyCompany\\PerformanceAnalyzer\\RedisMemoryAnalyzer'
          description: 'Check Redis memory usage'
          category: redis
          config:
            fragmentation_threshold: 1.5
            memory_limit_mb: 1024
EOF

    echo -e "${GREEN}✓${NC} Updated configuration with custom analyzers"
    
else
    echo -e "${RED}✗${NC} Configuration is NOT being loaded. API analyzer still runs."
    echo -e "${YELLOW}Trying alternative location...${NC}"
    
    # Try root directory instead
    cp "$MAGENTO_ROOT/app/etc/n98-magerun2.yaml" "$MAGENTO_ROOT/n98-magerun2.yaml"
    echo -e "${GREEN}✓${NC} Copied to $MAGENTO_ROOT/n98-magerun2.yaml"
fi

# Step 3: Clear any caches
echo -e "\n${YELLOW}Step 3: Clearing caches...${NC}"
rm -rf ~/.n98-magerun2/cache/* 2>/dev/null
echo -e "${GREEN}✓${NC} Cleared n98-magerun2 cache"

# Step 4: Test the setup
echo -e "\n${YELLOW}Step 4: Testing custom analyzers...${NC}"

echo -e "\n${YELLOW}Listing all analyzers:${NC}"
~/fch/n98-magerun2/n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review --list-analyzers | grep -E "(simple-test|redis-memory|api)"

echo -e "\n${YELLOW}Running test analyzer only:${NC}"
~/fch/n98-magerun2/n98-magerun2.phar --root-dir "$MAGENTO_ROOT" performance:review --category=test

echo -e "\n${GREEN}Setup complete!${NC}"
echo -e "\nIf you see 'Custom analyzer is working!' above, then extensibility is working correctly."
echo -e "\nTo run all analyzers including custom ones:"
echo -e "${YELLOW}~/fch/n98-magerun2/n98-magerun2.phar --root-dir $MAGENTO_ROOT performance:review${NC}"