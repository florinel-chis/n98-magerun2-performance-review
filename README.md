# Performance Review Module for n98-magerun2 (v2.0)

A comprehensive performance analysis tool for Magento 2, Mage-OS, and Adobe Commerce installations. This n98-magerun2 module performs an in-depth review of your Magento installation and provides actionable recommendations for optimization.

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
│       ├── Analyzer/         # Individual analyzer classes
│       ├── Command/          # CLI commands
│       ├── Model/            # Data models
│       └── Util/             # Helper utilities
└── README.md                 # This file
```

### Extending the Module

To add new analyzers:
1. Create a new analyzer class in `src/PerformanceReview/Analyzer/`
2. Implement the `analyze()` method returning an array of `IssueInterface`
3. Add the analyzer to `PerformanceReviewCommand`
4. Update the category list if adding a new category

## Requirements

- **n98-magerun2**: Latest version recommended
- **Magento**: 2.3.x or higher (including Mage-OS and Adobe Commerce)
- **PHP**: 7.4 or higher (8.1+ recommended)
- **Memory**: Sufficient to load Magento environment

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

## Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Submit a pull request

## License

MIT License - See LICENSE file for details

## Credits

This module is a port of the original Magento 2 Performance Review module, adapted for n98-magerun2 to provide easier installation and broader compatibility.