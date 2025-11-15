# Developer Guide

Resources for contributing to the Performance Review module.

## 📋 Contents

### [Development Workflow](./development-workflow.md)
Complete contribution process:
- Setting up development environment
- Coding standards
- Git workflow and branching
- Pull request process
- Code review guidelines
- Release process

### [Testing Guide](./testing-guide.md)
Comprehensive testing instructions:
- Test structure and organization
- Running unit tests
- Writing analyzer tests
- Integration testing
- Test coverage
- CI/CD integration

### [YAML Internals](./yaml-internals.md)
Deep dive into configuration system:
- How n98-magerun2 loads YAML
- Configuration merge algorithm
- File discovery process
- Debugging configuration
- Performance considerations

## 🛠️ Development Setup

```bash
# Clone repository
git clone https://github.com/florinel-chis/n98-magerun2-performance-review.git
cd n98-magerun2-performance-review

# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run code quality checks
vendor/bin/phpcs
vendor/bin/phpstan analyse
```

## 🧪 Testing Your Changes

```bash
# Run specific test
vendor/bin/phpunit tests/Unit/Analyzer/YourAnalyzerTest.php

# Run all tests with coverage
vendor/bin/phpunit --coverage-html coverage/

# Test in real Magento installation
cd /path/to/magento
n98-magerun2.phar performance:review --category=custom -vvv
```

## 📝 Contribution Checklist

Before submitting a pull request:
- [ ] Code follows PSR-12 standards
- [ ] All tests pass
- [ ] New tests added for new functionality
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] No backwards compatibility breaks (or clearly documented)

## 🏗️ Architecture Overview

```
Command (PerformanceReviewCommand)
    ↓
Analyzers (11 core + custom)
    ↓ implements AnalyzerCheckInterface
Issue Collection
    ↓ uses IssueBuilder
Report Generator
    ↓
Console Output
```

## 📚 Related Documentation

- [User Guide](../user-guide/) - Using and extending
- [Examples](../examples/) - Reference implementations
- [Main Index](../README.md) - Full documentation map
