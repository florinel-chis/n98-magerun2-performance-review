# CLAUDE.md - Performance Review Module

This file provides guidance to Claude Code and its sub-agents when working with the Performance Review module for n98-magerun2.

## Quick Reference

### What This Module Does
A comprehensive performance analysis tool for Magento 2 that runs 11 different analyzer categories and generates actionable reports. Think of it as a "health check" for Magento installations.

### Architecture at a Glance

```
Command (PerformanceReviewCommand)
    ↓
Analyzers (11 core + custom)
    ↓
Issue Collection
    ↓
Report Generator
    ↓
Console Output
```

### Key File Locations

| Purpose | Path | Description |
|---------|------|-------------|
| **Main Command** | `src/PerformanceReview/Command/PerformanceReviewCommand.php` | Entry point, orchestrates all analyzers |
| **Analyzer Interface** | `src/PerformanceReview/Api/AnalyzerCheckInterface.php` | Contract for all analyzers (new interface) |
| **Issue Collection** | `src/PerformanceReview/Model/Issue/Collection.php` | Collects issues from analyzers |
| **Issue Builder** | `src/PerformanceReview/Model/Issue/IssueBuilder.php` | Fluent API for creating issues |
| **Report Generator** | `src/PerformanceReview/Model/ReportGenerator.php` | Formats and outputs reports |
| **Module Config** | `n98-magerun2.yaml` | Registers commands and autoloaders |
| **Core Analyzers** | `src/PerformanceReview/Analyzer/*.php` | 11 built-in analyzers |
| **Examples** | `examples/CustomAnalyzers/*.php` | Example custom analyzer implementations |

### Core Interfaces

```php
// Primary interface for new analyzers (v2.0)
interface AnalyzerCheckInterface {
    public function analyze(Collection $results): void;
}

// Optional: For analyzers needing configuration
interface ConfigAwareInterface {
    public function setConfig(array $config): void;
}

// Optional: For analyzers needing Magento dependencies
interface DependencyAwareInterface {
    public function setDependencies(array $dependencies): void;
}
```

### 11 Analyzer Categories

1. **config** - Configuration settings (mode, cache, session)
2. **database** - Database size, tables, URL rewrites
3. **modules** - Third-party modules and impact
4. **codebase** - Code organization, generated content
5. **frontend** - JS/CSS optimization, images
6. **indexing** - Indexers, cron jobs, queues
7. **php** - PHP version, memory, extensions
8. **mysql** - MySQL configuration and optimization
9. **redis** - Redis setup and configuration
10. **api** - API integrations and tokens
11. **thirdparty** - Known problematic extensions

### Example Analyzers (Reference Implementations)

The module includes production-ready example analyzers in `examples/CustomAnalyzers/`:

**UnusedIndexAnalyzer** (GOLD STANDARD REFERENCE)
- **Purpose**: Detects unused database indexes that waste storage and slow down writes
- **Interfaces**: All three (AnalyzerCheckInterface, ConfigAwareInterface, DependencyAwareInterface)
- **Testing**: 21 comprehensive unit tests covering all scenarios
- **Configuration**: Fully configurable thresholds (min_size_mb, high_priority_mb, medium_priority_mb)
- **Error Handling**: Comprehensive exception handling with fallback queries
- **Documentation**: Complete README and setup guide included
- **Status**: Production-ready, professionally coded

**Use UnusedIndexAnalyzer as your reference when creating custom analyzers.** It demonstrates all best practices.

**Files:**
- `examples/CustomAnalyzers/UnusedIndexAnalyzer.php` - Implementation
- `examples/CustomAnalyzers/README-UnusedIndexAnalyzer.md` - Documentation
- `examples/CustomAnalyzers/SETUP-UnusedIndexAnalyzer.md` - Setup guide
- `tests/Unit/Analyzer/UnusedIndexAnalyzerTest.php` - Test suite

**Other Examples:**
- `RedisMemoryAnalyzer.php` - Checks Redis memory usage and fragmentation
- `ElasticsearchHealthAnalyzer.php` - Monitors Elasticsearch cluster health

## Common Tasks

### Task 1: Create a New Custom Analyzer

**When to do this:** User wants to add a new performance check.

**Step-by-step:**

1. **Create the analyzer class:**
   ```php
   <?php
   namespace MyCompany\Analyzer;

   use PerformanceReview\Api\AnalyzerCheckInterface;
   use PerformanceReview\Model\Issue\Collection;

   class MyCustomAnalyzer implements AnalyzerCheckInterface
   {
       public function analyze(Collection $results): void
       {
           // Your analysis logic here
           if ($this->detectIssue()) {
               $results->createIssue()
                   ->setPriority('medium')  // high|medium|low
                   ->setCategory('Custom')
                   ->setIssue('Short description of the issue')
                   ->setDetails('Detailed explanation')
                   ->setCurrentValue('actual state')
                   ->setRecommendedValue('desired state')
                   ->add();
           }
       }

       private function detectIssue(): bool
       {
           // Your detection logic
           return true;
       }
   }
   ```

2. **Register in n98-magerun2.yaml:**
   ```yaml
   # Add autoloader if needed
   autoloaders_psr4:
     MyCompany\Analyzer\: 'path/to/MyCompany/Analyzer'

   # Register the analyzer
   commands:
     PerformanceReview\Command\PerformanceReviewCommand:
       analyzers:
         custom:
           - id: my-custom-check
             class: 'MyCompany\Analyzer\MyCustomAnalyzer'
             description: 'Brief description for --list-analyzers'
             category: custom  # Optional: groups analyzer
   ```

3. **Test the analyzer:**
   ```bash
   # List to verify registration
   n98-magerun2.phar performance:review --list-analyzers

   # Run your specific analyzer
   n98-magerun2.phar performance:review --category=custom -v
   ```

**Files to modify:** None in core module (extensibility by design)
**Files to create:** Your analyzer class + yaml configuration

**TIP:** Study `examples/CustomAnalyzers/UnusedIndexAnalyzer.php` for a complete reference implementation with all best practices.

### Task 2: Add a New Core Analyzer

**When to do this:** Contributing a new analyzer to the core module.

**Step-by-step:**

1. **Create analyzer in core:** `src/PerformanceReview/Analyzer/YourAnalyzer.php`
2. **Implement AnalyzerCheckInterface:**
   ```php
   <?php
   namespace PerformanceReview\Analyzer;

   use PerformanceReview\Api\AnalyzerCheckInterface;
   use PerformanceReview\Api\DependencyAwareInterface;
   use PerformanceReview\Model\Issue\Collection;

   class YourAnalyzer implements AnalyzerCheckInterface, DependencyAwareInterface
   {
       private array $dependencies = [];

       public function setDependencies(array $dependencies): void
       {
           $this->dependencies = $dependencies;
       }

       public function analyze(Collection $results): void
       {
           // Use $this->dependencies for Magento services
           $scopeConfig = $this->dependencies['scopeConfig'] ?? null;
           // ... analysis logic
       }
   }
   ```

3. **Register in PerformanceReviewCommand.php:**
   - Add property for analyzer (line ~100)
   - Inject in `inject()` method (line ~140)
   - Add to category map in `getCategoryAnalyzers()` (line ~400)

4. **Update documentation:**
   - Add to README.md "Analysis Categories" section
   - Add example to CUSTOM_ANALYZERS.md if pattern is new

**Files to modify:**
- `src/PerformanceReview/Command/PerformanceReviewCommand.php`
- `README.md`
- Optionally `CUSTOM_ANALYZERS.md`

### Task 3: Debug Analyzer Not Loading

**When to do this:** Custom analyzer doesn't appear in --list-analyzers

**Systematic approach:**

1. **Verify class exists and is syntactically correct:**
   ```bash
   php -l path/to/YourAnalyzer.php
   ```

2. **Check YAML configuration:**
   - Syntax correct? (use YAML validator)
   - File location correct? (see Configuration Locations below)
   - Autoloader path correct? (use `%module%` placeholder if relative)
   - Class name fully qualified?

3. **Check file permissions:**
   ```bash
   ls -la path/to/YourAnalyzer.php
   chmod 644 path/to/YourAnalyzer.php  # if needed
   ```

4. **Run with verbose output:**
   ```bash
   n98-magerun2.phar performance:review --list-analyzers -vvv
   ```

5. **Check autoloader registration:**
   - Add temporary debug in PerformanceReviewCommand.php:
   ```php
   // In loadCustomAnalyzers() method around line 580
   $this->output->writeln("Loading custom analyzers...");
   var_dump($customAnalyzers); // See what's being loaded
   ```

**Common issues:**
- Namespace mismatch between class and YAML
- Wrong file path in autoloader configuration
- YAML indentation issues (use spaces, not tabs)
- Missing `AnalyzerCheckInterface` implementation

### Task 4: Modify Existing Analyzer

**When to do this:** Need to change behavior of core analyzer.

**Approach A: Override with Custom (Recommended)**

1. Create your own analyzer implementing same logic
2. Disable core analyzer in YAML:
   ```yaml
   commands:
     PerformanceReview\Command\PerformanceReviewCommand:
       analyzers:
         core:
           database:
             enabled: false
         custom:
           - id: my-database
             class: 'MyCompany\Analyzer\MyDatabaseAnalyzer'
             category: database
   ```

**Approach B: Modify Core (For Contributors)**

1. Edit the analyzer file directly: `src/PerformanceReview/Analyzer/*.php`
2. Maintain backward compatibility
3. Add tests (see Task 5)
4. Update CHANGELOG.md
5. Submit pull request

**Files to modify:** `src/PerformanceReview/Analyzer/[SpecificAnalyzer].php`

### Task 5: Add Tests for Analyzer

**When to do this:** Creating new analyzer or modifying existing.

**Step-by-step:**

1. **Create test file:** `tests/Unit/Analyzer/YourAnalyzerTest.php`
   ```php
   <?php
   namespace PerformanceReview\Test\Unit\Analyzer;

   use PHPUnit\Framework\TestCase;
   use PerformanceReview\Analyzer\YourAnalyzer;
   use PerformanceReview\Model\Issue\Collection;

   class YourAnalyzerTest extends TestCase
   {
       private YourAnalyzer $analyzer;
       private Collection $collection;

       protected function setUp(): void
       {
           $this->analyzer = new YourAnalyzer();
           $this->collection = new Collection();
       }

       public function testAnalyzeDetectsIssue(): void
       {
           // Arrange
           $this->analyzer->setDependencies([/* mock dependencies */]);

           // Act
           $this->analyzer->analyze($this->collection);

           // Assert
           $issues = $this->collection->getIssues();
           $this->assertNotEmpty($issues);
           $this->assertEquals('expected-priority', $issues[0]->getPriority());
       }
   }
   ```

2. **Run tests:**
   ```bash
   # Run specific test
   vendor/bin/phpunit tests/Unit/Analyzer/YourAnalyzerTest.php

   # Run all tests
   vendor/bin/phpunit
   ```

**Note:** Test infrastructure is being developed. See TESTING_GUIDE.md for current status.

### Task 6: Add Configuration to Analyzer

**When to do this:** Analyzer needs configurable thresholds or settings.

**Step-by-step:**

1. **Implement ConfigAwareInterface:**
   ```php
   class ConfigurableAnalyzer implements
       AnalyzerCheckInterface,
       ConfigAwareInterface
   {
       private array $config = [];

       public function setConfig(array $config): void
       {
           $this->config = $config;
       }

       public function analyze(Collection $results): void
       {
           $threshold = $this->config['threshold'] ?? 100;
           // Use threshold in analysis
       }
   }
   ```

2. **Configure in YAML:**
   ```yaml
   commands:
     PerformanceReview\Command\PerformanceReviewCommand:
       analyzers:
         custom:
           - id: configurable-check
             class: 'MyCompany\Analyzer\ConfigurableAnalyzer'
             config:
               threshold: 200
               mode: strict
   ```

3. **Access in analyzer:** `$this->config['threshold']`

**Configuration cascade:** Later configs override earlier ones (project > user > system).

### Task 7: Use Magento Dependencies in Analyzer

**When to do this:** Analyzer needs Magento services (database, config, etc.)

**Step-by-step:**

1. **Implement DependencyAwareInterface:**
   ```php
   class MagentoAwareAnalyzer implements
       AnalyzerCheckInterface,
       DependencyAwareInterface
   {
       private array $dependencies = [];

       public function setDependencies(array $dependencies): void
       {
           $this->dependencies = $dependencies;
       }

       public function analyze(Collection $results): void
       {
           // Always check dependencies exist (may be null)
           $scopeConfig = $this->dependencies['scopeConfig'] ?? null;
           if (!$scopeConfig) {
               return; // Gracefully handle missing dependency
           }

           $value = $scopeConfig->getValue('path/to/config');
           // Use value in analysis
       }
   }
   ```

2. **Available dependencies** (see PerformanceReviewCommand.php:~140):
   - `deploymentConfig` - App deployment config
   - `appState` - Application state
   - `cacheTypeList` - Cache type list
   - `scopeConfig` - Scope configuration
   - `resourceConnection` - Database connection
   - `productCollectionFactory` - Product collection factory
   - `categoryCollectionFactory` - Category collection factory
   - `urlRewriteCollectionFactory` - URL rewrite collection factory
   - `moduleList` - Module list
   - `moduleManager` - Module manager
   - `componentRegistrar` - Component registrar
   - `filesystem` - Filesystem
   - `indexerRegistry` - Indexer registry
   - `scheduleCollectionFactory` - Cron schedule collection factory
   - `productMetadata` - Product metadata
   - `issueFactory` - Issue factory (legacy, prefer Collection)

**Best practice:** Always check if dependency exists before using.

## Code Patterns

### Issue Creation Pattern

```php
// Simple issue
$results->createIssue()
    ->setPriority('high')
    ->setCategory('Database')
    ->setIssue('Database size exceeds 50GB')
    ->add();

// Issue with details
$results->createIssue()
    ->setPriority('medium')
    ->setCategory('Config')
    ->setIssue('Redis not configured for cache')
    ->setDetails('Using file cache can impact performance significantly')
    ->setCurrentValue('File')
    ->setRecommendedValue('Redis')
    ->add();

// Multiple issues in loop
foreach ($tables as $table) {
    if ($table->size > $threshold) {
        $results->createIssue()
            ->setPriority('low')
            ->setCategory('Database')
            ->setIssue("Table {$table->name} is large")
            ->setCurrentValue($table->size . ' MB')
            ->setRecommendedValue('< ' . $threshold . ' MB')
            ->add();
    }
}
```

### Priority Guidelines

| Priority | Use When | Example |
|----------|----------|---------|
| **high** | Critical issue impacting performance/security | Developer mode in production |
| **medium** | Important optimization opportunity | Large database size (20-50GB) |
| **low** | Best practice or minor improvement | Image optimization not enabled |

### Error Handling Pattern

```php
public function analyze(Collection $results): void
{
    try {
        // Your analysis logic
        $data = $this->fetchData();

    } catch (\Exception $e) {
        // Don't let one analyzer break the whole report
        $results->createIssue()
            ->setPriority('low')
            ->setCategory('System')
            ->setIssue('Analysis failed: ' . get_class($this))
            ->setDetails($e->getMessage())
            ->add();
    }
}
```

### Database Query Pattern

```php
// Get connection
$connection = $this->dependencies['resourceConnection']->getConnection();

// Count query (memory efficient)
$count = $connection->fetchOne('SELECT COUNT(*) FROM table_name');

// Fetch single value
$value = $connection->fetchOne('SELECT column FROM table WHERE id = ?', [1]);

// Fetch row
$row = $connection->fetchRow('SELECT * FROM table WHERE id = ?', [1]);

// Fetch all (use cautiously - memory)
$rows = $connection->fetchAll('SELECT * FROM table LIMIT 100');
```

### Configuration Reading Pattern

```php
// Read store config
$scopeConfig = $this->dependencies['scopeConfig'];
$value = $scopeConfig->getValue(
    'section/group/field',
    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
);

// Read deployment config (env.php)
$deploymentConfig = $this->dependencies['deploymentConfig'];
$cacheConfig = $deploymentConfig->get('cache');
```

## Configuration Locations

n98-magerun2 loads configuration files in this order (later overrides earlier):

1. **/etc/n98-magerun2.yaml** - System-wide
2. **~/.n98-magerun2.yaml** - User-specific
3. **&lt;magento-root&gt;/n98-magerun2.yaml** - Project-specific
4. **&lt;magento-root&gt;/app/etc/n98-magerun2.yaml** - Alternative project location
5. **Module configs** - In configured plugin folders

**Plugin folders searched:**
- `/usr/share/n98-magerun2/modules`
- `/usr/local/share/n98-magerun2/modules`
- `~/.n98-magerun2/modules`

**Use `%module%` placeholder:** In YAML config, `%module%` resolves to the module's directory.

## Testing Patterns

### Manual Testing

```bash
# Quick verification
n98-magerun2.phar performance:review --list-analyzers

# Test specific category
n98-magerun2.phar performance:review --category=config -v

# Test your analyzer
n98-magerun2.phar performance:review --category=custom -vvv

# Test with specific Magento installation
n98-magerun2.phar performance:review --root-dir=/path/to/magento

# Skip slow analyzers during development
n98-magerun2.phar performance:review --skip-analyzer=database --skip-analyzer=codebase
```

### Unit Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/Unit/Analyzer/ConfigurationAnalyzerTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Integration Testing

See TESTING_GUIDE.md for:
- Setting up test Magento environment
- Running integration tests
- Testing custom analyzers

## Troubleshooting Patterns

### "Analyzer not found"

1. Check class exists: `ls -la path/to/Analyzer.php`
2. Check namespace matches file: Open file and verify `namespace`
3. Check YAML syntax: Use online YAML validator
4. Check autoloader path: Verify path is correct in YAML
5. Run with verbose: `n98-magerun2.phar performance:review --list-analyzers -vvv`

### "No issues detected" but should be issues

1. Add debug output:
   ```php
   file_put_contents('/tmp/analyzer-debug.log',
       "Analyzer running: " . get_class($this) . "\n",
       FILE_APPEND);
   ```
2. Check dependencies are available:
   ```php
   var_dump(array_keys($this->dependencies));
   ```
3. Verify detection logic:
   ```php
   // Add temporary verbose output
   $results->createIssue()
       ->setPriority('low')
       ->setCategory('Debug')
       ->setIssue('Debug: detection result = ' . var_export($detected, true))
       ->add();
   ```

### "Memory exhausted"

1. Increase PHP memory: `php -d memory_limit=4G n98-magerun2.phar performance:review`
2. Optimize queries: Use COUNT instead of fetching all rows
3. Process in batches:
   ```php
   $pageSize = 1000;
   for ($page = 1; $page <= $totalPages; $page++) {
       $collection->setPage($page, $pageSize);
       // Process batch
       $collection->clear(); // Free memory
   }
   ```

### "Class not found"

1. Check autoloader in YAML is correct
2. Verify namespace matches directory structure
3. Clear any caches: `n98-magerun2.phar cache:clear`
4. Check file permissions: `chmod 644 YourAnalyzer.php`

## Development Workflow

### Adding a New Feature

1. **Plan**: Identify which analyzer or component to modify
2. **Branch**: Create feature branch (if using git)
3. **Code**: Implement following patterns above
4. **Test**: Manual testing with real Magento installation
5. **Document**: Update relevant .md files
6. **Review**: Use checklist in PULL_REQUEST_TEMPLATE.md

### Modifying Existing Analyzer

1. **Read**: Understand current implementation
2. **Test**: Run analyzer before changes to understand current behavior
3. **Modify**: Make changes following existing patterns
4. **Test**: Verify changes work as expected
5. **Document**: Update CHANGELOG.md with your changes

### Best Practices

1. **Handle errors gracefully** - Don't let one analyzer break the report
2. **Check dependencies** - Always verify dependencies exist before using
3. **Use appropriate priorities** - Reserve "high" for critical issues
4. **Be memory conscious** - Use COUNT queries, not fetching all rows
5. **Provide actionable recommendations** - Tell users what to do, not just what's wrong
6. **Follow existing patterns** - Consistency makes maintenance easier

## Module Extension Points

### For Custom Functionality

1. **Custom Analyzers** - Add new analysis (see Task 1)
2. **Configuration** - Override thresholds and settings (see Task 6)
3. **Report Format** - Extend ReportGenerator.php for new formats
4. **Command Options** - Extend PerformanceReviewCommand.php

### For Integration

1. **CI/CD Integration** - Use exit codes (0 = success, 1 = issues found)
2. **Automation** - Use --output-file and --no-color options
3. **Monitoring** - Parse output or extend for JSON format

## Quick Reference Commands

```bash
# List all available analyzers
n98-magerun2.phar performance:review --list-analyzers

# Run full review
n98-magerun2.phar performance:review

# Run specific category
n98-magerun2.phar performance:review --category=database

# Save report to file
n98-magerun2.phar performance:review --output-file=report.txt

# Run with details
n98-magerun2.phar performance:review --details

# Skip specific analyzers
n98-magerun2.phar performance:review --skip-analyzer=redis --skip-analyzer=api

# Verbose output (debugging)
n98-magerun2.phar performance:review -vvv

# Different Magento installation
n98-magerun2.phar performance:review --root-dir=/path/to/magento
```

## Related Documentation

- **README.md** - User-facing documentation and overview
- **CUSTOM_ANALYZERS.md** - Detailed custom analyzer guide
- **TESTING_GUIDE.md** - Testing instructions
- **TROUBLESHOOTING.md** - Common issues and solutions
- **CHANGELOG.md** - Version history

## Getting Help

For questions or issues:
1. Check TROUBLESHOOTING.md
2. Review UnusedIndexAnalyzer in examples/CustomAnalyzers/ for best-practice reference
3. Review other examples in examples/CustomAnalyzers/
4. Check parent n98-magerun2 documentation at /Users/flo/fch/n98-magerun2/CLAUDE.md
5. Submit issue or pull request

---

**Version**: 2.0
**Last Updated**: 2025-01-31
**Maintained For**: Claude Code and Sub-agents
