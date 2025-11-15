# Examples

Reference implementations and code samples for custom analyzers.

## 📋 Available Examples

### [Unused Index Analyzer](./unused-index-analyzer/) ⭐ **Gold Standard**
Production-ready analyzer that detects unused database indexes.

**Features:**
- ✅ All three interfaces implemented (AnalyzerCheckInterface, ConfigAwareInterface, DependencyAwareInterface)
- ✅ 21 comprehensive unit tests
- ✅ Full configuration support
- ✅ Comprehensive error handling
- ✅ Complete documentation

**Use this as your reference when creating custom analyzers.**

**Files:**
- [README.md](./unused-index-analyzer/README.md) - Implementation details and usage
- [setup.md](./unused-index-analyzer/setup.md) - Installation guide

**Actual implementation:** `examples/CustomAnalyzers/UnusedIndexAnalyzer.php`

## 🎯 Example Patterns Demonstrated

### Basic Analyzer
```php
class SimpleAnalyzer implements AnalyzerCheckInterface
{
    public function analyze(Collection $results): void
    {
        // Simple detection logic
    }
}
```

### Configurable Analyzer
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
}
```

### Magento-Aware Analyzer
```php
class MagentoAnalyzer implements
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
        $connection = $this->dependencies['resourceConnection']
            ->getConnection();
        // Use Magento services
    }
}
```

## 📚 Additional Resources

### Other Example Analyzers (in codebase)

Check `examples/CustomAnalyzers/` for:
- `RedisMemoryAnalyzer.php` - Redis memory monitoring
- `ElasticsearchHealthAnalyzer.php` - Elasticsearch health checks

### Related Documentation

- [Custom Analyzers Guide](../user-guide/custom-analyzers.md) - Complete guide
- [Testing Guide](../developer-guide/testing-guide.md) - How to test analyzers
- [Main Index](../README.md) - Full documentation map

## 🚀 Quick Start

1. **Study the example**: Read [Unused Index Analyzer](./unused-index-analyzer/README.md)
2. **Copy the pattern**: Use it as a template for your analyzer
3. **Customize**: Adapt the logic to your needs
4. **Test**: Follow the testing guide
5. **Deploy**: Register in your YAML configuration

## 💡 Best Practices

From the examples, learn:
- ✅ Comprehensive error handling
- ✅ Configurable thresholds
- ✅ Clear issue descriptions
- ✅ Appropriate priority levels
- ✅ Memory-efficient queries
- ✅ Graceful dependency handling
- ✅ Complete test coverage
