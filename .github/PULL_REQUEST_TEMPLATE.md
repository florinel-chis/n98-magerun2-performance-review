## Summary

This PR implements extensibility support for the Performance Review module, allowing developers to create and register custom analyzers without modifying the core module. This addresses issue #1.

## Changes

### Core Features
- ✅ Added `AnalyzerCheckInterface` for custom analyzer implementation
- ✅ Added optional interfaces: `ConfigAwareInterface` and `DependencyAwareInterface`
- ✅ Implemented `Issue\Collection` and `IssueBuilder` for fluent issue creation
- ✅ Created `LegacyAnalyzerAdapter` for backward compatibility
- ✅ Refactored `PerformanceReviewCommand` to support dynamic analyzer loading

### New CLI Options
- `--list-analyzers` - List all available analyzers (core and custom)
- `--skip-analyzer=ID` - Skip specific analyzers by ID

### Documentation
- Comprehensive `CUSTOM_ANALYZERS.md` guide
- Testing guides and troubleshooting documentation
- Example custom analyzers (Redis memory, Elasticsearch health)
- Utility scripts for installation and debugging

### Project Organization
- Added `.gitignore` for better repository hygiene
- Reorganized documentation into logical structure
- Added MIT license

## Testing

1. Install the module:
   ```bash
   ~/.n98-magerun2/modules/performance-review/docs/scripts/install-feature.sh
   ```

2. Run quick test:
   ```bash
   cd /path/to/magento
   ~/.n98-magerun2/modules/performance-review/docs/scripts/test-setup.sh
   n98-magerun2.phar performance:review --list-analyzers
   ```

3. See [TESTING_GUIDE.md](TESTING_GUIDE.md) for comprehensive testing instructions.

## Breaking Changes

None. All existing functionality remains intact. The extensibility feature is purely additive.

## Related Issues

Fixes #1 - Request for extensible analyzer architecture

## Checklist

- [x] Code follows project style guidelines
- [x] Documentation has been updated
- [x] Changes are backward compatible
- [x] Example implementations provided
- [x] Basic unit test included
- [ ] Full test coverage (planned for future iteration)