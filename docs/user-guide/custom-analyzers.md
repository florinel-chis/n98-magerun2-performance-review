# Creating Custom Analyzers

The Performance Review module supports custom analyzers, allowing you to extend its functionality with project-specific checks. This follows the same extensibility pattern as n98-magerun2's `sys:check` command.

## Quick Start

1. Create a PHP class implementing `AnalyzerCheckInterface`
2. Register it in your `n98-magerun2.yaml` configuration
3. Run `performance:review` - your analyzer runs alongside core analyzers

## Creating a Custom Analyzer

### Basic Analyzer

```php
<?php
namespace MyCompany\PerformanceAnalyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Model\Issue\Collection;

class MyCustomAnalyzer implements AnalyzerCheckInterface
{
    public function analyze(Collection $results): void
    {
        // Perform your analysis
        if ($this->detectProblem()) {
            $results->createIssue()
                ->setPriority('medium')  // high, medium, or low
                ->setCategory('Custom')
                ->setIssue('Problem detected')
                ->setDetails('Detailed explanation of the issue')
                ->setCurrentValue('current state')
                ->setRecommendedValue('desired state')
                ->add();
        }
    }
    
    private function detectProblem(): bool
    {
        // Your detection logic
        return true;
    }
}
```

### Analyzer with Configuration

```php
<?php
namespace MyCompany\PerformanceAnalyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Api\ConfigAwareInterface;
use PerformanceReview\Model\Issue\Collection;

class ConfigurableAnalyzer implements AnalyzerCheckInterface, ConfigAwareInterface
{
    private array $config = [];
    
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
    
    public function analyze(Collection $results): void
    {
        $threshold = $this->config['threshold'] ?? 100;
        
        if ($this->getValue() > $threshold) {
            $results->createIssue()
                ->setPriority('high')
                ->setCategory('Performance')
                ->setIssue('Value exceeds threshold')
                ->setDetails("Threshold is configured as {$threshold}")
                ->setCurrentValue($this->getValue())
                ->setRecommendedValue("< {$threshold}")
                ->add();
        }
    }
    
    private function getValue(): int
    {
        return 150; // Your logic here
    }
}
```

### Analyzer with Magento Dependencies

```php
<?php
namespace MyCompany\PerformanceAnalyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Api\DependencyAwareInterface;
use PerformanceReview\Model\Issue\Collection;

class MagentoAwareAnalyzer implements AnalyzerCheckInterface, DependencyAwareInterface
{
    private array $dependencies = [];
    
    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }
    
    public function analyze(Collection $results): void
    {
        $scopeConfig = $this->dependencies['scopeConfig'] ?? null;
        if (!$scopeConfig) {
            return;
        }
        
        $value = $scopeConfig->getValue('my/custom/path');
        
        if (empty($value)) {
            $results->createIssue()
                ->setPriority('medium')
                ->setCategory('Configuration')
                ->setIssue('Custom configuration missing')
                ->setDetails('The configuration value my/custom/path is not set')
                ->add();
        }
    }
}
```

## Registering Custom Analyzers

Add your analyzers to `n98-magerun2.yaml`:

```yaml
# Register your namespace for autoloading
autoloaders_psr4:
  MyCompany\PerformanceAnalyzer\: 'app/code/MyCompany/PerformanceAnalyzer'

# Register analyzers
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      custom:
        - id: my-basic-check
          class: 'MyCompany\PerformanceAnalyzer\MyCustomAnalyzer'
          description: 'Check for my custom issue'
          
        - id: my-config-check
          class: 'MyCompany\PerformanceAnalyzer\ConfigurableAnalyzer'
          description: 'Check with configuration'
          category: performance
          config:
            threshold: 200
            
        - id: my-magento-check
          class: 'MyCompany\PerformanceAnalyzer\MagentoAwareAnalyzer'
          description: 'Check Magento configuration'
```

## Configuration Locations

n98-magerun2 loads configuration from multiple locations (in order):

1. `/etc/n98-magerun2.yaml` - System-wide configuration
2. `~/.n98-magerun2.yaml` - User configuration
3. `<project>/app/etc/n98-magerun2.yaml` - Project configuration
4. Module configurations

Later configurations override earlier ones.

## Available Dependencies

When implementing `DependencyAwareInterface`, you can access these Magento services:

- `deploymentConfig` - Magento\Framework\App\DeploymentConfig
- `appState` - Magento\Framework\App\State
- `cacheTypeList` - Magento\Framework\App\Cache\TypeListInterface
- `scopeConfig` - Magento\Framework\App\Config\ScopeConfigInterface
- `resourceConnection` - Magento\Framework\App\ResourceConnection
- `productCollectionFactory` - Product collection factory
- `categoryCollectionFactory` - Category collection factory
- `urlRewriteCollectionFactory` - URL rewrite collection factory
- `moduleList` - Magento\Framework\Module\ModuleListInterface
- `moduleManager` - Magento\Framework\Module\Manager
- `componentRegistrar` - Magento\Framework\Component\ComponentRegistrarInterface
- `filesystem` - Magento\Framework\Filesystem
- `indexerRegistry` - Magento\Framework\Indexer\IndexerRegistry
- `scheduleCollectionFactory` - Cron schedule collection factory
- `productMetadata` - Magento\Framework\App\ProductMetadataInterface
- `issueFactory` - PerformanceReview\Model\IssueFactory

## Issue Priorities

Use these priority levels based on impact:

- **`high`** - Critical issues requiring immediate attention
- **`medium`** - Important issues that should be addressed soon
- **`low`** - Minor issues or suggestions for improvement

## Categories

Common categories (you can create your own):

- Config
- Database
- Modules
- Codebase
- Frontend
- Indexing
- PHP
- MySQL
- Redis
- API
- Security
- Integration
- Custom

## Using Custom Analyzers

### List All Analyzers

```bash
n98-magerun2.phar performance:review --list-analyzers
```

### Run Specific Category

```bash
n98-magerun2.phar performance:review --category=custom
```

### Skip Specific Analyzers

```bash
n98-magerun2.phar performance:review --skip-analyzer=my-slow-check
```

### Verbose Output

```bash
n98-magerun2.phar performance:review -v
```

## Best Practices

1. **Handle Errors Gracefully** - Use try-catch blocks and create low-priority issues for failures
2. **Check Dependencies** - Always verify dependencies exist before using them
3. **Be Specific** - Provide clear issue descriptions and actionable recommendations
4. **Consider Performance** - Avoid heavy operations that could slow down the review
5. **Use Appropriate Priorities** - Reserve "high" for truly critical issues
6. **Document Configuration** - If your analyzer uses config, document the options

## Example Analyzers

See the `examples/CustomAnalyzers/` directory for complete, production-ready examples:

### UnusedIndexAnalyzer (Advanced Example - Gold Standard)

A comprehensive reference implementation demonstrating all best practices:

- **Purpose**: Detects unused database indexes that waste storage and slow down writes
- **Interfaces**: Implements all three interfaces (AnalyzerCheckInterface, ConfigAwareInterface, DependencyAwareInterface)
- **Error Handling**: Comprehensive exception handling with fallback queries
- **Configuration**: Fully configurable thresholds for priority levels
- **Testing**: 21 unit tests covering all scenarios including error cases
- **Documentation**: Complete with README and setup guide
- **Production Ready**: Professional code quality suitable for production use

This analyzer serves as the **gold standard reference** for creating custom analyzers. Study this implementation to learn:
- How to use all three interfaces properly
- Best practices for error handling and graceful degradation
- How to write comprehensive tests
- How to make analyzers configurable
- Professional code documentation and structure

See:
- `examples/CustomAnalyzers/UnusedIndexAnalyzer.php` - Main analyzer code
- `examples/CustomAnalyzers/README-UnusedIndexAnalyzer.md` - Detailed documentation
- `examples/CustomAnalyzers/SETUP-UnusedIndexAnalyzer.md` - Setup instructions
- `tests/Unit/Analyzer/UnusedIndexAnalyzerTest.php` - Comprehensive test suite

### Other Examples

- `RedisMemoryAnalyzer.php` - Checks Redis memory usage and fragmentation
- `ElasticsearchHealthAnalyzer.php` - Monitors Elasticsearch cluster health

## Troubleshooting

### Analyzer Not Loading

1. Check class exists and namespace is correct
2. Verify autoloader configuration in YAML
3. Run with `-v` flag to see loading errors

### Dependencies Not Available

Some dependencies may be null if Magento isn't fully initialized. Always check:

```php
$scopeConfig = $this->dependencies['scopeConfig'] ?? null;
if (!$scopeConfig) {
    return;
}
```

### Configuration Not Working

1. Verify YAML syntax is correct
2. Check configuration file location
3. Use `--list-analyzers` to verify registration

## Contributing Analyzers

If you create generally useful analyzers, consider:

1. Publishing as a separate package
2. Submitting a pull request to add to core analyzers
3. Sharing in the n98-magerun2 community

## Migration from Core Analyzers

To override a core analyzer with your own:

```yaml
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      # Disable core analyzer
      core:
        database:
          enabled: false
      
      # Add your replacement
      custom:
        - id: my-database
          class: 'MyCompany\Analyzer\BetterDatabaseAnalyzer'
          description: 'Enhanced database analysis'
          category: database
```