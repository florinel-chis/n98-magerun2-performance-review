# How YAML Configuration Loading Works

**IMPORTANT**: This guide explains the configuration system based on actual testing. Configuration files do NOT exist by default - you must create them to use custom analyzers.

## Configuration Loading Order

n98-magerun2 loads configuration files in this specific order (later files override earlier ones):

1. **Distribution Config** (`config.yaml` in n98-magerun2)
   - Built-in configuration, always present

2. **System Config**: `/etc/n98-magerun2.yaml` (Unix) or `%WINDIR%/n98-magerun2.yaml` (Windows)
   - **Does not exist by default** - create only if needed system-wide
   - Most users don't need this

3. **Plugin Module Configs**: `~/.n98-magerun2/modules/*/n98-magerun2.yaml`
   - **Only exists for modules that have one** - like our performance-review module
   - Registers module commands with n98-magerun2

4. **User Config**: `~/.n98-magerun2.yaml`
   - **Does not exist by default** - create manually if needed
   - Affects all your n98-magerun2 usage

5. **Project Config**: `<magento-root>/app/etc/n98-magerun2.yaml`
   - **Does not exist by default** - create for custom analyzers
   - **Recommended location** for custom analyzer configuration

## Step-by-Step: Configuring Example Analyzers

### Step 1: Choose Configuration Location

For custom analyzers, use your **Magento project** location (recommended):
```bash
cd <magento-root>
mkdir -p app/etc
```

**Why project-level?** Each Magento project can have different analyzers without affecting others.

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

Create this file: `<magento-root>/app/etc/n98-magerun2.yaml`

```yaml
# Custom analyzer configuration for this Magento project

# 1. Register namespaces (tells PHP where to find the classes)
autoloaders_psr4:
  # For the example analyzers provided with the module
  MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'
  
  # For your own custom analyzers
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
n98-magerun2.phar --root-dir <magento-root> performance:review --list-analyzers
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
n98-magerun2.phar --root-dir <magento-root> performance:review --category=custom

# Or run specific analyzer
n98-magerun2.phar --root-dir <magento-root> performance:review --skip-analyzer=database --skip-analyzer=modules
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

## Summary - What Actually Works

**Tested and Verified Steps**:

1. **Create config file**: `<magento-root>/app/etc/n98-magerun2.yaml` (does not exist by default)
2. **Register namespace** in `autoloaders_psr4` section
3. **Configure analyzer** in `commands` section  
4. **Run**: `n98-magerun2.phar --root-dir <magento-root> performance:review --list-analyzers`
5. **Verify**: Your custom analyzer appears in the list
6. **Execute**: `n98-magerun2.phar --root-dir <magento-root> performance:review`

**Key Point**: Only the module registration file (`~/.n98-magerun2/modules/performance-review/n98-magerun2.yaml`) exists by default. All other configuration files must be created manually.