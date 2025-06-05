<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;
use PerformanceReview\Util\ByteConverter;

/**
 * Database analyzer
 */
class DatabaseAnalyzer
{
    /**
     * Size thresholds
     */
    private const DB_SIZE_WARNING_THRESHOLD = 20 * 1024 * 1024 * 1024; // 20GB
    private const DB_SIZE_CRITICAL_THRESHOLD = 50 * 1024 * 1024 * 1024; // 50GB
    private const TABLE_SIZE_WARNING_THRESHOLD = 1024 * 1024 * 1024; // 1GB
    
    /**
     * Product/category count thresholds
     */
    private const PRODUCT_COUNT_WARNING = 100000;
    private const CATEGORY_COUNT_WARNING = 10000;
    private const URL_REWRITE_COUNT_WARNING = 500000;
    
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;
    
    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;
    
    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;
    
    /**
     * @var UrlRewriteCollectionFactory
     */
    private UrlRewriteCollectionFactory $urlRewriteCollectionFactory;
    
    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;
    
    /**
     * @var ByteConverter
     */
    private ByteConverter $byteConverter;
    
    /**
     * Constructor
     *
     * @param ResourceConnection $resourceConnection
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param UrlRewriteCollectionFactory $urlRewriteCollectionFactory
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        ProductCollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        IssueFactory $issueFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->urlRewriteCollectionFactory = $urlRewriteCollectionFactory;
        $this->issueFactory = $issueFactory;
        $this->byteConverter = new ByteConverter();
    }
    
    /**
     * Analyze database
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check database size
        $issues = array_merge($issues, $this->checkDatabaseSize());
        
        // Check table sizes
        $issues = array_merge($issues, $this->checkTableSizes());
        
        // Check product count
        $issues = array_merge($issues, $this->checkProductCount());
        
        // Check category count
        $issues = array_merge($issues, $this->checkCategoryCount());
        
        // Check URL rewrites
        $issues = array_merge($issues, $this->checkUrlRewrites());
        
        // Check log tables
        $issues = array_merge($issues, $this->checkLogTables());
        
        return $issues;
    }
    
    /**
     * Check database size
     *
     * @return IssueInterface[]
     */
    private function checkDatabaseSize(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $dbName = $this->resourceConnection->getSchemaName(ResourceConnection::DEFAULT_CONNECTION);
            
            $query = "SELECT 
                SUM(data_length + index_length) AS size
                FROM information_schema.TABLES 
                WHERE table_schema = :db_name";
            
            $size = (int) $connection->fetchOne($query, ['db_name' => $dbName]);
            
            if ($size > self::DB_SIZE_CRITICAL_THRESHOLD) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Database',
                    'Database size exceeds 50GB',
                    'Very large database can impact backup/restore times, replication, and query performance.',
                    $this->byteConverter->convert($size),
                    'Under 50GB'
                );
            } elseif ($size > self::DB_SIZE_WARNING_THRESHOLD) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Database',
                    'Database size exceeds 20GB',
                    'Large database size may impact performance. Consider archiving old data.',
                    $this->byteConverter->convert($size),
                    'Under 20GB'
                );
            }
        } catch (\Exception $e) {
            // Database size check failed
        }
        
        return $issues;
    }
    
    /**
     * Check table sizes
     *
     * @return IssueInterface[]
     */
    private function checkTableSizes(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $dbName = $this->resourceConnection->getSchemaName(ResourceConnection::DEFAULT_CONNECTION);
            
            $query = "SELECT 
                table_name,
                data_length + index_length AS size
                FROM information_schema.TABLES 
                WHERE table_schema = :db_name
                AND data_length + index_length > :threshold
                ORDER BY size DESC";
            
            $largeTables = $connection->fetchAll($query, [
                'db_name' => $dbName,
                'threshold' => self::TABLE_SIZE_WARNING_THRESHOLD
            ]);
            
            if (!empty($largeTables)) {
                $tableList = [];
                foreach ($largeTables as $table) {
                    $tableList[] = sprintf(
                        '%s (%s)',
                        $table['table_name'],
                        $this->byteConverter->convert((int) $table['size'])
                    );
                }
                
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Database',
                    'Large tables detected',
                    sprintf('%d table(s) exceed 1GB. Large tables can slow down queries and maintenance operations.', count($largeTables)),
                    implode(', ', array_slice($tableList, 0, 3)) . (count($tableList) > 3 ? '...' : ''),
                    'Tables under 1GB',
                    ['large_tables' => $tableList]
                );
            }
        } catch (\Exception $e) {
            // Table size check failed
        }
        
        return $issues;
    }
    
    /**
     * Check product count
     *
     * @return IssueInterface[]
     */
    private function checkProductCount(): array
    {
        $issues = [];
        
        try {
            $productCount = $this->productCollectionFactory->create()->getSize();
            
            if ($productCount > self::PRODUCT_COUNT_WARNING) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Database',
                    'High product count',
                    'Large product catalogs require optimization strategies like flat catalog, elasticsearch, and proper indexing.',
                    number_format($productCount),
                    'Optimized for catalog size'
                );
            }
        } catch (\Exception $e) {
            // Product count check failed
        }
        
        return $issues;
    }
    
    /**
     * Check category count
     *
     * @return IssueInterface[]
     */
    private function checkCategoryCount(): array
    {
        $issues = [];
        
        try {
            $categoryCount = $this->categoryCollectionFactory->create()->getSize();
            
            if ($categoryCount > self::CATEGORY_COUNT_WARNING) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_LOW,
                    'Database',
                    'High category count',
                    'Large category trees can impact navigation performance. Consider category structure optimization.',
                    number_format($categoryCount),
                    'Optimized category structure'
                );
            }
        } catch (\Exception $e) {
            // Category count check failed
        }
        
        return $issues;
    }
    
    /**
     * Check URL rewrites
     *
     * @return IssueInterface[]
     */
    private function checkUrlRewrites(): array
    {
        $issues = [];
        
        try {
            $urlRewriteCount = $this->urlRewriteCollectionFactory->create()->getSize();
            
            if ($urlRewriteCount > self::URL_REWRITE_COUNT_WARNING) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Database',
                    'Excessive URL rewrites',
                    'Too many URL rewrites can severely impact performance. Consider disabling automatic generation for categories/products.',
                    number_format($urlRewriteCount),
                    'Under 500,000'
                );
            }
        } catch (\Exception $e) {
            // URL rewrite check failed
        }
        
        return $issues;
    }
    
    /**
     * Check log tables
     *
     * @return IssueInterface[]
     */
    private function checkLogTables(): array
    {
        $issues = [];
        
        $logTables = [
            'report_event' => 'Report event log',
            'customer_log' => 'Customer log',
            'customer_visitor' => 'Customer visitor log',
            'report_viewed_product_index' => 'Viewed product report',
            'report_compared_product_index' => 'Compared product report'
        ];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            
            foreach ($logTables as $tableName => $description) {
                $table = $this->resourceConnection->getTableName($tableName);
                
                if ($connection->isTableExists($table)) {
                    $count = (int) $connection->fetchOne("SELECT COUNT(*) FROM {$table}");
                    
                    if ($count > 1000000) {
                        $issues[] = $this->issueFactory->createIssue(
                            IssueInterface::PRIORITY_MEDIUM,
                            'Database',
                            sprintf('Large %s table', $description),
                            'Log tables should be cleaned regularly to maintain performance.',
                            number_format($count) . ' records',
                            'Regular cleanup'
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            // Log table check failed
        }
        
        return $issues;
    }
}