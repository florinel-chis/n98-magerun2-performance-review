# Quick Test Instructions

## 5-Minute Test

### 1. Navigate to any Magento installation
```bash
cd /path/to/your/magento
```

### 2. Run the test setup script
```bash
~/.n98-magerun2/modules/performance-review/test-setup.sh
```

This creates:
- A test configuration file
- A simple test analyzer

### 3. Verify it works
```bash
# Should show "SimpleTestAnalyzer" in the list
n98-magerun2.phar performance:review --list-analyzers | grep simple-test

# Run the test analyzer
n98-magerun2.phar performance:review --category=test
```

### Expected Output
```
Starting Magento 2 Performance Review...

Running Simple test to verify extensibility works... ✓

== Test ==
--------------------------------------------------------------------------------
Priority   | Recommendation                           | Details                  
----------+------------------------------------------+--------------------------
Low        | Extensibility test successful            | This issue confirms that...
           |                                          | Current: Custom analyzer loaded
           |                                          | Recommended: No action needed
----------+------------------------------------------+--------------------------

================================================================================
SUMMARY: Found 1 issues (0 high, 0 medium, 1 low)
================================================================================
```

## Manual Test (Without Script)

### 1. Create a minimal test configuration

In your Magento root, create `app/etc/n98-magerun2.yaml`:

```yaml
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      # Disable a core analyzer as a test
      core:
        modules:
          enabled: false
```

### 2. Run and verify

```bash
# The modules analyzer should NOT run
n98-magerun2.phar performance:review | grep "Checking modules"
# Should return nothing

# List analyzers - modules should still appear but won't run
n98-magerun2.phar performance:review --list-analyzers
```

### 3. Test skip functionality (no config needed)

```bash
# Skip redis and api analyzers
n98-magerun2.phar performance:review --skip-analyzer=redis --skip-analyzer=api

# You should NOT see:
# "Checking Redis configuration..."
# "Checking API configuration..."
```

## Troubleshooting

### "Class not found" error
- Check the file path in the YAML is correct
- Verify the namespace matches the class declaration
- Use absolute paths or paths relative to Magento root

### Analyzer not appearing in list
- Check YAML syntax (indentation matters!)
- Verify the class implements `AnalyzerCheckInterface`
- Run with `-v` flag for debug info

### No output from analyzer
- Make sure `analyze()` method creates at least one issue
- Check for PHP errors: `php -l test-analyzers/SimpleTestAnalyzer.php`