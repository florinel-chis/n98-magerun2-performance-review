# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0-beta] - 2025-06-20

### Added
- Complete rewrite as n98-magerun2 module
- 11 comprehensive analyzers covering all aspects of Magento performance
- Color-coded priority system (High/Medium/Low)
- Professional report generation with table formatting
- Support for category-specific analysis via `--category` option
- File output support via `--output-file` option
- Detailed mode via `--details` option
- Exit code based on issue severity

### Changed
- Migrated from standalone tool to n98-magerun2 module architecture
- Improved code organization with separate analyzer classes
- Enhanced dependency injection using n98-magerun2's inject() method
- Updated all thresholds based on current Magento best practices

### Known Issues
- Silent exception handling in analyzers makes debugging difficult
- High memory usage on large catalogs (100k+ products)
- Version detection falls back to hardcoded values
- Fixed-width report columns may break with long content
- No configuration file support for custom thresholds
- Exit code behavior may not suit all use cases

### Technical Debt
- No unit or integration tests
- Database queries not optimized for performance
- Some methods load entire collections into memory
- Error handling needs improvement
- Missing JSON/XML output formats

## [1.0.0] - Previous Version
- Original standalone implementation
- Basic performance checks
- Simple text output