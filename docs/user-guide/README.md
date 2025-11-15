# User Guide

Learn how to use and extend the Performance Review module for your specific needs.

## 📋 Contents

### [Custom Analyzers](./custom-analyzers.md)
Complete guide to creating custom performance analyzers:
- Architecture overview
- Interface requirements
- Creating your first analyzer
- Configuration options
- Dependency injection
- Best practices and patterns

### [Troubleshooting](./troubleshooting.md)
Solutions to common issues:
- Analyzer not loading
- Configuration not applying
- Dependency injection errors
- Memory and performance issues
- Command failures

### [YAML Configuration](./yaml-configuration.md)
Understanding and configuring the module:
- Configuration file locations
- Configuration precedence
- Analyzer registration
- Threshold customization
- Advanced configuration patterns

## 🎯 Common Tasks

### Create a Simple Custom Analyzer

```php
<?php
namespace MyCompany\Analyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Model\Issue\Collection;

class MyAnalyzer implements AnalyzerCheckInterface
{
    public function analyze(Collection $results): void
    {
        // Your analysis logic
        $results->createIssue()
            ->setPriority('medium')
            ->setCategory('Custom')
            ->setIssue('Issue description')
            ->add();
    }
}
```

### Register in YAML

```yaml
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      custom:
        - id: my-analyzer
          class: 'MyCompany\Analyzer\MyAnalyzer'
          description: 'My custom check'
```

## 💡 Examples

See real-world examples in the [Examples](../examples/) section, particularly:
- [Unused Index Analyzer](../examples/unused-index-analyzer/) - Gold standard reference

## 📚 Related Documentation

- [Developer Guide](../developer-guide/) - Contributing and testing
- [Getting Started](../getting-started/) - Installation and setup
- [Main Index](../README.md) - Full documentation map
