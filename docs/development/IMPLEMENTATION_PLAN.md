# Performance Review Module Implementation Plan for n98-magerun2

## Overview
Port the Magento 2 Performance Review module to work as an n98-magerun2 extension, maintaining all functionality while adapting to the n98-magerun2 architecture.

## Module Structure

### Current Magento Module Structure
```
Performance/Review/
├── Api/                    # Interfaces
├── Console/Command/        # CLI commands
├── Model/                  # Business logic & analyzers
├── phar-build/            # Standalone version
├── docs/                  # Documentation
└── etc/                   # Configuration
```

### Target n98-magerun2 Structure
```
~/.n98-magerun2/modules/performance-review/
├── n98-magerun2.yaml      # Module configuration
├── src/
│   ├── PerformanceReview/
│   │   ├── Command/       # n98-magerun2 commands
│   │   ├── Analyzer/      # Analysis logic
│   │   ├── Model/         # Data models
│   │   └── Util/          # Helper utilities
│   └── resources/         # Report templates
└── README.md
```

## Implementation Steps

### Phase 1: Core Infrastructure
1. Create base command class extending AbstractMagentoCommand
2. Port data models (Issue, IssueFactory)
3. Create report generator
4. Set up module configuration

### Phase 2: Analyzers
Port each analyzer with n98-magerun2 compatibility:
- ConfigurationAnalyzer
- PhpAnalyzer
- MysqlAnalyzer
- RedisAnalyzer
- DatabaseAnalyzer
- ModuleAnalyzer
- CodebaseAnalyzer
- FrontendAnalyzer
- IndexerCronAnalyzer
- ThirdPartyAnalyzer
- ApiAnalyzer

### Phase 3: Integration
1. Create main PerformanceReviewCommand
2. Implement output formatting
3. Add file export functionality
4. Test with various Magento installations

## Key Adaptations

### 1. Dependency Injection
- Magento DI → n98-magerun2 inject() method
- Use helper services where available
- Direct instantiation for simple services

### 2. Configuration Access
- Use Magento services through n98-magerun2's initialized environment
- Leverage existing n98-magerun2 helpers for database access

### 3. Logging
- Replace Magento logger with OutputInterface
- Optional file logging through custom implementation

### 4. Command Structure
```php
class PerformanceReviewCommand extends AbstractMagentoCommand
{
    protected function configure()
    {
        $this->setName('performance:review')
             ->setDescription('Run comprehensive performance review')
             ->addOption('category', 'c', InputOption::VALUE_OPTIONAL)
             ->addOption('output-file', 'o', InputOption::VALUE_OPTIONAL)
             ->addOption('details', 'd', InputOption::VALUE_NONE);
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if ($this->initMagento()) {
            // Run analyzers
        }
    }
}
```

## Benefits of n98-magerun2 Implementation
1. No need to modify Magento installation
2. Works with any Magento 2 version
3. Can be distributed as standalone module
4. Leverages n98-magerun2's existing infrastructure
5. Easy to install and update

## Timeline
- Phase 1: 2 hours
- Phase 2: 4 hours
- Phase 3: 2 hours
- Testing: 2 hours

Total estimated time: 10 hours