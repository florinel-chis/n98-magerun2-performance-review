# YAML Configuration Loading - Step by Step

## The Flow: How Everything Works Together

```
1. You run: n98-magerun2.phar performance:review
                    ↓
2. n98-magerun2 loads YAML files in order:
   - /etc/n98-magerun2.yaml
   - ~/.n98-magerun2.yaml  
   - /path/to/magento/app/etc/n98-magerun2.yaml  ← YOUR CONFIG
   - /path/to/magento/n98-magerun2.yaml
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

1. **Your Configuration** (`~/fch/magento248/app/etc/n98-magerun2.yaml`):
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

### Option 1: Copy and Modify Example

1. Copy the example config:
```bash
cp ~/.n98-magerun2/modules/performance-review/examples/n98-magerun2.yaml.example ~/fch/magento248/app/etc/n98-magerun2.yaml
```

2. Edit the autoloader path:
```bash
# Change this line in the copied file:
# MyCompany\PerformanceAnalyzer\: '/path/to/your/custom/analyzers'
# To:
# MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'
```

3. Test:
```bash
n98-magerun2.phar --root-dir ~/fch/magento248 performance:review --list-analyzers
```

### Option 2: Use the Demo Script

```bash
~/.n98-magerun2/modules/performance-review/docs/scripts/demo-example-analyzers.sh
```

This script:
- Creates the correct configuration
- Tests if it loads
- Shows you exactly what happens

## Configuration Locations (in order)

1. **System**: `/etc/n98-magerun2.yaml` (affects all users)
2. **User**: `~/.n98-magerun2.yaml` (affects just you)
3. **Project**: `~/fch/magento248/app/etc/n98-magerun2.yaml` (affects just this project) ← **Recommended**
4. **Project Root**: `~/fch/magento248/n98-magerun2.yaml` (alternative)

**Best Practice**: Use project-level configuration so each Magento project can have different analyzers.

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

### Step 1: Basic Test
```bash
# This should NOT show "API Analysis" if config is loading
n98-magerun2.phar --root-dir ~/fch/magento248 performance:review | grep "API Analysis"
```

### Step 2: List Test
```bash
# This should show your custom analyzers
n98-magerun2.phar --root-dir ~/fch/magento248 performance:review --list-analyzers | grep -i redis
```

### Step 3: Run Test
```bash
# This should execute your analyzer
n98-magerun2.phar --root-dir ~/fch/magento248 performance:review --category=custom
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

The key insight: **You don't call your analyzer directly**. The Performance Review Command finds it through the YAML configuration and calls it for you.