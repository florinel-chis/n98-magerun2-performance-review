# Reference Documentation

Technical specifications and version history.

## 📋 Contents

### [Changelog](./changelog.md)
Version history and release notes:
- Release versions
- New features
- Bug fixes
- Breaking changes
- Migration guides

### [Claude AI Guide](./claude-ai-guide.md)
Instructions for AI assistants working with this codebase:
- Architecture overview
- Common tasks and workflows
- Code patterns
- Configuration system
- Testing patterns
- Troubleshooting procedures

## 📚 Quick Reference

### Core Interfaces

```php
// Primary interface (v2.0)
interface AnalyzerCheckInterface {
    public function analyze(Collection $results): void;
}

// Optional: Configuration support
interface ConfigAwareInterface {
    public function setConfig(array $config): void;
}

// Optional: Magento dependencies
interface DependencyAwareInterface {
    public function setDependencies(array $dependencies): void;
}
```

### 11 Analysis Categories

1. **config** - Configuration settings
2. **database** - Database size and structure
3. **modules** - Third-party modules
4. **codebase** - Code organization
5. **frontend** - Asset optimization
6. **indexing** - Indexers and queues
7. **php** - PHP environment
8. **mysql** - MySQL configuration
9. **redis** - Redis setup
10. **api** - API integrations
11. **thirdparty** - Known extensions

### Priority Levels

| Priority | Use Case | Example |
|----------|----------|---------|
| **high** | Critical performance/security | Developer mode in production |
| **medium** | Important optimization | Large database (20-50GB) |
| **low** | Best practice | Image optimization disabled |

## 🔍 API Reference

### Issue Builder

```php
$results->createIssue()
    ->setPriority('high|medium|low')
    ->setCategory('Category Name')
    ->setIssue('Short description')
    ->setDetails('Detailed explanation')
    ->setCurrentValue('Current state')
    ->setRecommendedValue('Desired state')
    ->add();
```

### Available Dependencies

- `deploymentConfig` - App deployment config
- `appState` - Application state
- `scopeConfig` - Store configuration
- `resourceConnection` - Database connection
- `moduleList` - Installed modules
- `filesystem` - File operations
- `indexerRegistry` - Indexer management
- And more (see Claude AI Guide)

## 📚 Related Documentation

- [User Guide](../user-guide/) - Using the module
- [Developer Guide](../developer-guide/) - Contributing
- [Main Index](../README.md) - Full documentation map
