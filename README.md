# Performance Review Module for n98-magerun2

A comprehensive performance analysis tool for Magento 2 installations, ported from the original Magento module to work as an n98-magerun2 extension.

## Features

- **Configuration Analysis**: Checks cache backends, session storage, JS/CSS optimization settings
- **Mode Detection**: Verifies if Magento is running in production mode
- **Cache Status**: Identifies disabled cache types
- **Performance Recommendations**: Provides actionable recommendations for each issue found
- **Priority Levels**: Issues are categorized as High, Medium, or Low priority
- **Detailed Reports**: Optional detailed view shows additional context for each issue

## Installation

1. Create the module directory in your n98-magerun2 modules folder:
```bash
mkdir -p ~/.n98-magerun2/modules/
```

2. Copy this module to the modules directory:
```bash
cp -r performance-review ~/.n98-magerun2/modules/
```

3. Verify installation:
```bash
n98-magerun2.phar list | grep performance
```

## Usage

### Basic Usage

Run a full performance review:
```bash
n98-magerun2.phar performance:review
```

Run from any directory by specifying Magento root:
```bash
n98-magerun2.phar performance:review --root-dir=/path/to/magento
```

### Options

- `--category, -c`: Run specific category only (config, modules, codebase, database, frontend, indexing, thirdparty, api, php, mysql, redis)
- `--output-file, -o`: Save report to file instead of displaying
- `--details, -d`: Show detailed information for issues
- `--no-color`: Disable colored output

### Examples

Run configuration analysis only:
```bash
n98-magerun2.phar performance:review --category=config
```

Save report to file:
```bash
n98-magerun2.phar performance:review --output-file=performance-report.txt
```

Show detailed information:
```bash
n98-magerun2.phar performance:review --details
```

## Currently Implemented Analyzers

### Configuration Analyzer
- Application mode check
- Cache backend configuration (Redis vs File)
- Page cache configuration
- Session storage configuration
- JS/CSS minification and merging settings
- Flat catalog settings (for older Magento versions)
- Cache types status

## Planned Analyzers

The following analyzers are planned for future releases:

- **PHP Configuration Analyzer**: Memory limits, OPcache settings, execution time
- **MySQL Configuration Analyzer**: Query cache, buffer sizes, connection limits
- **Redis Configuration Analyzer**: Memory usage, persistence settings
- **Database Analyzer**: Table sizes, missing indexes, query performance
- **Module Analyzer**: Third-party module conflicts, deprecated modules
- **Codebase Analyzer**: Custom code quality, overrides, patches
- **Frontend Analyzer**: Asset optimization, lazy loading, critical CSS
- **Indexer & Cron Analyzer**: Indexer status, cron schedule issues
- **API Analyzer**: REST/GraphQL configuration, rate limiting
- **Third Party Analyzer**: Extension compatibility, known issues

## Report Output

The tool generates a comprehensive report showing:

1. **Summary**: Total issues count by priority
2. **Categorized Issues**: Issues grouped by category with descriptions
3. **Priority Indicators**:
   - `[HIGH]` - Critical issues that significantly impact performance
   - `[MEDIUM]` - Important optimizations that should be addressed
   - `[LOW]` - Minor improvements or optional optimizations
4. **Key Recommendations**: Actionable steps for high-priority issues

## Exit Codes

- `0`: Success, no high-priority issues found
- `1`: High-priority issues detected that require attention

## Development

### Adding New Analyzers

1. Create analyzer class in `src/PerformanceReview/Analyzer/`
2. Implement analyze method returning array of IssueInterface objects
3. Add analyzer to PerformanceReviewCommand
4. Update module configuration if needed

### Issue Creation

Use the IssueFactory to create issues:

```php
$this->issueFactory->createHighPriority(
    'Issue Title',
    'Detailed description',
    IssueInterface::CATEGORY_CONFIGURATION,
    'Recommendation for fixing',
    ['additional' => 'details']
);
```

## License

This module is open-source software licensed under the MIT license.

## Contributing

Contributions are welcome! Please submit pull requests or issues on GitHub.

## Credits

Based on the original Performance Review module for Magento 2, adapted for n98-magerun2 by the community.