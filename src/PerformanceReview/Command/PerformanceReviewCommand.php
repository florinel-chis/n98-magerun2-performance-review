<?php
declare(strict_types=1);

namespace PerformanceReview\Command;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use PerformanceReview\Analyzer\ConfigurationAnalyzer;
use PerformanceReview\Analyzer\DatabaseAnalyzer;
use PerformanceReview\Analyzer\ModuleAnalyzer;
use PerformanceReview\Analyzer\CodebaseAnalyzer;
use PerformanceReview\Analyzer\FrontendAnalyzer;
use PerformanceReview\Analyzer\IndexerCronAnalyzer;
use PerformanceReview\Analyzer\PhpConfigurationAnalyzer;
use PerformanceReview\Analyzer\MysqlConfigurationAnalyzer;
use PerformanceReview\Analyzer\RedisConfigurationAnalyzer;
use PerformanceReview\Analyzer\ApiAnalyzer;
use PerformanceReview\Analyzer\ThirdPartyAnalyzer;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\ReportGenerator;
use PerformanceReview\Model\Issue\Collection;
use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Api\ConfigAwareInterface;
use PerformanceReview\Api\DependencyAwareInterface;
use PerformanceReview\Analyzer\LegacyAnalyzerAdapter;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\State;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as ScheduleCollectionFactory;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * Performance review command for n98-magerun2
 */
class PerformanceReviewCommand extends AbstractMagentoCommand
{
    /**
     * Exit codes
     */
    private const EXIT_CODE_SUCCESS = 0;
    private const EXIT_CODE_FAILURE = 1;
    
    /**
     * @var ConfigurationAnalyzer
     */
    private ?ConfigurationAnalyzer $configurationAnalyzer = null;
    
    /**
     * @var DatabaseAnalyzer
     */
    private ?DatabaseAnalyzer $databaseAnalyzer = null;
    
    /**
     * @var ModuleAnalyzer
     */
    private ?ModuleAnalyzer $moduleAnalyzer = null;
    
    /**
     * @var CodebaseAnalyzer
     */
    private ?CodebaseAnalyzer $codebaseAnalyzer = null;
    
    /**
     * @var FrontendAnalyzer
     */
    private ?FrontendAnalyzer $frontendAnalyzer = null;
    
    /**
     * @var IndexerCronAnalyzer
     */
    private ?IndexerCronAnalyzer $indexerCronAnalyzer = null;
    
    /**
     * @var PhpConfigurationAnalyzer
     */
    private ?PhpConfigurationAnalyzer $phpConfigurationAnalyzer = null;
    
    /**
     * @var MysqlConfigurationAnalyzer
     */
    private ?MysqlConfigurationAnalyzer $mysqlConfigurationAnalyzer = null;
    
    /**
     * @var RedisConfigurationAnalyzer
     */
    private ?RedisConfigurationAnalyzer $redisConfigurationAnalyzer = null;
    
    /**
     * @var ApiAnalyzer
     */
    private ?ApiAnalyzer $apiAnalyzer = null;
    
    /**
     * @var ThirdPartyAnalyzer
     */
    private ?ThirdPartyAnalyzer $thirdPartyAnalyzer = null;
    
    /**
     * @var IssueFactory
     */
    private ?IssueFactory $issueFactory = null;
    
    /**
     * @var ReportGenerator
     */
    private ?ReportGenerator $reportGenerator = null;
    
    /**
     * @var array
     */
    private array $analyzerConfig = [];
    
    /**
     * @var array
     */
    private array $customAnalyzers = [];
    
    /**
     * @var OutputInterface|null
     */
    private ?OutputInterface $output = null;
    
    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('performance:review')
            ->setDescription('Run a comprehensive performance review of your Magento 2 installation (v2.0)')
            ->addOption(
                'output-file',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Save the report to a file instead of displaying it'
            )
            ->addOption(
                'category',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Run review for specific category only (config, modules, codebase, database, frontend, indexing, thirdparty, api, php, mysql, redis)'
            )
            ->addOption(
                'no-color',
                null,
                InputOption::VALUE_NONE,
                'Disable colored output'
            )
            ->addOption(
                'details',
                'd',
                InputOption::VALUE_NONE,
                'Show detailed information for issues'
            )
            ->addOption(
                'list-analyzers',
                'l',
                InputOption::VALUE_NONE,
                'List all available analyzers'
            )
            ->addOption(
                'skip-analyzer',
                's',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Skip specific analyzer(s) by ID'
            );
    }
    
    /**
     * Inject dependencies
     *
     * @param DeploymentConfig $deploymentConfig
     * @param State $appState
     * @param TypeListInterface $cacheTypeList
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resourceConnection
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param UrlRewriteCollectionFactory $urlRewriteCollectionFactory
     * @param ModuleListInterface $moduleList
     * @param ModuleManager $moduleManager
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param Filesystem $filesystem
     * @param IndexerRegistry $indexerRegistry
     * @param ScheduleCollectionFactory $scheduleCollectionFactory
     * @param ProductMetadataInterface $productMetadata
     */
    public function inject(
        DeploymentConfig $deploymentConfig,
        State $appState,
        TypeListInterface $cacheTypeList,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection,
        ProductCollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        ModuleListInterface $moduleList,
        ModuleManager $moduleManager,
        ComponentRegistrarInterface $componentRegistrar,
        Filesystem $filesystem,
        IndexerRegistry $indexerRegistry,
        ScheduleCollectionFactory $scheduleCollectionFactory,
        ProductMetadataInterface $productMetadata
    ) {
        $this->issueFactory = new IssueFactory();
        $this->reportGenerator = new ReportGenerator();
        
        // Initialize analyzers with injected dependencies
        $this->configurationAnalyzer = new ConfigurationAnalyzer(
            $deploymentConfig,
            $appState,
            $cacheTypeList,
            $scopeConfig,
            $this->issueFactory
        );
        
        $this->databaseAnalyzer = new DatabaseAnalyzer(
            $resourceConnection,
            $productCollectionFactory,
            $categoryCollectionFactory,
            $urlRewriteCollectionFactory,
            $this->issueFactory
        );
        
        $this->moduleAnalyzer = new ModuleAnalyzer(
            $moduleList,
            $moduleManager,
            $componentRegistrar,
            $this->issueFactory
        );
        
        $this->codebaseAnalyzer = new CodebaseAnalyzer(
            $filesystem,
            $componentRegistrar,
            $moduleList,
            $this->issueFactory
        );
        
        $this->frontendAnalyzer = new FrontendAnalyzer(
            $scopeConfig,
            $filesystem,
            $this->issueFactory
        );
        
        $this->indexerCronAnalyzer = new IndexerCronAnalyzer(
            $indexerRegistry,
            $resourceConnection,
            $scheduleCollectionFactory,
            $scopeConfig,
            $this->issueFactory
        );
        
        $this->phpConfigurationAnalyzer = new PhpConfigurationAnalyzer(
            $this->issueFactory
        );
        
        $this->mysqlConfigurationAnalyzer = new MysqlConfigurationAnalyzer(
            $resourceConnection,
            $this->issueFactory
        );
        
        $this->redisConfigurationAnalyzer = new RedisConfigurationAnalyzer(
            $deploymentConfig,
            $this->issueFactory
        );
        
        $this->apiAnalyzer = new ApiAnalyzer(
            $scopeConfig,
            $resourceConnection,
            $this->issueFactory
        );
        
        $this->thirdPartyAnalyzer = new ThirdPartyAnalyzer(
            $moduleList,
            $productMetadata,
            $componentRegistrar,
            $this->issueFactory
        );
        
        // Load analyzer configuration
        $this->loadAnalyzerConfiguration();
    }
    
    /**
     * Load analyzer configuration from YAML files
     * 
     * @return void
     */
    private function loadAnalyzerConfiguration(): void
    {
        $config = $this->getApplication()->getConfig();
        $commandConfig = $config['commands'][self::class] ?? [];
        $this->analyzerConfig = $commandConfig['analyzers'] ?? [];
    }
    
    /**
     * Get all available analyzers (both core and custom)
     * 
     * @return array
     */
    private function getAllAnalyzers(): array
    {
        $analyzers = [];
        
        // Add core analyzers with legacy adapter
        $coreAnalyzers = [
            'configuration' => [
                'id' => 'configuration',
                'name' => 'Configuration Analysis',
                'description' => 'Check application mode, cache configuration, and settings',
                'category' => 'config',
                'class' => ConfigurationAnalyzer::class,
                'adapter' => $this->configurationAnalyzer
            ],
            'database' => [
                'id' => 'database',
                'name' => 'Database Analysis',
                'description' => 'Analyze database size, table optimization, and data volumes',
                'category' => 'database',
                'class' => DatabaseAnalyzer::class,
                'adapter' => $this->databaseAnalyzer
            ],
            'modules' => [
                'id' => 'modules',
                'name' => 'Module Analysis',
                'description' => 'Check installed modules and their impact',
                'category' => 'modules',
                'class' => ModuleAnalyzer::class,
                'adapter' => $this->moduleAnalyzer
            ],
            'codebase' => [
                'id' => 'codebase',
                'name' => 'Codebase Analysis',
                'description' => 'Examine code structure and custom code volume',
                'category' => 'codebase',
                'class' => CodebaseAnalyzer::class,
                'adapter' => $this->codebaseAnalyzer
            ],
            'frontend' => [
                'id' => 'frontend',
                'name' => 'Frontend Analysis',
                'description' => 'Check frontend optimization settings',
                'category' => 'frontend',
                'class' => FrontendAnalyzer::class,
                'adapter' => $this->frontendAnalyzer
            ],
            'indexing' => [
                'id' => 'indexing',
                'name' => 'Indexer & Cron Analysis',
                'description' => 'Review indexer status and cron job health',
                'category' => 'indexing',
                'class' => IndexerCronAnalyzer::class,
                'adapter' => $this->indexerCronAnalyzer
            ],
            'php' => [
                'id' => 'php',
                'name' => 'PHP Configuration',
                'description' => 'Review PHP configuration and extensions',
                'category' => 'php',
                'class' => PhpConfigurationAnalyzer::class,
                'adapter' => $this->phpConfigurationAnalyzer
            ],
            'mysql' => [
                'id' => 'mysql',
                'name' => 'MySQL Configuration',
                'description' => 'Check MySQL/MariaDB configuration settings',
                'category' => 'mysql',
                'class' => MysqlConfigurationAnalyzer::class,
                'adapter' => $this->mysqlConfigurationAnalyzer
            ],
            'redis' => [
                'id' => 'redis',
                'name' => 'Redis Configuration',
                'description' => 'Analyze Redis configuration and usage',
                'category' => 'redis',
                'class' => RedisConfigurationAnalyzer::class,
                'adapter' => $this->redisConfigurationAnalyzer
            ],
            'api' => [
                'id' => 'api',
                'name' => 'API Analysis',
                'description' => 'Check API integrations and OAuth tokens',
                'category' => 'api',
                'class' => ApiAnalyzer::class,
                'adapter' => $this->apiAnalyzer
            ],
            'thirdparty' => [
                'id' => 'thirdparty',
                'name' => 'Third-party Analysis',
                'description' => 'Identify problematic third-party extensions',
                'category' => 'thirdparty',
                'class' => ThirdPartyAnalyzer::class,
                'adapter' => $this->thirdPartyAnalyzer
            ]
        ];
        
        // Add core analyzers (unless overridden in config)
        foreach ($coreAnalyzers as $id => $analyzer) {
            if (!isset($this->analyzerConfig['core'][$id]) || 
                ($this->analyzerConfig['core'][$id]['enabled'] ?? true) !== false) {
                $analyzers[$id] = $analyzer;
            }
        }
        
        // Load custom analyzers from configuration
        $customAnalyzers = $this->loadCustomAnalyzers();
        foreach ($customAnalyzers as $id => $analyzer) {
            $analyzers[$id] = $analyzer;
        }
        
        return $analyzers;
    }
    
    /**
     * Load custom analyzers from configuration
     * 
     * @return array
     */
    private function loadCustomAnalyzers(): array
    {
        $analyzers = [];
        
        // Merge all analyzer configurations
        $allConfigs = [];
        foreach ($this->analyzerConfig as $group => $groupAnalyzers) {
            if (is_array($groupAnalyzers)) {
                foreach ($groupAnalyzers as $analyzerConfig) {
                    if (isset($analyzerConfig['id'])) {
                        $allConfigs[$analyzerConfig['id']] = array_merge(
                            ['group' => $group],
                            $analyzerConfig
                        );
                    }
                }
            }
        }
        
        foreach ($allConfigs as $id => $config) {
            if (!isset($config['class'])) {
                continue;
            }
            
            $class = $config['class'];
            
            if (!class_exists($class)) {
                if ($this->output && $this->output->isVerbose()) {
                    $this->output->writeln(
                        sprintf('<comment>Analyzer class not found: %s</comment>', $class)
                    );
                }
                continue;
            }
            
            // Check if it's a custom analyzer implementing our interface
            $implementsInterface = false;
            try {
                $reflection = new \ReflectionClass($class);
                $implementsInterface = $reflection->implementsInterface(AnalyzerCheckInterface::class);
            } catch (\Exception $e) {
                continue;
            }
            
            if ($implementsInterface) {
                // It's a new-style analyzer
                $analyzer = new $class();
                
                if ($analyzer instanceof ConfigAwareInterface && isset($config['config'])) {
                    $analyzer->setConfig($config['config']);
                }
                
                $analyzers[$id] = [
                    'id' => $id,
                    'name' => $config['name'] ?? $config['description'] ?? $id,
                    'description' => $config['description'] ?? '',
                    'category' => $config['category'] ?? $config['group'] ?? 'custom',
                    'instance' => $analyzer,
                    'group' => $config['group'] ?? 'custom'
                ];
            } else {
                // It's a legacy analyzer, wrap it
                $analyzers[$id] = [
                    'id' => $id,
                    'name' => $config['name'] ?? $config['description'] ?? $id,
                    'description' => $config['description'] ?? '',
                    'category' => $config['category'] ?? $config['group'] ?? 'custom',
                    'class' => $class,
                    'group' => $config['group'] ?? 'custom',
                    'config' => $config['config'] ?? []
                ];
            }
        }
        
        return $analyzers;
    }
    
    /**
     * Get dependencies for injection
     * 
     * @return array
     */
    private function getDependencies(): array
    {
        return [
            'deploymentConfig' => $this->deploymentConfig ?? null,
            'appState' => $this->appState ?? null,
            'cacheTypeList' => $this->cacheTypeList ?? null,
            'scopeConfig' => $this->scopeConfig ?? null,
            'resourceConnection' => $this->resourceConnection ?? null,
            'productCollectionFactory' => $this->productCollectionFactory ?? null,
            'categoryCollectionFactory' => $this->categoryCollectionFactory ?? null,
            'urlRewriteCollectionFactory' => $this->urlRewriteCollectionFactory ?? null,
            'moduleList' => $this->moduleList ?? null,
            'moduleManager' => $this->moduleManager ?? null,
            'componentRegistrar' => $this->componentRegistrar ?? null,
            'filesystem' => $this->filesystem ?? null,
            'indexerRegistry' => $this->indexerRegistry ?? null,
            'scheduleCollectionFactory' => $this->scheduleCollectionFactory ?? null,
            'productMetadata' => $this->productMetadata ?? null,
            'issueFactory' => $this->issueFactory ?? null
        ];
    }
    
    /**
     * List all available analyzers
     * 
     * @param OutputInterface $output
     * @return void
     */
    private function listAnalyzers(OutputInterface $output): void
    {
        $analyzers = $this->getAllAnalyzers();
        
        $output->writeln('<info>Available Performance Analyzers:</info>');
        $output->writeln('');
        
        $table = $this->getHelper('table');
        $table->setHeaders(['ID', 'Name', 'Category', 'Description']);
        
        $rows = [];
        foreach ($analyzers as $id => $analyzer) {
            $rows[] = [
                $id,
                $analyzer['name'] ?? 'Unknown',
                $analyzer['category'] ?? 'Unknown',
                $analyzer['description'] ?? ''
            ];
        }
        
        $table->setRows($rows);
        $table->render($output);
    }
    
    /**
     * Execute command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);
        $this->output = $output;
        
        // Detect and initialize Magento
        $this->detectMagento($output);
        if (!$this->initMagento()) {
            $output->writeln('<error>Could not initialize Magento. Is this a Magento root directory?</error>');
            return self::EXIT_CODE_FAILURE;
        }
        
        // Handle --list-analyzers option
        if ($input->getOption('list-analyzers')) {
            $this->listAnalyzers($output);
            return self::EXIT_CODE_SUCCESS;
        }
        
        $output->writeln('Starting Magento 2 Performance Review...');
        $output->writeln('');
        
        // Get all analyzers
        $allAnalyzers = $this->getAllAnalyzers();
        $category = $input->getOption('category');
        $skipAnalyzers = $input->getOption('skip-analyzer') ?: [];
        
        // Filter analyzers based on category and skip options
        $analyzersToRun = [];
        foreach ($allAnalyzers as $id => $analyzer) {
            // Skip if in skip list
            if (in_array($id, $skipAnalyzers)) {
                continue;
            }
            
            // Skip if category filter doesn't match
            if ($category && $analyzer['category'] !== $category) {
                continue;
            }
            
            $analyzersToRun[$id] = $analyzer;
        }
        
        if (empty($analyzersToRun)) {
            $output->writeln('<comment>No analyzers to run based on your filters.</comment>');
            return self::EXIT_CODE_SUCCESS;
        }
        
        // Create issue collection
        $issueCollection = new Collection($this->issueFactory);
        
        // Run analyzers
        foreach ($analyzersToRun as $id => $analyzerData) {
            $output->write(sprintf('Running %s... ', $analyzerData['name']));
            
            try {
                if (isset($analyzerData['adapter'])) {
                    // Legacy analyzer - use old method
                    $issues = $analyzerData['adapter']->analyze();
                    foreach ($issues as $issue) {
                        $issueCollection->addIssue($issue);
                    }
                } elseif (isset($analyzerData['instance'])) {
                    // New-style analyzer
                    $analyzer = $analyzerData['instance'];
                    
                    // Set dependencies if needed
                    if ($analyzer instanceof DependencyAwareInterface) {
                        $analyzer->setDependencies($this->getDependencies());
                    }
                    
                    // Run analysis
                    $analyzer->analyze($issueCollection);
                } else {
                    // Custom legacy analyzer - use adapter
                    $adapter = new LegacyAnalyzerAdapter($analyzerData['class']);
                    
                    if ($adapter instanceof ConfigAwareInterface && isset($analyzerData['config'])) {
                        $adapter->setConfig($analyzerData['config']);
                    }
                    
                    if ($adapter instanceof DependencyAwareInterface) {
                        $adapter->setDependencies($this->getDependencies());
                    }
                    
                    $adapter->analyze($issueCollection);
                }
                
                $output->writeln('<info>✓</info>');
            } catch (\Exception $e) {
                $output->writeln('<error>✗</error>');
                if ($output->isVerbose()) {
                    $output->writeln('<error>  ' . $e->getMessage() . '</error>');
                }
                
                // Add error as an issue
                $issueCollection->createIssue()
                    ->setPriority('low')
                    ->setCategory('System')
                    ->setIssue(sprintf('Analyzer "%s" failed', $analyzerData['name']))
                    ->setDetails($e->getMessage())
                    ->add();
            }
        }
        
        // Get all collected issues
        $issues = $issueCollection->getIssues();
        
        $output->writeln('');
        
        // Generate report
        $showDetails = $input->getOption('details');
        $report = $this->reportGenerator->generateReport($issues, $showDetails);
        
        // Handle output
        $outputFile = $input->getOption('output-file');
        if ($outputFile) {
            try {
                file_put_contents($outputFile, $report);
                $output->writeln("<info>Report saved to: $outputFile</info>");
            } catch (\Exception $e) {
                $output->writeln('<error>Failed to save report: ' . $e->getMessage() . '</error>');
                return self::EXIT_CODE_FAILURE;
            }
        } else {
            // If no-color option is set, strip ANSI color codes
            if ($input->getOption('no-color')) {
                $report = preg_replace('/\033\[[0-9;]*m/', '', $report);
            }
            $output->write($report);
        }
        
        $executionTime = round(microtime(true) - $startTime, 2);
        $output->writeln('');
        $output->writeln("<info>Performance review completed in {$executionTime} seconds.</info>");
        
        // Return exit code based on severity of issues found
        $highPriorityCount = $this->countHighPriorityIssues($issues);
        
        if ($highPriorityCount > 0) {
            $output->writeln("<error>Found {$highPriorityCount} high priority issues that should be addressed.</error>");
            return self::EXIT_CODE_FAILURE;
        }
        
        return self::EXIT_CODE_SUCCESS;
    }
    
    /**
     * Count high priority issues
     *
     * @param array $issues
     * @return int
     */
    private function countHighPriorityIssues(array $issues): int
    {
        $count = 0;
        foreach ($issues as $issue) {
            if ($issue->getPriority() === 'high') {
                $count++;
            }
        }
        return $count;
    }
}