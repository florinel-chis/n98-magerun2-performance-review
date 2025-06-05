<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\App\ResourceConnection;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;
use PerformanceReview\Util\ByteConverter;

/**
 * MySQL configuration analyzer
 */
class MysqlConfigurationAnalyzer
{
    /**
     * Important MySQL variables
     */
    private const MYSQL_VARIABLES = [
        'innodb_buffer_pool_size' => [
            'min' => 1073741824, // 1GB
            'recommended' => 4294967296, // 4GB
            'type' => 'bytes',
            'description' => 'InnoDB buffer pool size should be 50-80% of available RAM'
        ],
        'max_connections' => [
            'min' => 150,
            'recommended' => 500,
            'type' => 'number',
            'description' => 'Maximum concurrent connections'
        ],
        'thread_cache_size' => [
            'min' => 8,
            'recommended' => 64,
            'type' => 'number',
            'description' => 'Thread cache reduces connection overhead'
        ],
        'table_open_cache' => [
            'min' => 2000,
            'recommended' => 4000,
            'type' => 'number',
            'description' => 'Number of open tables for all threads'
        ],
        'tmp_table_size' => [
            'min' => 67108864, // 64MB
            'recommended' => 268435456, // 256MB
            'type' => 'bytes',
            'description' => 'Maximum size of internal in-memory temporary tables'
        ],
        'max_heap_table_size' => [
            'min' => 67108864, // 64MB
            'recommended' => 268435456, // 256MB
            'type' => 'bytes',
            'description' => 'Maximum size for MEMORY tables'
        ],
        'innodb_log_file_size' => [
            'min' => 134217728, // 128MB
            'recommended' => 536870912, // 512MB
            'type' => 'bytes',
            'description' => 'Size of InnoDB redo log files'
        ],
        'query_cache_size' => [
            'max' => 0,
            'type' => 'deprecation',
            'description' => 'Query cache is deprecated and should be disabled in MySQL 5.7+'
        ],
        'innodb_flush_log_at_trx_commit' => [
            'recommended' => '2',
            'type' => 'value',
            'description' => 'Set to 2 for better performance (with slight durability trade-off)'
        ]
    ];
    
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;
    
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
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        IssueFactory $issueFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->issueFactory = $issueFactory;
        $this->byteConverter = new ByteConverter();
    }
    
    /**
     * Analyze MySQL configuration
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check MySQL version
        $issues = array_merge($issues, $this->checkMysqlVersion());
        
        // Check MySQL variables
        $issues = array_merge($issues, $this->checkMysqlVariables());
        
        // Check storage engine
        $issues = array_merge($issues, $this->checkStorageEngine());
        
        // Check slow query log
        $issues = array_merge($issues, $this->checkSlowQueryLog());
        
        // Check binary log
        $issues = array_merge($issues, $this->checkBinaryLog());
        
        return $issues;
    }
    
    /**
     * Check MySQL version
     *
     * @return IssueInterface[]
     */
    private function checkMysqlVersion(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $version = $connection->fetchOne('SELECT VERSION()');
            
            // Extract version number
            preg_match('/^([0-9]+\.[0-9]+\.[0-9]+)/', $version, $matches);
            $versionNumber = $matches[1] ?? '0.0.0';
            
            // Check if it's MariaDB
            $isMariaDB = stripos($version, 'mariadb') !== false;
            
            if ($isMariaDB) {
                if (version_compare($versionNumber, '10.4', '<')) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'MySQL',
                        'MariaDB version outdated',
                        'Newer MariaDB versions provide better performance and features.',
                        $version,
                        'MariaDB 10.4 or higher'
                    );
                }
            } else {
                if (version_compare($versionNumber, '5.7', '<')) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_HIGH,
                        'MySQL',
                        'MySQL version too old',
                        'MySQL 5.6 and older are not supported by Magento 2.4+',
                        $version,
                        'MySQL 5.7 or higher'
                    );
                } elseif (version_compare($versionNumber, '8.0', '<')) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_LOW,
                        'MySQL',
                        'Consider upgrading to MySQL 8.0',
                        'MySQL 8.0 provides better performance and features.',
                        $version,
                        'MySQL 8.0 or higher'
                    );
                }
            }
        } catch (\Exception $e) {
            // Version check failed
        }
        
        return $issues;
    }
    
    /**
     * Check MySQL variables
     *
     * @return IssueInterface[]
     */
    private function checkMysqlVariables(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $variables = [];
            
            // Fetch all variables at once
            $result = $connection->fetchAll('SHOW VARIABLES');
            foreach ($result as $row) {
                $variables[$row['Variable_name']] = $row['Value'];
            }
            
            foreach (self::MYSQL_VARIABLES as $varName => $config) {
                if (!isset($variables[$varName])) {
                    continue;
                }
                
                $currentValue = $variables[$varName];
                
                switch ($config['type']) {
                    case 'bytes':
                        $currentBytes = (int) $currentValue;
                        
                        if (isset($config['min']) && $currentBytes < $config['min']) {
                            $issues[] = $this->issueFactory->createIssue(
                                IssueInterface::PRIORITY_HIGH,
                                'MySQL',
                                sprintf('%s too low', $varName),
                                $config['description'],
                                $this->byteConverter->convert($currentBytes),
                                $this->byteConverter->convert($config['recommended'])
                            );
                        } elseif (isset($config['recommended']) && $currentBytes < $config['recommended']) {
                            $issues[] = $this->issueFactory->createIssue(
                                IssueInterface::PRIORITY_MEDIUM,
                                'MySQL',
                                sprintf('%s below recommended', $varName),
                                $config['description'],
                                $this->byteConverter->convert($currentBytes),
                                $this->byteConverter->convert($config['recommended'])
                            );
                        }
                        break;
                        
                    case 'number':
                        $currentNumber = (int) $currentValue;
                        
                        if (isset($config['min']) && $currentNumber < $config['min']) {
                            $issues[] = $this->issueFactory->createIssue(
                                IssueInterface::PRIORITY_MEDIUM,
                                'MySQL',
                                sprintf('%s too low', $varName),
                                $config['description'],
                                (string) $currentNumber,
                                (string) $config['recommended']
                            );
                        }
                        break;
                        
                    case 'deprecation':
                        if (isset($config['max']) && (int) $currentValue > $config['max']) {
                            $issues[] = $this->issueFactory->createIssue(
                                IssueInterface::PRIORITY_MEDIUM,
                                'MySQL',
                                sprintf('%s should be disabled', $varName),
                                $config['description'],
                                $currentValue,
                                '0'
                            );
                        }
                        break;
                        
                    case 'value':
                        if ($currentValue != $config['recommended']) {
                            $issues[] = $this->issueFactory->createIssue(
                                IssueInterface::PRIORITY_LOW,
                                'MySQL',
                                sprintf('%s not optimal', $varName),
                                $config['description'],
                                $currentValue,
                                $config['recommended']
                            );
                        }
                        break;
                }
            }
            
            // Check if tmp_table_size and max_heap_table_size match
            if (isset($variables['tmp_table_size']) && isset($variables['max_heap_table_size'])) {
                if ($variables['tmp_table_size'] != $variables['max_heap_table_size']) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_LOW,
                        'MySQL',
                        'tmp_table_size and max_heap_table_size mismatch',
                        'These values should be equal for optimal performance.',
                        sprintf('tmp: %s, heap: %s', 
                            $this->byteConverter->convert((int) $variables['tmp_table_size']),
                            $this->byteConverter->convert((int) $variables['max_heap_table_size'])
                        ),
                        'Equal values'
                    );
                }
            }
        } catch (\Exception $e) {
            // Variable check failed
        }
        
        return $issues;
    }
    
    /**
     * Check storage engine
     *
     * @return IssueInterface[]
     */
    private function checkStorageEngine(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $dbName = $this->resourceConnection->getSchemaName(ResourceConnection::DEFAULT_CONNECTION);
            
            // Check for non-InnoDB tables
            $query = "SELECT table_name, engine 
                     FROM information_schema.tables 
                     WHERE table_schema = :db_name 
                     AND engine != 'InnoDB' 
                     AND table_type = 'BASE TABLE'";
            
            $nonInnodbTables = $connection->fetchAll($query, ['db_name' => $dbName]);
            
            if (!empty($nonInnodbTables)) {
                $tableList = [];
                foreach ($nonInnodbTables as $table) {
                    $tableList[] = sprintf('%s (%s)', $table['table_name'], $table['engine']);
                }
                
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'MySQL',
                    'Non-InnoDB tables detected',
                    'InnoDB provides better performance and reliability for Magento.',
                    implode(', ', array_slice($tableList, 0, 3)) . (count($tableList) > 3 ? '...' : ''),
                    'All tables using InnoDB',
                    ['non_innodb_tables' => $tableList]
                );
            }
        } catch (\Exception $e) {
            // Storage engine check failed
        }
        
        return $issues;
    }
    
    /**
     * Check slow query log
     *
     * @return IssueInterface[]
     */
    private function checkSlowQueryLog(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $variables = [];
            
            $result = $connection->fetchAll("SHOW VARIABLES LIKE 'slow_query%'");
            foreach ($result as $row) {
                $variables[$row['Variable_name']] = $row['Value'];
            }
            
            $result = $connection->fetchAll("SHOW VARIABLES LIKE 'long_query_time'");
            foreach ($result as $row) {
                $variables[$row['Variable_name']] = $row['Value'];
            }
            
            if (isset($variables['slow_query_log']) && $variables['slow_query_log'] !== 'ON') {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'MySQL',
                    'Slow query log disabled',
                    'Enable slow query log to identify performance bottlenecks.',
                    'Disabled',
                    'Enabled'
                );
            } elseif (isset($variables['long_query_time']) && (float) $variables['long_query_time'] > 2) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_LOW,
                    'MySQL',
                    'Slow query threshold too high',
                    'Lower threshold helps identify more optimization opportunities.',
                    $variables['long_query_time'] . ' seconds',
                    '2 seconds or less'
                );
            }
        } catch (\Exception $e) {
            // Slow query log check failed
        }
        
        return $issues;
    }
    
    /**
     * Check binary log
     *
     * @return IssueInterface[]
     */
    private function checkBinaryLog(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            $logBin = $connection->fetchOne("SHOW VARIABLES LIKE 'log_bin'");
            
            if ($logBin && strtoupper($logBin['Value'] ?? '') === 'ON') {
                // Check expire logs days
                $expireLogs = $connection->fetchOne("SHOW VARIABLES LIKE 'expire_logs_days'");
                if ($expireLogs && (int) ($expireLogs['Value'] ?? 0) > 7) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_LOW,
                        'MySQL',
                        'Binary logs retention too long',
                        'Long retention period can consume significant disk space.',
                        $expireLogs['Value'] . ' days',
                        '7 days or less'
                    );
                }
            }
        } catch (\Exception $e) {
            // Binary log check failed
        }
        
        return $issues;
    }
}