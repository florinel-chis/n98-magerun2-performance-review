# Performance Review Module for n98-magerun2

This module adds a comprehensive performance review command to n98-magerun2 that analyzes your Magento 2 installation and provides detailed recommendations for optimization.

## Installation

1. Create the modules directory if it doesn't exist:
   ```bash
   mkdir -p ~/.n98-magerun2/modules
   ```

2. Copy this module to the modules directory:
   ```bash
   cp -r performance-review ~/.n98-magerun2/modules/
   ```

3. Verify the module is loaded:
   ```bash
   n98-magerun2.phar list performance
   ```

## Usage

### Full Performance Review
Run a complete performance analysis:
```bash
n98-magerun2.phar performance:review
```

### Category-Specific Analysis
Analyze specific categories only:
```bash
# Configuration settings
n98-magerun2.phar performance:review --category=config

# Database performance
n98-magerun2.phar performance:review --category=database

# Module analysis
n98-magerun2.phar performance:review --category=modules

# Other categories: codebase, frontend, indexing, php, mysql, redis, api, thirdparty
```

### Save Report to File
```bash
n98-magerun2.phar performance:review --output-file=performance-report.txt
```

### Additional Options
```bash
# Disable colored output
n98-magerun2.phar performance:review --no-color

# Show detailed information for each issue
n98-magerun2.phar performance:review --details
```

## Features

### Comprehensive Analysis
The module analyzes 11 different aspects of your Magento 2 installation:

1. **Configuration** - Application mode, cache backends, session storage, minification settings
2. **Database** - Size analysis, table optimization, product/category counts, log tables
3. **Modules** - Third-party module count, performance-impacting modules, duplicate functionality
4. **Codebase** - Directory sizes, custom code analysis, core modifications, media files
5. **Frontend** - JS/CSS optimization, image formats, lazy loading, theme management
6. **Indexing** - Indexer status, cron health, stuck jobs, schedule optimization
7. **PHP** - Version, memory limits, extensions, OPcache configuration
8. **MySQL** - Version, buffer pool, query optimization, storage engines
9. **Redis** - Configuration, database separation, compression, persistence
10. **API** - Integration management, OAuth tokens, rate limiting
11. **Third-party** - Extension compatibility, code quality, development tools

### Professional Report Format
- Table-based layout with clear categories
- Color-coded priorities: 🔴 High, 🟡 Medium, 🟢 Low
- Current vs Recommended values
- Detailed explanations for each issue
- Summary with total issue counts

### Exit Codes
- `0` - Success (no high priority issues found)
- `1` - High priority issues detected

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

## Requirements

- n98-magerun2
- Magento 2.3.x or higher
- PHP 7.3 or higher

## License

MIT License