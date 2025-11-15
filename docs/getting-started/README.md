# Getting Started

Quick setup and verification guides to get you running with the Performance Review module.

## 📋 Contents

### [Setup Guide](./setup.md)
Complete installation and configuration instructions:
- Installation methods
- Configuration file setup
- Autoloader configuration
- First run verification

### [Quick Test](./quick-test.md)
Verify your installation is working correctly:
- Run the command
- Verify analyzers load
- Test custom analyzer registration
- Troubleshoot common issues

## 🎯 Quick Start

```bash
# 1. Install the module
composer require florinel-chis/n98-magerun2-performance-review

# 2. Run performance review
n98-magerun2.phar performance:review

# 3. List available analyzers
n98-magerun2.phar performance:review --list-analyzers
```

## 📚 Next Steps

After completing setup:
- **Create custom analyzers**: See [Custom Analyzers](../user-guide/custom-analyzers.md)
- **Configure thresholds**: See [YAML Configuration](../user-guide/yaml-configuration.md)
- **Run tests**: See [Testing Guide](../developer-guide/testing-guide.md)

## 🆘 Need Help?

- Common issues: [Troubleshooting](../user-guide/troubleshooting.md)
- Documentation index: [Main Index](../README.md)
