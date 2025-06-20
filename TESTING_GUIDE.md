# Testing the Extensibility Feature

This guide shows how to test the new custom analyzer functionality.

## Prerequisites

1. Ensure you're on the feature branch:
```bash
git checkout feature/1-extensible-analyzers
```

2. Make sure n98-magerun2 is installed and the module is in the correct location:
```bash
~/.n98-magerun2/modules/performance-review/
```

## Testing Core Functionality

### 1. Test the New Options

#### List All Analyzers
```bash
# This should show all 11 core analyzers
n98-magerun2.phar performance:review --list-analyzers
```

Expected output:
```
Available Performance Analyzers:

ID              | Name                        | Category   | Description
----------------|-----------------------------|-----------|---------------------------------
configuration   | Configuration Analysis      | config     | Check application mode, cache...
database        | Database Analysis           | database   | Analyze database size, table...
modules         | Module Analysis             | modules    | Check installed modules...
...
```

#### Skip Specific Analyzers
```bash
# Run review but skip Redis and API analyzers
n98-magerun2.phar performance:review --skip-analyzer=redis --skip-analyzer=api
```

You should see all analyzers run except Redis and API.

## Testing Custom Analyzers

### 1. Set Up Example Analyzers

Create a test configuration file in your Magento root:

```bash
# Navigate to your Magento installation
cd /path/to/magento

# Create the configuration file
cat > app/etc/n98-magerun2.yaml << 'EOF'
# Register the examples namespace
autoloaders_psr4:
  MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'

commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
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
EOF
```

### 2. Verify Custom Analyzers Are Loaded

```bash
# List analyzers again - you should see your custom ones
n98-magerun2.phar performance:review --list-analyzers | grep -E "(redis-memory|elasticsearch)"
```

Expected output:
```
redis-memory    | Check Redis memory usage... | redis      | Check Redis memory usage and...
elasticsearch-health | Check Elasticsearch...  | search     | Check Elasticsearch cluster...
```

### 3. Run Only Custom Analyzers

```bash
# Run only the custom category
n98-magerun2.phar performance:review --category=custom
```

### 4. Test with Verbose Output

```bash
# See detailed information about analyzer loading
n98-magerun2.phar performance:review -v
```

## Creating a Simple Test Analyzer

### 1. Create Your Own Test Analyzer

```bash
# Create a test analyzer directory
mkdir -p ~/test-analyzers

# Create a simple analyzer
cat > ~/test-analyzers/TestAnalyzer.php << 'EOF'
<?php
namespace TestAnalyzers;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Model\Issue\Collection;

class TestAnalyzer implements AnalyzerCheckInterface
{
    public function analyze(Collection $results): void
    {
        // Always create a test issue
        $results->createIssue()
            ->setPriority('low')
            ->setCategory('Test')
            ->setIssue('Test analyzer is working')
            ->setDetails('This confirms custom analyzers are loading correctly')
            ->setCurrentValue('Test successful')
            ->setRecommendedValue('No action needed')
            ->add();
    }
}
EOF
```

### 2. Register the Test Analyzer

Add to your `app/etc/n98-magerun2.yaml`:

```yaml
autoloaders_psr4:
  TestAnalyzers\: '~/test-analyzers'

commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      test:
        - id: test-analyzer
          class: 'TestAnalyzers\TestAnalyzer'
          description: 'Simple test analyzer'
```

### 3. Run and Verify

```bash
# Run the test analyzer
n98-magerun2.phar performance:review --category=test

# Or run all and look for test output
n98-magerun2.phar performance:review | grep -A5 "Test analyzer"
```

## Testing Redis Memory Analyzer

If you have Redis configured:

### 1. Check Redis is Running
```bash
redis-cli ping
# Should return: PONG
```

### 2. Create Some Test Data
```bash
# Add test data to Redis
redis-cli
> SET test:key1 "value1"
> SET test:key2 "value2"
> INFO memory
> exit
```

### 3. Run the Analyzer
```bash
n98-magerun2.phar performance:review --category=redis
```

Look for output like:
- "High Redis memory fragmentation" (if fragmentation > threshold)
- "Redis memory usage exceeds limit" (if usage > configured limit)
- "Redis is evicting keys" (if evictions detected)

## Testing Configuration Options

### 1. Test Analyzer Configuration

Modify the config values in your YAML:

```yaml
analyzers:
  custom:
    - id: redis-memory
      config:
        fragmentation_threshold: 1.0  # Very strict - likely to trigger
        memory_limit_mb: 1           # Very low - likely to trigger
```

Run again and see if different issues are reported.

### 2. Test Disabling Core Analyzers

```yaml
analyzers:
  core:
    database:
      enabled: false
    modules:
      enabled: false
```

Run and verify those analyzers don't execute.

## Debugging Issues

### 1. Analyzer Not Loading

```bash
# Check with verbose flag
n98-magerun2.phar performance:review -v --list-analyzers

# Check your YAML syntax
php -r "print_r(yaml_parse_file('app/etc/n98-magerun2.yaml'));"
```

### 2. Class Not Found

```bash
# Verify autoloader path is correct
n98-magerun2.phar performance:review -v 2>&1 | grep "not found"

# Check file permissions
ls -la ~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers/
```

### 3. No Output from Analyzer

Add debug output to your analyzer:

```php
public function analyze(Collection $results): void
{
    echo "DEBUG: Analyzer is running\n";
    // ... rest of code
}
```

## Expected Test Results

### Successful Test Checklist

- [ ] `--list-analyzers` shows both core and custom analyzers
- [ ] `--skip-analyzer` successfully skips specified analyzers
- [ ] Custom analyzers appear in the analyzer list
- [ ] Custom analyzers execute and produce issues
- [ ] Configuration values are respected
- [ ] Verbose mode shows analyzer loading information
- [ ] Category filtering works with custom categories
- [ ] Core analyzers can be disabled via configuration

### Sample Output

```
Starting Magento 2 Performance Review...

Running Configuration Analysis... ✓
Running Database Analysis... ✓
Running Redis Memory Analyzer... ✓
Running Test analyzer is working... ✓

== Test ==
--------------------------------------------------------------------------------
Priority   | Recommendation                           | Details                  
----------+------------------------------------------+--------------------------
Low        | Test analyzer is working                 | This confirms custom...
           |                                          | Current: Test successful
           |                                          | Recommended: No action...
----------+------------------------------------------+--------------------------

================================================================================
SUMMARY: Found 1 issues (0 high, 0 medium, 1 low)
================================================================================
```

## Next Steps

1. Create your own analyzer for project-specific checks
2. Share useful analyzers with the community
3. Report any issues with the extensibility system