#!/bin/bash
# Quick test setup script for the extensibility feature

echo "Setting up test environment for performance-review extensibility..."

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if we're in a Magento directory
if [ ! -f "app/etc/env.php" ]; then
    echo -e "${RED}Error: This doesn't appear to be a Magento root directory${NC}"
    echo "Please run this script from your Magento installation root"
    exit 1
fi

# Create test configuration
echo -e "${YELLOW}Creating test configuration...${NC}"

cat > app/etc/n98-magerun2.yaml << 'EOF'
# Test configuration for performance-review extensibility
autoloaders_psr4:
  # Point to the examples directory in the module
  MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'
  # Test analyzer directory
  TestAnalyzers\: './test-analyzers'

commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      # Example analyzers
      custom:
        - id: redis-memory
          class: 'MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer'
          description: 'Check Redis memory usage and fragmentation'
          category: redis
          config:
            fragmentation_threshold: 1.2
            memory_limit_mb: 512
        
        - id: elasticsearch-health
          class: 'MyCompany\PerformanceAnalyzer\ElasticsearchHealthAnalyzer'
          description: 'Check Elasticsearch cluster health'
          category: search
      
      # Test analyzer
      test:
        - id: simple-test
          class: 'TestAnalyzers\SimpleTestAnalyzer'
          description: 'Simple test to verify extensibility works'
          category: test
EOF

echo -e "${GREEN}✓ Created app/etc/n98-magerun2.yaml${NC}"

# Create test analyzer directory
echo -e "${YELLOW}Creating test analyzer...${NC}"
mkdir -p test-analyzers

cat > test-analyzers/SimpleTestAnalyzer.php << 'EOF'
<?php
declare(strict_types=1);

namespace TestAnalyzers;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Model\Issue\Collection;

/**
 * Simple test analyzer to verify extensibility
 */
class SimpleTestAnalyzer implements AnalyzerCheckInterface
{
    public function analyze(Collection $results): void
    {
        // Always create a success message
        $results->createIssue()
            ->setPriority('low')
            ->setCategory('Test')
            ->setIssue('Extensibility test successful')
            ->setDetails(
                'This issue confirms that custom analyzers are loading correctly. ' .
                'The extensibility feature is working as expected!'
            )
            ->setCurrentValue('Custom analyzer loaded')
            ->setRecommendedValue('No action needed')
            ->add();
        
        // Check PHP version as a real test
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '8.2.0', '<')) {
            $results->createIssue()
                ->setPriority('low')
                ->setCategory('Test')
                ->setIssue('PHP version check from custom analyzer')
                ->setDetails('Your PHP version was checked by the custom analyzer')
                ->setCurrentValue($phpVersion)
                ->setRecommendedValue('8.2.0 or higher')
                ->add();
        }
    }
}
EOF

echo -e "${GREEN}✓ Created test-analyzers/SimpleTestAnalyzer.php${NC}"

# Display test commands
echo -e "\n${GREEN}Setup complete! Here are some commands to test:${NC}\n"

echo "1. List all analyzers (should include your custom ones):"
echo -e "${YELLOW}   n98-magerun2.phar performance:review --list-analyzers${NC}\n"

echo "2. Run only the test analyzer:"
echo -e "${YELLOW}   n98-magerun2.phar performance:review --category=test${NC}\n"

echo "3. Run with verbose output:"
echo -e "${YELLOW}   n98-magerun2.phar performance:review -v --category=test${NC}\n"

echo "4. Skip specific analyzers:"
echo -e "${YELLOW}   n98-magerun2.phar performance:review --skip-analyzer=database --skip-analyzer=modules${NC}\n"

echo "5. Run all analyzers including custom ones:"
echo -e "${YELLOW}   n98-magerun2.phar performance:review${NC}\n"

echo -e "${GREEN}Files created:${NC}"
echo "  - app/etc/n98-magerun2.yaml (configuration)"
echo "  - test-analyzers/SimpleTestAnalyzer.php (test analyzer)"

echo -e "\n${YELLOW}To see the test analyzer in action, run:${NC}"
echo "n98-magerun2.phar performance:review --category=test"