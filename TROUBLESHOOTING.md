# Troubleshooting Custom Analyzers

## The Issue
Custom analyzers are not running even though the configuration appears correct.

## Root Causes and Solutions

### 1. Configuration File Location

n98-magerun2 loads configuration files in this order:
1. `/etc/n98-magerun2.yaml` (system)
2. `~/.n98-magerun2.yaml` (user home)
3. `<magento_root>/app/etc/n98-magerun2.yaml` (project)
4. `<magento_root>/n98-magerun2.yaml` (project root)

**Solution**: Run the diagnostic script to check which locations are being checked:
```bash
cd ~/fch/magento248
php ~/.n98-magerun2/modules/performance-review/diagnose.php
```

### 2. YAML Syntax Issues

The YAML format is very sensitive to:
- Indentation (must use spaces, not tabs)
- Special characters in strings
- Proper escaping of backslashes

**Common mistake**:
```yaml
# Wrong - missing backslash escaping
commands:
  PerformanceReview\Command\PerformanceReviewCommand:

# Correct - escaped backslashes
commands:
  PerformanceReview\\Command\\PerformanceReviewCommand:
```

### 3. Autoloader Path Issues

Paths in the autoloader configuration must be:
- Absolute paths, OR
- Relative to the Magento root directory
- Properly resolved (~ is not always expanded)

**Solution**: Use absolute paths or paths relative to Magento root:
```yaml
autoloaders_psr4:
  # Relative to Magento root
  TestAnalyzers\\: 'app/code/TestAnalyzers'
  
  # Absolute path (more reliable)
  MyCompany\\Analyzer\\: '/home/user/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'
```

### 4. Configuration Not Loading

Sometimes the configuration is not loaded due to:
- Cache issues
- Wrong configuration key
- Module not recognizing the config structure

**Quick Test**: Disable a core analyzer to verify config loads:
```yaml
commands:
  PerformanceReview\\Command\\PerformanceReviewCommand:
    analyzers:
      core:
        api:
          enabled: false
```

If API analyzer still runs, configuration is not being loaded.

## Step-by-Step Fix

### Step 1: Run the Fix Script
```bash
cd ~/fch/magento248
~/.n98-magerun2/modules/performance-review/fix-custom-analyzers.sh
```

This script will:
1. Test if configuration loading works
2. Create a working test analyzer
3. Set up proper configuration
4. Clear caches

### Step 2: Manual Configuration

If the script doesn't work, create this minimal configuration manually:

```bash
cat > ~/fch/magento248/app/etc/n98-magerun2.yaml << 'EOF'
commands:
  PerformanceReview\\Command\\PerformanceReviewCommand:
    analyzers:
      core:
        api:
          enabled: false
EOF
```

Then verify:
```bash
~/fch/n98-magerun2/n98-magerun2.phar --root-dir ~/fch/magento248 performance:review | grep "API Analysis"
```

If you don't see "API Analysis", the config is loading correctly.

### Step 3: Add Custom Analyzer

Once configuration loading is confirmed, add a simple test analyzer:

```bash
# Create test analyzer
mkdir -p ~/fch/magento248/app/code/Test
cat > ~/fch/magento248/app/code/Test/SimpleAnalyzer.php << 'EOF'
<?php
namespace Test;
use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Model\Issue\Collection;

class SimpleAnalyzer implements AnalyzerCheckInterface {
    public function analyze(Collection $results): void {
        $results->createIssue()
            ->setPriority('low')
            ->setCategory('Test')
            ->setIssue('Test analyzer works!')
            ->setDetails('Success!')
            ->add();
    }
}
EOF

# Update configuration
cat > ~/fch/magento248/app/etc/n98-magerun2.yaml << 'EOF'
autoloaders_psr4:
  Test\\: 'app/code/Test'

commands:
  PerformanceReview\\Command\\PerformanceReviewCommand:
    analyzers:
      test:
        - id: test-simple
          class: 'Test\\SimpleAnalyzer'
          description: 'Simple test'
EOF
```

### Step 4: Clear Caches and Test
```bash
rm -rf ~/.n98-magerun2/cache/*
~/fch/n98-magerun2/n98-magerun2.phar --root-dir ~/fch/magento248 performance:review --list-analyzers | grep test-simple
~/fch/n98-magerun2/n98-magerun2.phar --root-dir ~/fch/magento248 performance:review --category=test
```

## Debug Mode

Run with maximum verbosity to see what's happening:
```bash
~/fch/n98-magerun2/n98-magerun2.phar --root-dir ~/fch/magento248 performance:review -vvv 2>&1 | less
```

Look for:
- Configuration loading messages
- Autoloader registration
- Class loading errors

## Alternative: Global Configuration

If project-level config doesn't work, try user-level:

```bash
# Create global config
cat > ~/.n98-magerun2.yaml << 'EOF'
autoloaders_psr4:
  MyAnalyzers\\: '~/.n98-magerun2/my-analyzers'

commands:
  PerformanceReview\\Command\\PerformanceReviewCommand:
    analyzers:
      custom:
        - id: my-test
          class: 'MyAnalyzers\\TestAnalyzer'
          description: 'My test analyzer'
EOF

# Create analyzer
mkdir -p ~/.n98-magerun2/my-analyzers
# ... create TestAnalyzer.php ...
```

## Common Error Messages

### "Class not found"
- Check namespace matches exactly
- Verify autoloader path is correct
- Ensure file name matches class name

### "Option does not exist"
- Module might be using old version
- Run: `~/.n98-magerun2/modules/performance-review/install-feature.sh`

### No custom analyzers in list
- Configuration not loading
- Try different config file location
- Check YAML syntax

## Still Not Working?

1. Check PHP syntax:
```bash
php -l app/code/Test/SimpleAnalyzer.php
```

2. Verify interface exists:
```bash
ls ~/.n98-magerun2/modules/performance-review/src/PerformanceReview/Api/
```

3. Check if it's a path resolution issue:
```bash
cd ~/fch/magento248
pwd  # Should show full path
# Use this full path in autoloader config instead of relative paths
```

4. Try the diagnostic script:
```bash
php ~/.n98-magerun2/modules/performance-review/diagnose.php
```