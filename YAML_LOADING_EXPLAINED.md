# YAML Configuration Loading - Step by Step (Verified)

**REALITY CHECK**: Based on actual testing, most config files don't exist by default. This guide shows what actually works.

## The Flow: How Everything Works Together

```
1. You run: n98-magerun2.phar performance:review
                    ↓
2. n98-magerun2 looks for YAML files in order (only loads files that exist):
   - Built-in config.yaml (always present)
   - /etc/n98-magerun2.yaml (doesn't exist by default)
   - ~/.n98-magerun2.yaml (doesn't exist by default)  
   - <magento-root>/app/etc/n98-magerun2.yaml  ← CREATE THIS FILE
                    ↓
3. Registers autoloaders from YAML:
   autoloaders_psr4:
     MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'
                    ↓
4. Performance Review Command starts:
   - Loads its configuration from YAML
   - Finds: commands → PerformanceReview\Command\PerformanceReviewCommand → analyzers
                    ↓
5. For each configured analyzer:
   - class: 'MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer'
   - PHP autoloader finds: ~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers/RedisMemoryAnalyzer.php
   - Creates new instance: new RedisMemoryAnalyzer()
   - Calls: $analyzer->analyze($issueCollection)
                    ↓
6. Your custom analyzer runs and adds issues to the collection
                    ↓
7. Report is generated with both core and custom analyzer results
```

## Real Example: Configuring the Redis Memory Analyzer

### The Files Involved

1. **Your Configuration** (`<magento-root>/app/etc/n98-magerun2.yaml`):
```yaml
autoloaders_psr4:
  MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'

commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      custom:
        - id: redis-memory
          class: 'MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer'
          description: 'Check Redis memory'
```

2. **The Analyzer Class** (`~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers/RedisMemoryAnalyzer.php`):
```php
<?php
namespace MyCompany\PerformanceAnalyzer;  // ← Matches YAML namespace

use PerformanceReview\Api\AnalyzerCheckInterface;

class RedisMemoryAnalyzer implements AnalyzerCheckInterface  // ← Matches YAML class
{
    public function analyze(Collection $results): void {
        // Your analyzer logic here
    }
}
```

### How They Connect

1. **YAML tells PHP where to look**: 
   - Namespace: `MyCompany\PerformanceAnalyzer\`
   - Directory: `~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers`

2. **PHP finds your class**:
   - Looking for: `MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer`
   - Converts to file path: `RedisMemoryAnalyzer.php` in the registered directory
   - Full path: `~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers/RedisMemoryAnalyzer.php`

3. **Performance Review Command loads it**:
   - Reads config: `class: 'MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer'`
   - Creates instance: `new MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer()`
   - Calls: `$analyzer->analyze($results)`

## Quick Setup for Examples

### Option 1: Use the Demo Script (Easiest)

```bash
~/.n98-magerun2/modules/performance-review/docs/scripts/demo-example-analyzers.sh
```

This script creates the configuration file with correct paths automatically.

### Option 2: Copy and Modify Example

1. Copy the example config:
```bash
cp ~/.n98-magerun2/modules/performance-review/examples/n98-magerun2.yaml.example <magento-root>/app/etc/n98-magerun2.yaml
```

2. Edit the autoloader path to point to the examples:
```bash
# The example file already has the correct path for the provided examples:
# MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'
```

3. Test:
```bash
n98-magerun2.phar --root-dir <magento-root> performance:review --list-analyzers
```

### Option 2: Use the Demo Script

```bash
~/.n98-magerun2/modules/performance-review/docs/scripts/demo-example-analyzers.sh <magento-root>
```

This script:
- Creates the correct configuration for your Magento installation
- Tests if it loads
- Shows you exactly what happens

## Configuration File Locations (in loading order)

These are the **supported locations** where n98-magerun2 will look for config files:

1. **System**: `/etc/n98-magerun2.yaml` (create manually if needed - affects all users)
2. **User**: `~/.n98-magerun2.yaml` (create manually if needed - affects just you)
3. **Project**: `<magento-root>/app/etc/n98-magerun2.yaml` ← **CREATE THIS ONE**
4. **Project Root**: `<magento-root>/n98-magerun2.yaml` (alternative)

**IMPORTANT**: None of these files exist by default except the module registration file.

**Best Practice**: Use project-level configuration (`app/etc/n98-magerun2.yaml`) so each Magento project can have different analyzers.

## Why It Might Not Work

### Common Issue #1: Wrong Path
```yaml
# ❌ Wrong - relative to current directory
autoloaders_psr4:
  MyCompany\PerformanceAnalyzer\: 'examples/CustomAnalyzers'

# ✅ Correct - absolute path
autoloaders_psr4:
  MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'
```

### Common Issue #2: Wrong Namespace
```yaml
# Your YAML says:
class: 'MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer'

# But your PHP file has:
namespace SomeOtherCompany\PerformanceAnalyzer;  # ❌ Doesn't match
```

### Common Issue #3: Wrong File Name
```yaml
# YAML expects:
class: 'MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer'

# So PHP looks for:
RedisMemoryAnalyzer.php  # Must match exactly
```

## Testing Your Configuration

### Step 1: List Test (Most Important)
```bash
# This should show your custom analyzers if config is working
n98-magerun2.phar --root-dir <magento-root> performance:review --list-analyzers
```

### Step 2: Run Test  
```bash
# Run only your custom analyzers to test them
n98-magerun2.phar --root-dir <magento-root> performance:review --skip-analyzer=configuration --skip-analyzer=database --skip-analyzer=modules --skip-analyzer=codebase --skip-analyzer=frontend --skip-analyzer=indexing --skip-analyzer=php --skip-analyzer=mysql --skip-analyzer=redis --skip-analyzer=api --skip-analyzer=thirdparty
```

## The Magic Behind the Scenes

When n98-magerun2 starts, it:

1. **Finds all YAML files** in the standard locations
2. **Merges them** (later files override earlier ones)
3. **Registers PSR-4 autoloaders** from the `autoloaders_psr4` section
4. **Stores configuration** for each command

When `PerformanceReviewCommand` runs, it:

1. **Asks n98-magerun2** for its configuration
2. **Reads the `analyzers` section**
3. **For each analyzer**, tries to create an instance using the `class` field
4. **PHP autoloader** finds your file using the namespace mapping
5. **Your analyzer runs** and creates issues

## Key Insights From Testing

1. **Only module registration exists by default** - all other config files must be created
2. **Configuration does work** - when you create the file, custom analyzers appear in `--list-analyzers`
3. **Use project-level config** - `<magento-root>/app/etc/n98-magerun2.yaml` is recommended
4. **Test with --list-analyzers first** - this proves your configuration is loading
5. **You don't call analyzers directly** - the Performance Review Command finds them via YAML config