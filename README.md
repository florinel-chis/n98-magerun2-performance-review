# Performance Review Module for n98-magerun2 (v2.0)

A comprehensive performance analysis tool for Magento 2, Mage-OS, and Adobe Commerce installations. This n98-magerun2 module performs an in-depth review of your Magento installation and provides actionable recommendations for optimization.

**Current Status**: Beta - Functional with known limitations (see below)

## Overview

The Performance Review module analyzes 11 different aspects of your Magento installation to identify performance bottlenecks, configuration issues, and optimization opportunities. It provides a professional report with color-coded priorities and specific recommendations.

## Installation

1. Create the n98-magerun2 modules directory if it doesn't exist:
   ```bash
   mkdir -p ~/.n98-magerun2/modules
   ```

2. Clone or copy this module to the modules directory:
   ```bash
   cp -r performance-review ~/.n98-magerun2/modules/
   ```

3. Verify the module is loaded:
   ```bash
   n98-magerun2.phar list performance
   ```

   You should see:
   - `performance:review` - Run a comprehensive performance review
   - `performance:show-title` - Display module title (demo command)

## Usage

### Basic Usage

Run a complete performance analysis from your Magento root directory:
```bash
n98-magerun2.phar performance:review
```

Or specify a Magento installation path:
```bash
n98-magerun2.phar performance:review --root-dir=/path/to/magento
```

### Category-Specific Analysis

Focus on specific areas by using the `--category` option:
```bash
# Configuration settings
n98-magerun2.phar performance:review --category=config

# Database performance
n98-magerun2.phar performance:review --category=database

# Module analysis
n98-magerun2.phar performance:review --category=modules

# Available categories:
# config, database, modules, codebase, frontend, indexing, 
# php, mysql, redis, api, thirdparty
```

### Output Options

Save the report to a file:
```bash
n98-magerun2.phar performance:review --output-file=performance-report.txt
```

Disable colored output (useful for CI/CD pipelines):
```bash
n98-magerun2.phar performance:review --no-color
```

Show detailed explanations for each issue:
```bash
n98-magerun2.phar performance:review --details
```

Combine options as needed:
```bash
n98-magerun2.phar performance:review --category=database --details --output-file=db-report.txt
```

List all available analyzers (including custom ones):
```bash
n98-magerun2.phar performance:review --list-analyzers
```

Skip specific analyzers:
```bash
n98-magerun2.phar performance:review --skip-analyzer=redis --skip-analyzer=api
```

## Custom Analyzers

The module supports custom analyzers, allowing you to add project-specific performance checks without modifying the core module. This follows n98-magerun2's extensibility patterns.

### Quick Example

1. Create your analyzer class:
```php
<?php
namespace MyCompany\Analyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Model\Issue\Collection;

class CustomCacheAnalyzer implements AnalyzerCheckInterface
{
    public function analyze(Collection $results): void
    {
        // Your analysis logic
        $results->createIssue()
            ->setPriority('medium')
            ->setCategory('Cache')
            ->setIssue('Custom cache issue detected')
            ->setDetails('Description of the issue')
            ->add();
    }
}
```

2. Register in `n98-magerun2.yaml`:
```yaml
autoloaders_psr4:
  MyCompany\Analyzer\: 'app/code/MyCompany/Analyzer'

commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      custom:
        - id: custom-cache
          class: 'MyCompany\Analyzer\CustomCacheAnalyzer'
          description: 'Check custom cache configuration'
```

### Example Analyzers Included

The module includes several complete, production-ready example analyzers in `examples/CustomAnalyzers/`:

- **UnusedIndexAnalyzer** ⭐ **Gold Standard** - Detects unused database indexes that waste storage and slow down writes
  - Comprehensive test coverage (21 unit tests)
  - Configurable thresholds for priority levels
  - Production-ready with proper error handling
  - See [docs/examples/unused-index-analyzer/](docs/examples/unused-index-analyzer/) for detailed documentation

- **RedisMemoryAnalyzer** - Checks Redis memory usage and fragmentation
- **ElasticsearchHealthAnalyzer** - Monitors Elasticsearch cluster health

These examples demonstrate best practices for custom analyzer development and can be used as reference implementations.

See [docs/user-guide/custom-analyzers.md](docs/user-guide/custom-analyzers.md) for detailed documentation on creating custom analyzers.

## Analysis Categories

The module performs comprehensive analysis across 11 categories:

### 1. **Configuration Analysis** (`config`)
- Application mode (developer/production)
- Cache backend configuration (Redis vs File)
- Session storage optimization
- JS/CSS minification and merging
- Static content deployment settings
- Cache types status

### 2. **Database Analysis** (`database`)
- Total database size (warnings at 20GB/50GB)
- Individual table size analysis
- Product and category counts
- URL rewrite optimization
- Log table management
- Table optimization needs

### 3. **Module Analysis** (`modules`)
- Third-party module count and impact
- Performance-affecting modules detection
- Disabled modules still in codebase
- Duplicate functionality identification
- Module conflicts and dependencies

### 4. **Codebase Analysis** (`codebase`)
- Generated content size management
- Var directory cleanup needs
- Custom code volume and organization
- Core modification detection
- Media file optimization
- Code pool usage

### 5. **Frontend Analysis** (`frontend`)
- JavaScript optimization settings
- CSS optimization and merging
- HTML minification
- Image optimization and WebP support
- Lazy loading implementation
- Theme count and management

### 6. **Indexing & Cron Analysis** (`indexing`)
- Indexer status and mode
- Cron job execution health
- Stuck or failed jobs
- Schedule optimization
- Queue processing status

### 7. **PHP Configuration** (`php`)
- PHP version compatibility (7.4+, recommends 8.1+)
- Memory limits (2GB min, 4GB recommended)
- Required extensions verification
- OPcache configuration
- Performance extensions (APCu, Redis)
- Execution time limits

### 8. **MySQL Configuration** (`mysql`)
- MySQL/MariaDB version check
- InnoDB buffer pool sizing
- Connection limits and caching
- Query cache settings
- Slow query logging
- Table cache optimization

### 9. **Redis Configuration** (`redis`)
- Redis server detection and version
- Database separation (cache/session/FPC)
- Compression settings
- Connection persistence
- Memory usage patterns
- Extension type (phpredis vs Predis)

### 10. **API Analysis** (`api`)
- Active integration count
- OAuth token management
- Async API configuration
- Rate limiting implementation
- Token security and cleanup

### 11. **Third-party Analysis** (`thirdparty`)
- Known problematic extensions
- Compatibility checks
- Code quality indicators
- Development tool detection
- Security considerations

## Report Format

### Professional Output
The module generates a structured report with:
- Professional header with timestamp
- Category-separated sections
- Table format with clear columns
- Color-coded priority indicators:
  - 🔴 **High Priority**: Critical issues requiring immediate attention
  - 🟡 **Medium Priority**: Important optimizations to consider
  - 🟢 **Low Priority**: Minor improvements or best practices
- Current vs Recommended values
- Detailed issue descriptions
- Summary with total counts and exit codes

### Exit Codes
- `0` - Success (no high priority issues found)
- `1` - High priority issues detected (useful for CI/CD pipelines)

## Example Output

```
================================================================================
                    MAGENTO 2 PERFORMANCE REVIEW REPORT
================================================================================
Generated: 2025-06-05 10:30:45
================================================================================

== Config ==
--------------------------------------------------------------------------------
Priority   | Recommendation                           | Details                  
----------+------------------------------------------+---------------------------
High       | Switch from developer mode to production | Developer mode impacts...
           |                                          | Current: developer
           |                                          | Recommended: production
----------+------------------------------------------+---------------------------

== Database ==
--------------------------------------------------------------------------------
Priority   | Recommendation                           | Details                  
----------+------------------------------------------+---------------------------
Medium     | Database size exceeds 20GB               | Large database size may...
           |                                          | Current: 25.3 GB
           |                                          | Recommended: Under 20GB
----------+------------------------------------------+---------------------------

[... more issues ...]

================================================================================
SUMMARY: Found 15 issues (3 high, 8 medium, 4 low)
================================================================================
```

## Technical Details

### Module Architecture

The module follows n98-magerun2's extension architecture:
- **Commands**: Extend `AbstractMagentoCommand` for Magento environment access
- **Analyzers**: Separate analyzer classes for each category
- **Models**: Issue representation and report generation
- **Dependency Injection**: Uses n98-magerun2's `inject()` method for Magento services

### File Structure
```
performance-review/
├── n98-magerun2.yaml         # Module configuration
├── src/
│   └── PerformanceReview/
│       ├── Api/              # Interfaces for extensibility
│       ├── Analyzer/         # Individual analyzer classes
│       ├── Command/          # CLI commands
│       ├── Model/            # Data models
│       │   └── Issue/        # Issue collection classes
│       └── Util/             # Helper utilities
├── examples/                 # Example custom analyzers
│   ├── CustomAnalyzers/      # Example analyzer implementations
│   └── n98-magerun2.yaml.example
├── docs/                     # Documentation (see docs/README.md)
│   ├── getting-started/      # Setup and quick test guides
│   ├── user-guide/           # Custom analyzers, troubleshooting, YAML config
│   ├── developer-guide/      # Development workflow, testing, internals
│   ├── examples/             # Example documentation
│   ├── reference/            # Changelog and technical references
│   └── archive/              # Historical planning documents
├── tests/                    # Test files
│   └── Unit/                 # Unit tests
├── .github/                  # GitHub templates
│   └── PULL_REQUEST_TEMPLATE.md
├── .gitignore                # Git ignore rules
├── CLAUDE.md                 # AI assistant guide
└── README.md                 # This file
```

### Extending the Module

#### Option 1: Custom Analyzers (Recommended)
Create custom analyzers without modifying the core module:
1. Implement `AnalyzerCheckInterface` in your own namespace
2. Register via `n98-magerun2.yaml` configuration
3. No core modifications needed
4. See [docs/user-guide/custom-analyzers.md](docs/user-guide/custom-analyzers.md) for details

#### Option 2: Core Contribution
To add analyzers to the core module:
1. Create a new analyzer class in `src/PerformanceReview/Analyzer/`
2. Implement the `analyze()` method returning an array of `IssueInterface`
3. Add the analyzer to `PerformanceReviewCommand`
4. Update the category list if adding a new category
5. Submit a pull request

## Requirements

- **n98-magerun2**: v7.0.0 or higher
- **Magento**: 2.3.x - 2.4.7 (including Mage-OS 1.0.x and Adobe Commerce)
- **PHP**: 7.4 or higher (8.1+ recommended for better performance)
- **Memory**: Minimum 2GB PHP memory limit (4GB recommended for large catalogs)
- **Permissions**: Read access to Magento installation and database

## Troubleshooting

### Module Not Found
If the module commands don't appear:
1. Check the module is in the correct directory: `~/.n98-magerun2/modules/performance-review/`
2. Verify the `n98-magerun2.yaml` file exists
3. Clear any caches: `n98-magerun2.phar cache:clear`

### Memory Issues
For large installations, increase PHP memory limit:
```bash
php -d memory_limit=4G n98-magerun2.phar performance:review
```

### Permission Errors
Ensure the module files are readable:
```bash
chmod -R 755 ~/.n98-magerun2/modules/performance-review
```

## Known Issues and Limitations

### Current Limitations

1. **Error Handling**: Exceptions in analyzers are silently caught, making debugging difficult
2. **Memory Usage**: Some analyzers may consume significant memory on large catalogs (100k+ products)
3. **No Configuration**: All thresholds are hardcoded and cannot be customized
4. **Limited Output Formats**: Only text output is supported (no JSON/XML)
5. **No Test Coverage**: The module currently lacks unit and integration tests
6. **Exit Codes**: Returns failure exit code (1) when high priority issues are found, which may not be suitable for all use cases

### Technical Debt

- Version detection logic needs improvement (currently falls back to hardcoded values)
- Database queries could be optimized for better performance
- Report formatting uses fixed column widths that may break with long content
- Some analyzer methods load entire collections into memory instead of using count queries

### Planned Improvements

- Add comprehensive test coverage
- Implement configurable thresholds via YAML configuration
- Add JSON/XML output formats for automation
- Improve error handling and logging
- Optimize database queries and memory usage
- Add progress indicators for long-running analyses

## Documentation

📚 **Complete documentation is available in [docs/](docs/README.md)**

### Quick Links

**Getting Started:**
- [Setup Guide](docs/getting-started/setup.md) - Installation and configuration
- [Quick Test](docs/getting-started/quick-test.md) - Verify your installation

**User Guide:**
- [Custom Analyzers](docs/user-guide/custom-analyzers.md) - Create your own performance checks
- [Troubleshooting](docs/user-guide/troubleshooting.md) - Common issues and solutions
- [YAML Configuration](docs/user-guide/yaml-configuration.md) - Configure analyzers

**Developer Guide:**
- [Development Workflow](docs/developer-guide/development-workflow.md) - Contributing guidelines
- [Testing Guide](docs/developer-guide/testing-guide.md) - Write and run tests
- [YAML Internals](docs/developer-guide/yaml-internals.md) - How configuration loading works

**Examples & Reference:**
- [Unused Index Analyzer](docs/examples/unused-index-analyzer/) - Gold standard example ⭐
- [Changelog](docs/reference/changelog.md) - Version history
- [Full Documentation Index](docs/README.md) - Complete documentation map

## Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Submit a pull request

### Development Guidelines

- Follow PSR-12 coding standards
- Add unit tests for new analyzers
- Update documentation for new features
- Consider memory usage for large installations
- Ensure compatibility with Magento 2.3.x - 2.4.x

## License

MIT License - See LICENSE file for details

## Credits

This module is a port of the original Magento 2 Performance Review module, adapted for n98-magerun2 to provide easier installation and broader compatibility.