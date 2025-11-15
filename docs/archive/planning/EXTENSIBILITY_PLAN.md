# Extensibility Implementation Plan

## Overview

This plan outlines the implementation of an extensibility system for the performance-review module, inspired by n98-magerun2's sys:check command architecture. This will allow developers to add custom analyzers through YAML configuration without modifying the core module.

## Goals

1. Enable developers to register custom analyzers via YAML configuration
2. Maintain backward compatibility with existing analyzers
3. Provide a clear API for custom analyzer development
4. Support both module-based and project-based custom analyzers
5. Allow custom analyzers to be distributed as separate packages

## Architecture Design

### 1. Create Analyzer Interface

```php
<?php
namespace PerformanceReview\Api;

interface AnalyzerInterface
{
    /**
     * Get the unique identifier for this analyzer
     */
    public function getCode(): string;
    
    /**
     * Get the category this analyzer belongs to
     */
    public function getCategory(): string;
    
    /**
     * Get human-readable name for this analyzer
     */
    public function getName(): string;
    
    /**
     * Get description of what this analyzer checks
     */
    public function getDescription(): string;
    
    /**
     * Run the analysis and return issues found
     * 
     * @return \PerformanceReview\Model\IssueInterface[]
     */
    public function analyze(): array;
    
    /**
     * Check if this analyzer is enabled
     */
    public function isEnabled(): bool;
    
    /**
     * Set dependencies that may be injected
     * 
     * @param array $dependencies
     */
    public function setDependencies(array $dependencies): void;
}
```

### 2. Create Abstract Base Analyzer

```php
<?php
namespace PerformanceReview\Analyzer;

use PerformanceReview\Api\AnalyzerInterface;
use PerformanceReview\Model\IssueFactory;

abstract class AbstractAnalyzer implements AnalyzerInterface
{
    protected IssueFactory $issueFactory;
    protected array $config = [];
    protected array $dependencies = [];
    
    public function __construct(IssueFactory $issueFactory, array $config = [])
    {
        $this->issueFactory = $issueFactory;
        $this->config = $config;
    }
    
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }
    
    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }
    
    protected function getDependency(string $key)
    {
        return $this->dependencies[$key] ?? null;
    }
}
```

### 3. Refactor Existing Analyzers

Update all existing analyzers to implement the new interface:

```php
class ConfigurationAnalyzer extends AbstractAnalyzer
{
    public function getCode(): string
    {
        return 'configuration';
    }
    
    public function getCategory(): string
    {
        return 'Config';
    }
    
    public function getName(): string
    {
        return 'Configuration Analysis';
    }
    
    public function getDescription(): string
    {
        return 'Analyzes Magento configuration settings for performance issues';
    }
    
    // Existing analyze() method remains the same
}
```

### 4. Create Analyzer Registry

```php
<?php
namespace PerformanceReview\Model;

use PerformanceReview\Api\AnalyzerInterface;

class AnalyzerRegistry
{
    private array $analyzers = [];
    private array $customAnalyzerConfigs = [];
    
    public function register(string $code, AnalyzerInterface $analyzer): void
    {
        $this->analyzers[$code] = $analyzer;
    }
    
    public function registerCustom(array $config): void
    {
        $this->customAnalyzerConfigs[] = $config;
    }
    
    public function get(string $code): ?AnalyzerInterface
    {
        return $this->analyzers[$code] ?? null;
    }
    
    public function getAll(): array
    {
        return $this->analyzers;
    }
    
    public function getByCategory(string $category): array
    {
        return array_filter($this->analyzers, function($analyzer) use ($category) {
            return $analyzer->getCategory() === $category;
        });
    }
    
    public function loadCustomAnalyzers(IssueFactory $issueFactory): void
    {
        foreach ($this->customAnalyzerConfigs as $config) {
            if (!class_exists($config['class'])) {
                continue;
            }
            
            $analyzer = new $config['class']($issueFactory, $config);
            if ($analyzer instanceof AnalyzerInterface) {
                $this->register($analyzer->getCode(), $analyzer);
            }
        }
    }
}
```

### 5. YAML Configuration Support

Update `n98-magerun2.yaml` schema:

```yaml
autoloaders_psr4:
  PerformanceReview\: '%module%/src/PerformanceReview'

commands:
  customCommands:
    - PerformanceReview\Command\PerformanceReviewCommand

performance_review:
  analyzers:
    # Core analyzers can be disabled
    core:
      configuration:
        enabled: true
      database:
        enabled: true
      
    # Custom analyzers
    custom:
      - class: MyCompany\PerformanceAnalyzer\CustomCacheAnalyzer
        code: custom_cache
        category: Cache
        enabled: true
        config:
          threshold: 1000
          
      - class: MyCompany\PerformanceAnalyzer\CustomSecurityAnalyzer
        code: security_check
        category: Security
        enabled: true
```

### 6. Update PerformanceReviewCommand

```php
protected function configure(): void
{
    // ... existing configuration ...
    
    $this->addOption(
        'skip-analyzer',
        null,
        InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
        'Skip specific analyzer(s) by code'
    );
    
    $this->addOption(
        'list-analyzers',
        null,
        InputOption::VALUE_NONE,
        'List all available analyzers'
    );
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
    // Initialize analyzer registry
    $registry = $this->initializeAnalyzerRegistry();
    
    // Handle --list-analyzers option
    if ($input->getOption('list-analyzers')) {
        return $this->listAnalyzers($registry, $output);
    }
    
    // Get analyzers to run
    $analyzers = $this->getAnalyzersToRun($registry, $input);
    
    // Run analysis
    $issues = [];
    foreach ($analyzers as $analyzer) {
        if ($analyzer->isEnabled()) {
            $output->write(sprintf('Running %s... ', $analyzer->getName()));
            try {
                $analyzerIssues = $analyzer->analyze();
                $issues = array_merge($issues, $analyzerIssues);
                $output->writeln('<info>✓</info>');
            } catch (\Exception $e) {
                $output->writeln('<error>✗</error>');
                if ($output->isVerbose()) {
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                }
            }
        }
    }
    
    // ... rest of execution remains the same
}
```

## Implementation Steps

### Phase 1: Core Infrastructure (Week 1)

1. **Create Interfaces and Abstract Classes**
   - AnalyzerInterface
   - AbstractAnalyzer
   - AnalyzerRegistry

2. **Refactor Existing Analyzers**
   - Update all 11 analyzers to extend AbstractAnalyzer
   - Implement required interface methods
   - Maintain backward compatibility

3. **Update Dependency Injection**
   - Create factory pattern for analyzer instantiation
   - Support dynamic dependency injection

### Phase 2: Configuration System (Week 2)

1. **YAML Configuration Loader**
   - Parse performance_review section from config files
   - Support multiple configuration sources
   - Merge configurations with proper precedence

2. **Custom Analyzer Loading**
   - Implement class loading mechanism
   - Validate custom analyzer classes
   - Handle configuration errors gracefully

3. **Registry Integration**
   - Wire up registry in PerformanceReviewCommand
   - Support analyzer filtering and selection

### Phase 3: Developer Experience (Week 3)

1. **Example Custom Analyzers**
   - Create 2-3 example custom analyzers
   - Show different use cases and patterns
   - Include in documentation

2. **Documentation**
   - How to create custom analyzers
   - Configuration reference
   - Best practices guide
   - API documentation

3. **Developer Tools**
   - Analyzer scaffold command
   - Validation command for custom analyzers
   - Debug mode for analyzer development

### Phase 4: Testing and Polish (Week 4)

1. **Unit Tests**
   - Test analyzer registry
   - Test configuration loading
   - Test custom analyzer integration

2. **Integration Tests**
   - Test with various configurations
   - Test error handling
   - Test performance impact

3. **Documentation Review**
   - Update README
   - Create migration guide
   - Add troubleshooting section

## Example Custom Analyzer

```php
<?php
namespace MyCompany\PerformanceAnalyzer;

use PerformanceReview\Analyzer\AbstractAnalyzer;

class CustomRedisAnalyzer extends AbstractAnalyzer
{
    public function getCode(): string
    {
        return 'custom_redis_memory';
    }
    
    public function getCategory(): string
    {
        return 'Redis';
    }
    
    public function getName(): string
    {
        return 'Redis Memory Usage Analysis';
    }
    
    public function getDescription(): string
    {
        return 'Analyzes Redis memory usage patterns and fragmentation';
    }
    
    public function analyze(): array
    {
        $issues = [];
        
        // Custom analysis logic
        $redisInfo = $this->getRedisInfo();
        $memoryUsage = $redisInfo['used_memory'] ?? 0;
        $memoryRss = $redisInfo['used_memory_rss'] ?? 0;
        
        if ($memoryRss > 0) {
            $fragmentation = $memoryRss / $memoryUsage;
            if ($fragmentation > 1.5) {
                $issues[] = $this->issueFactory->createIssue(
                    'high',
                    $this->getCategory(),
                    'High Redis memory fragmentation',
                    'Memory fragmentation ratio is high, consider restarting Redis',
                    sprintf('%.2f', $fragmentation),
                    '< 1.5'
                );
            }
        }
        
        return $issues;
    }
    
    private function getRedisInfo(): array
    {
        // Implementation to get Redis INFO
        return [];
    }
}
```

## Benefits

1. **Extensibility**: Easy to add project-specific checks
2. **Modularity**: Custom analyzers can be packaged separately
3. **Flexibility**: Enable/disable analyzers via configuration
4. **Community**: Enables sharing of custom analyzers
5. **Backward Compatible**: Existing functionality preserved

## Risks and Mitigations

1. **Risk**: Performance impact from loading many analyzers
   - **Mitigation**: Lazy loading, caching, parallel execution

2. **Risk**: Security issues from loading untrusted code
   - **Mitigation**: Clear documentation on security, optional sandboxing

3. **Risk**: Breaking changes to analyzer API
   - **Mitigation**: Semantic versioning, deprecation notices

4. **Risk**: Complexity for simple use cases
   - **Mitigation**: Keep default usage simple, advanced features optional

## Success Criteria

1. All existing analyzers work without modification
2. Custom analyzers can be added via YAML configuration
3. Performance impact < 5% for default configuration
4. Clear documentation and examples available
5. Unit test coverage > 80% for new code
6. Community can easily create and share analyzers

## Timeline

- **Week 1**: Core infrastructure implementation
- **Week 2**: Configuration system and loading
- **Week 3**: Developer experience and documentation
- **Week 4**: Testing, polish, and release preparation

Total estimated time: 4 weeks