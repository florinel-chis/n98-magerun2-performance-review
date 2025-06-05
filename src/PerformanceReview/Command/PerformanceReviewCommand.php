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
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\ReportGenerator;
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
     * @var IssueFactory
     */
    private ?IssueFactory $issueFactory = null;
    
    /**
     * @var ReportGenerator
     */
    private ?ReportGenerator $reportGenerator = null;
    
    /**
     * Configure command
     */
    protected function configure(): void
    {
        $this->setName('performance:review')
            ->setDescription('Run a comprehensive performance review of your Magento 2 installation')
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
        ScheduleCollectionFactory $scheduleCollectionFactory
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
        
        // Detect and initialize Magento
        $this->detectMagento($output);
        if (!$this->initMagento()) {
            $output->writeln('<error>Could not initialize Magento. Is this a Magento root directory?</error>');
            return self::EXIT_CODE_FAILURE;
        }
        
        $output->writeln('Starting Magento 2 Performance Review...');
        $output->writeln('');
        
        $issues = [];
        $category = $input->getOption('category');
        
        try {
            // Run configuration analysis
            if (!$category || $category === 'config') {
                $output->write('Checking configuration... ');
                $configIssues = $this->configurationAnalyzer->analyze();
                $issues = array_merge($issues, $configIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run database analysis
            if (!$category || $category === 'database') {
                $output->write('Analyzing database... ');
                $databaseIssues = $this->databaseAnalyzer->analyze();
                $issues = array_merge($issues, $databaseIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run module analysis
            if (!$category || $category === 'modules') {
                $output->write('Checking modules... ');
                $moduleIssues = $this->moduleAnalyzer->analyze();
                $issues = array_merge($issues, $moduleIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run codebase analysis
            if (!$category || $category === 'codebase') {
                $output->write('Analyzing codebase... ');
                $codebaseIssues = $this->codebaseAnalyzer->analyze();
                $issues = array_merge($issues, $codebaseIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run frontend analysis
            if (!$category || $category === 'frontend') {
                $output->write('Checking frontend optimization... ');
                $frontendIssues = $this->frontendAnalyzer->analyze();
                $issues = array_merge($issues, $frontendIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run indexer and cron analysis
            if (!$category || $category === 'indexing') {
                $output->write('Checking indexers and cron... ');
                $indexerCronIssues = $this->indexerCronAnalyzer->analyze();
                $issues = array_merge($issues, $indexerCronIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run PHP configuration analysis
            if (!$category || $category === 'php') {
                $output->write('Checking PHP configuration... ');
                $phpIssues = $this->phpConfigurationAnalyzer->analyze();
                $issues = array_merge($issues, $phpIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // Run MySQL configuration analysis
            if (!$category || $category === 'mysql') {
                $output->write('Checking MySQL configuration... ');
                $mysqlIssues = $this->mysqlConfigurationAnalyzer->analyze();
                $issues = array_merge($issues, $mysqlIssues);
                $output->writeln('<info>✓</info>');
            }
            
            // TODO: Add other analyzers here as they are implemented
            if ($category && !in_array($category, ['config', 'database', 'modules', 'codebase', 'frontend', 'indexing', 'php', 'mysql'])) {
                $output->writeln(sprintf('<comment>Category "%s" analyzer not yet implemented.</comment>', $category));
            }
            
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            if ($output->isVerbose()) {
                $output->writeln($e->getTraceAsString());
            }
            return self::EXIT_CODE_FAILURE;
        }
        
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