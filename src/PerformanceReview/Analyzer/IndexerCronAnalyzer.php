<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Mview\ViewInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory as ScheduleCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;

/**
 * Indexer and cron analyzer
 */
class IndexerCronAnalyzer
{
    /**
     * Cron configuration paths
     */
    private const XML_PATH_CRON_ENABLED = 'system/cron/*/enabled';
    
    /**
     * @var IndexerRegistry
     */
    private IndexerRegistry $indexerRegistry;
    
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;
    
    /**
     * @var ScheduleCollectionFactory
     */
    private ScheduleCollectionFactory $scheduleCollectionFactory;
    
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;
    
    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;
    
    /**
     * Constructor
     *
     * @param IndexerRegistry $indexerRegistry
     * @param ResourceConnection $resourceConnection
     * @param ScheduleCollectionFactory $scheduleCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        IndexerRegistry $indexerRegistry,
        ResourceConnection $resourceConnection,
        ScheduleCollectionFactory $scheduleCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        IssueFactory $issueFactory
    ) {
        $this->indexerRegistry = $indexerRegistry;
        $this->resourceConnection = $resourceConnection;
        $this->scheduleCollectionFactory = $scheduleCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->issueFactory = $issueFactory;
    }
    
    /**
     * Analyze indexers and cron
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check indexer status
        $issues = array_merge($issues, $this->checkIndexerStatus());
        
        // Check indexer mode
        $issues = array_merge($issues, $this->checkIndexerMode());
        
        // Check cron status
        $issues = array_merge($issues, $this->checkCronStatus());
        
        // Check cron schedule
        $issues = array_merge($issues, $this->checkCronSchedule());
        
        // Check stuck cron jobs
        $issues = array_merge($issues, $this->checkStuckCronJobs());
        
        return $issues;
    }
    
    /**
     * Check indexer status
     *
     * @return IssueInterface[]
     */
    private function checkIndexerStatus(): array
    {
        $issues = [];
        $invalidIndexers = [];
        
        try {
            $indexerIds = $this->getIndexerIds();
            
            foreach ($indexerIds as $indexerId) {
                try {
                    $indexer = $this->indexerRegistry->get($indexerId);
                    
                    if (!$indexer->isValid()) {
                        $invalidIndexers[] = $indexerId;
                    }
                } catch (\Exception $e) {
                    // Skip indexer if cannot be loaded
                }
            }
            
            if (!empty($invalidIndexers)) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Indexing',
                    'Invalid indexers detected',
                    'Invalid indexers need to be reindexed to ensure data consistency and performance.',
                    implode(', ', array_slice($invalidIndexers, 0, 3)) . (count($invalidIndexers) > 3 ? '...' : ''),
                    'All indexers valid',
                    ['invalid_indexers' => $invalidIndexers]
                );
            }
        } catch (\Exception $e) {
            // Indexer status check failed
        }
        
        return $issues;
    }
    
    /**
     * Check indexer mode
     *
     * @return IssueInterface[]
     */
    private function checkIndexerMode(): array
    {
        $issues = [];
        $realtimeIndexers = [];
        
        try {
            $indexerIds = $this->getIndexerIds();
            
            foreach ($indexerIds as $indexerId) {
                try {
                    $indexer = $this->indexerRegistry->get($indexerId);
                    
                    if (!$indexer->isScheduled()) {
                        $realtimeIndexers[] = $indexerId;
                    }
                } catch (\Exception $e) {
                    // Skip indexer if cannot be loaded
                }
            }
            
            if (!empty($realtimeIndexers)) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Indexing',
                    'Indexers in "Update on Save" mode',
                    'Realtime indexing can severely impact admin performance. Use "Update by Schedule" mode.',
                    count($realtimeIndexers) . ' indexers',
                    'All in schedule mode',
                    ['realtime_indexers' => $realtimeIndexers]
                );
            }
        } catch (\Exception $e) {
            // Indexer mode check failed
        }
        
        return $issues;
    }
    
    /**
     * Check cron status
     *
     * @return IssueInterface[]
     */
    private function checkCronStatus(): array
    {
        $issues = [];
        
        try {
            $collection = $this->scheduleCollectionFactory->create();
            $collection->addFieldToFilter('status', 'success')
                ->addFieldToFilter('executed_at', ['gteq' => date('Y-m-d H:i:s', strtotime('-1 hour'))])
                ->setPageSize(1);
            
            if ($collection->getSize() === 0) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Indexing',
                    'Cron not running',
                    'No successful cron jobs in the last hour. Cron is required for scheduled tasks and indexing.',
                    'No recent jobs',
                    'Cron running'
                );
            }
            
            // Check for cron errors
            $errorCollection = $this->scheduleCollectionFactory->create();
            $errorCollection->addFieldToFilter('status', 'error')
                ->addFieldToFilter('executed_at', ['gteq' => date('Y-m-d H:i:s', strtotime('-24 hours'))]);
            
            $errorCount = $errorCollection->getSize();
            if ($errorCount > 10) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Indexing',
                    'Multiple cron errors',
                    'High number of cron errors in the last 24 hours indicates stability issues.',
                    $errorCount . ' errors',
                    'Minimal errors'
                );
            }
        } catch (\Exception $e) {
            // Cron status check failed
        }
        
        return $issues;
    }
    
    /**
     * Check cron schedule
     *
     * @return IssueInterface[]
     */
    private function checkCronSchedule(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('cron_schedule');
            
            // Check pending jobs count
            $pendingCount = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"
            );
            
            if ($pendingCount > 1000) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Indexing',
                    'Excessive pending cron jobs',
                    'Too many pending jobs indicates cron execution problems or performance issues.',
                    $pendingCount . ' pending',
                    'Under 100 pending'
                );
            }
            
            // Check schedule table size
            $totalCount = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$table}"
            );
            
            if ($totalCount > 10000) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Indexing',
                    'Large cron_schedule table',
                    'Cron schedule table should be cleaned regularly to maintain performance.',
                    $totalCount . ' records',
                    'Regular cleanup'
                );
            }
        } catch (\Exception $e) {
            // Cron schedule check failed
        }
        
        return $issues;
    }
    
    /**
     * Check for stuck cron jobs
     *
     * @return IssueInterface[]
     */
    private function checkStuckCronJobs(): array
    {
        $issues = [];
        
        try {
            $collection = $this->scheduleCollectionFactory->create();
            $collection->addFieldToFilter('status', 'running')
                ->addFieldToFilter('executed_at', ['lteq' => date('Y-m-d H:i:s', strtotime('-2 hours'))]);
            
            $stuckJobs = [];
            foreach ($collection as $job) {
                $stuckJobs[] = $job->getJobCode();
            }
            
            if (!empty($stuckJobs)) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Indexing',
                    'Stuck cron jobs detected',
                    'Jobs running for over 2 hours may be stuck and blocking other jobs.',
                    implode(', ', array_slice(array_unique($stuckJobs), 0, 3)) . (count($stuckJobs) > 3 ? '...' : ''),
                    'No stuck jobs',
                    ['stuck_jobs' => $stuckJobs]
                );
            }
        } catch (\Exception $e) {
            // Stuck jobs check failed
        }
        
        return $issues;
    }
    
    /**
     * Get all indexer IDs
     *
     * @return array
     */
    private function getIndexerIds(): array
    {
        $indexerIds = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('indexer_state');
            
            $indexerIds = $connection->fetchCol(
                "SELECT indexer_id FROM {$table}"
            );
        } catch (\Exception $e) {
            // Use default indexer list as fallback
            $indexerIds = [
                'catalog_category_product',
                'catalog_product_category',
                'catalog_product_price',
                'catalog_product_attribute',
                'cataloginventory_stock',
                'catalogrule_rule',
                'catalogrule_product',
                'catalogsearch_fulltext'
            ];
        }
        
        return $indexerIds;
    }
}