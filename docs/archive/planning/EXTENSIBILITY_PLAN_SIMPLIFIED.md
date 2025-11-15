# Simplified Extensibility Implementation Plan

## Overview

Based on n98-magerun2's sys:check extensibility model, this simplified plan aligns the performance-review module with existing n98-magerun2 patterns and conventions.

## Key Design Decisions

1. **Follow n98-magerun2 Patterns**: Use similar YAML structure and interface design
2. **Minimal Changes**: Keep existing analyzers working without modification
3. **Simple Integration**: Make it easy to add custom analyzers
4. **Consistent Naming**: Use n98-magerun2's naming conventions

## Implementation Approach

### 1. Analyzer Interface (Simplified)

```php
<?php
namespace PerformanceReview\Api;

use PerformanceReview\Model\Issue\Collection;

interface AnalyzerCheckInterface
{
    /**
     * Run the analysis check
     * 
     * @param Collection $results Collection to add issues to
     * @return void
     */
    public function analyze(Collection $results): void;
}
```

### 2. Optional Aware Interfaces

```php
interface ConfigAwareInterface
{
    public function setConfig(array $config): void;
}

interface DependencyAwareInterface  
{
    public function setDependencies(array $dependencies): void;
}
```

### 3. YAML Configuration Structure

Following n98-magerun2's pattern:

```yaml
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      # Core analyzers (built-in)
      config:
        - id: app-mode
          class: PerformanceReview\Analyzer\ConfigurationAnalyzer
          description: 'Check application mode'
        - id: cache-backend  
          class: PerformanceReview\Analyzer\ConfigurationAnalyzer
          description: 'Check cache backend configuration'
      
      # Custom analyzers (user-defined)
      custom:
        - id: redis-memory-fragmentation
          class: MyCompany\Analyzer\RedisFragmentationCheck
          description: 'Check Redis memory fragmentation'
          config:
            threshold: 1.5
        - id: elasticsearch-health
          class: MyCompany\Analyzer\ElasticsearchHealthCheck  
          description: 'Check Elasticsearch cluster health'
```

### 4. Issue Collection (Similar to Result\Collection)

```php
<?php
namespace PerformanceReview\Model\Issue;

class Collection
{
    private array $issues = [];
    private IssueFactory $issueFactory;
    
    public function __construct(IssueFactory $issueFactory)
    {
        $this->issueFactory = $issueFactory;
    }
    
    public function createIssue(): IssueBuilder
    {
        return new IssueBuilder($this);
    }
    
    public function addIssue(IssueInterface $issue): void
    {
        $this->issues[] = $issue;
    }
    
    public function getIssues(): array
    {
        return $this->issues;
    }
}

class IssueBuilder
{
    private Collection $collection;
    private array $data = [];
    
    public function setPriority(string $priority): self
    {
        $this->data['priority'] = $priority;
        return $this;
    }
    
    public function setCategory(string $category): self
    {
        $this->data['category'] = $category;
        return $this;
    }
    
    public function setIssue(string $issue): self
    {
        $this->data['issue'] = $issue;
        return $this;
    }
    
    public function setDetails(string $details): self
    {
        $this->data['details'] = $details;
        return $this;
    }
    
    public function setCurrentValue($value): self
    {
        $this->data['current_value'] = $value;
        return $this;
    }
    
    public function setRecommendedValue($value): self
    {
        $this->data['recommended_value'] = $value;
        return $this;
    }
    
    public function add(): void
    {
        $issue = $this->collection->issueFactory->create($this->data);
        $this->collection->addIssue($issue);
    }
}
```

### 5. Example Custom Analyzer

```php
<?php
namespace MyCompany\Analyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Api\ConfigAwareInterface;
use PerformanceReview\Model\Issue\Collection;

class RedisFragmentationCheck implements AnalyzerCheckInterface, ConfigAwareInterface
{
    private array $config = [];
    
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
    
    public function analyze(Collection $results): void
    {
        $threshold = $this->config['threshold'] ?? 1.5;
        
        // Get Redis info (simplified)
        $memoryUsage = $this->getRedisMemoryUsage();
        $memoryRss = $this->getRedisMemoryRss();
        
        if ($memoryUsage > 0) {
            $fragmentation = $memoryRss / $memoryUsage;
            
            $result = $results->createIssue();
            
            if ($fragmentation > $threshold) {
                $result->setPriority('high')
                    ->setCategory('Redis')
                    ->setIssue('High Redis memory fragmentation')
                    ->setDetails('Memory fragmentation indicates wasted memory')
                    ->setCurrentValue(sprintf('%.2f', $fragmentation))
                    ->setRecommendedValue(sprintf('< %.1f', $threshold))
                    ->add();
            }
        }
    }
    
    private function getRedisMemoryUsage(): int
    {
        // Implementation
        return 1024 * 1024 * 100; // 100MB
    }
    
    private function getRedisMemoryRss(): int  
    {
        // Implementation
        return 1024 * 1024 * 180; // 180MB
    }
}
```

### 6. Updated Command Implementation

```php
class PerformanceReviewCommand extends AbstractMagentoCommand
{
    private function loadAnalyzers(array $config): array
    {
        $analyzers = [];
        
        foreach ($config as $group => $groupAnalyzers) {
            foreach ($groupAnalyzers as $analyzerConfig) {
                $class = $analyzerConfig['class'];
                
                if (!class_exists($class)) {
                    if ($this->output->isVerbose()) {
                        $this->output->writeln(
                            sprintf('<comment>Analyzer class not found: %s</comment>', $class)
                        );
                    }
                    continue;
                }
                
                $analyzer = new $class();
                
                if (!$analyzer instanceof AnalyzerCheckInterface) {
                    continue;
                }
                
                // Set config if supported
                if ($analyzer instanceof ConfigAwareInterface && isset($analyzerConfig['config'])) {
                    $analyzer->setConfig($analyzerConfig['config']);
                }
                
                // Set dependencies if supported
                if ($analyzer instanceof DependencyAwareInterface) {
                    $analyzer->setDependencies($this->getDependencies());
                }
                
                $analyzers[$analyzerConfig['id']] = [
                    'instance' => $analyzer,
                    'description' => $analyzerConfig['description'] ?? '',
                    'group' => $group
                ];
            }
        }
        
        return $analyzers;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get analyzer configuration
        $config = $this->getConfig()['analyzers'] ?? [];
        
        // Add core analyzers to config if not overridden
        $config = $this->mergeWithCoreAnalyzers($config);
        
        // Load all analyzers
        $analyzers = $this->loadAnalyzers($config);
        
        // Create issue collection
        $issueCollection = new Collection($this->issueFactory);
        
        // Run analyzers
        foreach ($analyzers as $id => $analyzerData) {
            $output->write(sprintf('Running %s... ', $analyzerData['description']));
            
            try {
                $analyzerData['instance']->analyze($issueCollection);
                $output->writeln('<info>✓</info>');
            } catch (\Exception $e) {
                $output->writeln('<error>✗</error>');
                if ($output->isVerbose()) {
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                }
            }
        }
        
        // Generate report from collected issues
        $issues = $issueCollection->getIssues();
        $report = $this->reportGenerator->generateReport($issues);
        
        // ... rest remains the same
    }
}
```

## Migration Path

### Phase 1: Add Extension Support (1 week)
1. Create new interfaces (AnalyzerCheckInterface, ConfigAwareInterface)
2. Add Issue\Collection class
3. Update PerformanceReviewCommand to load custom analyzers
4. Keep existing analyzer system working in parallel

### Phase 2: Adapt Core Analyzers (1 week)
1. Create adapter to run old analyzers through new system
2. Gradually refactor core analyzers to new interface
3. Maintain backward compatibility

### Phase 3: Documentation & Examples (3 days)
1. Create documentation following n98-magerun2 wiki style
2. Provide 2-3 example custom analyzers
3. Update README with extension information

### Phase 4: Testing & Release (3 days)
1. Add tests for extension system
2. Test with various custom analyzers
3. Create migration guide for users

## Benefits of This Approach

1. **Familiar Pattern**: Developers already know n98-magerun2's extension pattern
2. **Simple Integration**: Just add YAML config and PHP class
3. **Minimal Disruption**: Existing functionality continues to work
4. **Community Ready**: Easy to share custom analyzers
5. **Consistent UX**: Follows n98-magerun2 conventions

## Example YAML Configuration in User's Project

```yaml
# app/etc/n98-magerun2.yaml or ~/.n98-magerun2.yaml
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      project-specific:
        - id: custom-module-check
          class: 'MyProject\PerformanceCheck\CustomModuleAnalyzer'
          description: 'Check our custom modules for issues'
        - id: api-rate-limits
          class: 'MyProject\PerformanceCheck\ApiRateLimitAnalyzer'
          description: 'Verify API rate limiting is configured'
          config:
            endpoints:
              - /rest/V1/products
              - /rest/V1/orders
            max_requests_per_minute: 100
```

## Documentation Example

### Creating a Custom Analyzer

1. Create your analyzer class implementing `AnalyzerCheckInterface`:

```php
<?php
namespace MyCompany\PerformanceCheck;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Model\Issue\Collection;

class CustomModuleAnalyzer implements AnalyzerCheckInterface
{
    public function analyze(Collection $results): void
    {
        // Your analysis logic
        $issue = $results->createIssue()
            ->setPriority('medium')
            ->setCategory('Modules')
            ->setIssue('Custom module issue found')
            ->setDetails('Description of the issue')
            ->add();
    }
}
```

2. Register in your n98-magerun2.yaml:

```yaml
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      custom:
        - id: my-custom-check
          class: 'MyCompany\PerformanceCheck\CustomModuleAnalyzer'
          description: 'My custom module check'
```

3. Run the performance review:

```bash
n98-magerun2.phar performance:review
```

Your custom analyzer will run alongside the core analyzers!