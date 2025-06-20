# How YAML Configuration Loading Works

This guide explains how n98-magerun2 loads configuration files and how custom analyzers are registered.

## Configuration Loading Order

n98-magerun2 loads configuration files in a specific order, with later files overriding earlier ones:

1. **Distribution Config** (built into n98-magerun2)
   - Core n98-magerun2 configuration

2. **System Config**: `/etc/n98-magerun2.yaml`
   - System-wide configuration for all users

3. **User Config**: `~/.n98-magerun2.yaml`  
   - Your personal configuration (e.g., `/Users/flo/.n98-magerun2.yaml`)

4. **Project Config** (in Magento root):
   - `app/etc/n98-magerun2.yaml` (recommended)
   - `n98-magerun2.yaml` (alternative)

5. **Module Configs**
   - From modules in `~/.n98-magerun2/modules/*/n98-magerun2.yaml`

## Step-by-Step: Configuring Example Analyzers

### Step 1: Choose Configuration Location

For testing, use your **Magento project** location:
```bash
cd ~/fch/magento248
mkdir -p app/etc
```

### Step 2: Create Configuration File

Create `app/etc/n98-magerun2.yaml`:

```yaml
# Register the namespace for the example analyzers
autoloaders_psr4:
  # This tells PHP where to find classes in the MyCompany\PerformanceAnalyzer namespace
  MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'

# Configure the performance review command
commands:
  # The command class we're configuring
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      # Create a group called "custom"
      custom:
        # First analyzer: Redis Memory
        - id: redis-memory
          class: 'MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer'
          description: 'Check Redis memory usage'
          category: redis
          config:
            fragmentation_threshold: 1.5
            memory_limit_mb: 512
```

### Step 3: How It Works

When you run `n98-magerun2.phar performance:review`:

1. **n98-magerun2 starts** and loads all YAML configs in order
2. **Autoloader registration**: The `autoloaders_psr4` section tells PHP:
   - When looking for `MyCompany\PerformanceAnalyzer\*` classes
   - Look in `~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers/`
3. **Command configuration**: The `commands` section:
   - Finds `PerformanceReview\Command\PerformanceReviewCommand`
   - Reads the `analyzers` configuration
   - Loads each analyzer based on the `class` field
4. **Analyzer execution**:
   - Creates instance of `MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer`
   - If it has `setConfig()`, passes the `config` values
   - Calls `analyze()` method

## Complete Working Example

Here's a complete configuration that works with the provided examples:

```yaml
# File: ~/fch/magento248/app/etc/n98-magerun2.yaml

# 1. Register namespaces (tells PHP where to find the classes)
autoloaders_psr4:
  # For the example analyzers
  MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'
  
  # For your own analyzers
  YourCompany\Analyzers\: 'app/code/YourCompany/Analyzers'

# 2. Configure the command
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      # Group: custom
      custom:
        - id: redis-memory
          class: 'MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer'
          description: 'Analyze Redis memory usage and fragmentation'
          category: redis
          config:
            fragmentation_threshold: 1.5
            memory_limit_mb: 1024
            
        - id: elasticsearch-health  
          class: 'MyCompany\PerformanceAnalyzer\ElasticsearchHealthAnalyzer'
          description: 'Check Elasticsearch cluster health'
          category: search
      
      # Disable a core analyzer
      core:
        api:
          enabled: false
```

## Testing Your Configuration

### 1. Verify Configuration Loads

```bash
# List all analyzers - should include your custom ones
n98-magerun2.phar performance:review --list-analyzers
```

You should see:
```
ID              | Name                        | Category   | Description
redis-memory    | Analyze Redis memory...     | redis      | Analyze Redis memory usage...
elasticsearch...| Check Elasticsearch...      | search     | Check Elasticsearch cluster...
```

### 2. Run Only Custom Analyzers

```bash
# Run only the custom group
n98-magerun2.phar performance:review --category=custom

# Or run specific analyzer
n98-magerun2.phar performance:review --skip-analyzer=database --skip-analyzer=modules
```

## How the Code Finds Your Analyzer

1. **Configuration Loading** (`PerformanceReviewCommand.php`):
```php
private function loadAnalyzerConfiguration(): void
{
    $config = $this->getApplication()->getConfig();
    $commandConfig = $config['commands'][self::class] ?? [];
    $this->analyzerConfig = $commandConfig['analyzers'] ?? [];
}
```

2. **Autoloader Registration** (handled by n98-magerun2):
```php
// n98-magerun2 reads autoloaders_psr4 and registers them
// This happens before your command runs
```

3. **Class Loading**:
```php
$class = $config['class']; // 'MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer'

if (!class_exists($class)) {
    // PHP uses the autoloader to find:
    // ~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers/RedisMemoryAnalyzer.php
}

$analyzer = new $class();
```

## Troubleshooting

### Analyzer Not Found

1. **Check paths are correct**:
```bash
ls -la ~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers/
```

2. **Verify YAML syntax**:
```bash
php -r "print_r(yaml_parse_file('app/etc/n98-magerun2.yaml'));"
```

3. **Check class namespace matches file**:
   - File: `RedisMemoryAnalyzer.php`
   - Namespace in file: `namespace MyCompany\PerformanceAnalyzer;`
   - Class in YAML: `MyCompany\PerformanceAnalyzer\RedisMemoryAnalyzer`

### Configuration Not Loading

Run the diagnostic:
```bash
php ~/.n98-magerun2/modules/performance-review/docs/scripts/diagnose.php
```

## Path Resolution

- `~` is expanded to your home directory
- Relative paths are relative to Magento root
- Absolute paths work as-is

Examples:
```yaml
autoloaders_psr4:
  # Absolute path
  MyAnalyzer\: '/var/www/analyzers'
  
  # Relative to Magento root
  MyAnalyzer\: 'app/code/MyAnalyzer'
  
  # Using home directory
  MyAnalyzer\: '~/.n98-magerun2/my-analyzers'
```

## Summary

1. **Create YAML file** in `app/etc/n98-magerun2.yaml`
2. **Register namespace** in `autoloaders_psr4` section
3. **Configure analyzer** in `commands` section
4. **Run** `n98-magerun2.phar performance:review`

The key is that n98-magerun2 handles all the complex loading - you just need to tell it:
- Where your PHP files are (autoloaders_psr4)
- Which classes to use as analyzers (commands section)