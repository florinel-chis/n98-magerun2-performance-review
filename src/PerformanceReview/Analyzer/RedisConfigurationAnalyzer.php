<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\App\DeploymentConfig;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;

/**
 * Redis configuration analyzer
 */
class RedisConfigurationAnalyzer
{
    /**
     * Configuration paths
     */
    private const CACHE_BACKEND_PATH = 'cache/frontend/default/backend';
    private const PAGE_CACHE_BACKEND_PATH = 'cache/frontend/page_cache/backend';
    private const SESSION_SAVE_PATH = 'session/save';
    
    /**
     * Redis database recommendations
     */
    private const REDIS_DB_RECOMMENDATIONS = [
        'cache' => 0,
        'page_cache' => 1,
        'session' => 2
    ];
    
    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;
    
    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;
    
    /**
     * Constructor
     *
     * @param DeploymentConfig $deploymentConfig
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        DeploymentConfig $deploymentConfig,
        IssueFactory $issueFactory
    ) {
        $this->deploymentConfig = $deploymentConfig;
        $this->issueFactory = $issueFactory;
    }
    
    /**
     * Analyze Redis configuration
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check if Redis is being used
        $redisUsage = $this->getRedisUsage();
        
        if (empty($redisUsage)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'Redis',
                'Redis not configured',
                'Redis provides significant performance improvements for cache and sessions.',
                'Not configured',
                'Redis for cache and sessions'
            );
            return $issues;
        }
        
        // Check Redis configuration
        $issues = array_merge($issues, $this->checkRedisConfiguration($redisUsage));
        
        // Check database separation
        $issues = array_merge($issues, $this->checkDatabaseSeparation($redisUsage));
        
        // Check compression settings
        $issues = array_merge($issues, $this->checkCompressionSettings());
        
        // Check Redis extension
        $issues = array_merge($issues, $this->checkRedisExtension());
        
        return $issues;
    }
    
    /**
     * Get Redis usage information
     *
     * @return array
     */
    private function getRedisUsage(): array
    {
        $usage = [];
        
        // Check cache backend
        $cacheBackend = $this->deploymentConfig->get(self::CACHE_BACKEND_PATH);
        if ($cacheBackend === 'Magento\\Framework\\Cache\\Backend\\Redis' || 
            $cacheBackend === 'Cm_Cache_Backend_Redis') {
            $usage['cache'] = $this->deploymentConfig->get('cache/frontend/default/backend_options') ?? [];
        }
        
        // Check page cache backend
        $pageCacheBackend = $this->deploymentConfig->get(self::PAGE_CACHE_BACKEND_PATH);
        if ($pageCacheBackend === 'Magento\\Framework\\Cache\\Backend\\Redis' || 
            $pageCacheBackend === 'Cm_Cache_Backend_Redis') {
            $usage['page_cache'] = $this->deploymentConfig->get('cache/frontend/page_cache/backend_options') ?? [];
        }
        
        // Check session save
        $sessionSave = $this->deploymentConfig->get(self::SESSION_SAVE_PATH);
        if ($sessionSave === 'redis') {
            $usage['session'] = $this->deploymentConfig->get('session/redis') ?? [];
        }
        
        return $usage;
    }
    
    /**
     * Check Redis configuration
     *
     * @param array $redisUsage
     * @return IssueInterface[]
     */
    private function checkRedisConfiguration(array $redisUsage): array
    {
        $issues = [];
        
        foreach ($redisUsage as $type => $config) {
            // Check server configuration
            if (empty($config['server']) || $config['server'] === 'localhost' || $config['server'] === '127.0.0.1') {
                if (count($redisUsage) > 1) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_LOW,
                        'Redis',
                        'Redis on localhost',
                        'Consider using a dedicated Redis server for better performance and scalability.',
                        'localhost',
                        'Dedicated Redis server'
                    );
                    break; // Only report once
                }
            }
            
            // Check persistent connections
            if (!isset($config['persistent']) || empty($config['persistent'])) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_LOW,
                    'Redis',
                    sprintf('Persistent connections disabled for %s', $type),
                    'Persistent connections reduce connection overhead.',
                    'Disabled',
                    'Enabled'
                );
            }
            
            // Check compression for cache
            if (in_array($type, ['cache', 'page_cache'])) {
                if (!isset($config['compress_data']) || $config['compress_data'] != 1) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Redis',
                        sprintf('Compression disabled for %s', $type),
                        'Compression reduces memory usage and network traffic.',
                        'Disabled',
                        'Enabled'
                    );
                }
            }
        }
        
        // Check if page cache is using Redis but default cache is not
        if (isset($redisUsage['page_cache']) && !isset($redisUsage['cache'])) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Redis',
                'Default cache not using Redis',
                'Using Redis for all cache types provides consistent performance.',
                'File/Database cache',
                'Redis cache'
            );
        }
        
        // Check if sessions are not using Redis
        if (!isset($redisUsage['session']) && !empty($redisUsage)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Redis',
                'Sessions not using Redis',
                'Redis sessions provide better performance and scalability than file-based sessions.',
                'File/Database sessions',
                'Redis sessions'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check database separation
     *
     * @param array $redisUsage
     * @return IssueInterface[]
     */
    private function checkDatabaseSeparation(array $redisUsage): array
    {
        $issues = [];
        $databases = [];
        
        foreach ($redisUsage as $type => $config) {
            $db = $config['database'] ?? 0;
            if (!isset($databases[$db])) {
                $databases[$db] = [];
            }
            $databases[$db][] = $type;
        }
        
        // Check for shared databases
        foreach ($databases as $db => $types) {
            if (count($types) > 1) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Redis',
                    'Shared Redis database',
                    'Using separate databases prevents cache conflicts and improves management.',
                    sprintf('DB %d: %s', $db, implode(', ', $types)),
                    'Separate databases'
                );
            }
        }
        
        // Check for non-standard database numbers
        foreach ($redisUsage as $type => $config) {
            $db = $config['database'] ?? 0;
            $recommended = self::REDIS_DB_RECOMMENDATIONS[$type] ?? null;
            
            if ($recommended !== null && $db != $recommended) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_LOW,
                    'Redis',
                    sprintf('Non-standard database for %s', $type),
                    'Using standard database numbers improves maintainability.',
                    sprintf('DB %d', $db),
                    sprintf('DB %d', $recommended)
                );
            }
        }
        
        return $issues;
    }
    
    /**
     * Check compression settings
     *
     * @return IssueInterface[]
     */
    private function checkCompressionSettings(): array
    {
        $issues = [];
        
        // Check compression library
        $cacheConfig = $this->deploymentConfig->get('cache/frontend/default/backend_options') ?? [];
        $compressionLib = $cacheConfig['compression_lib'] ?? '';
        
        if (!empty($cacheConfig) && empty($compressionLib)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'Redis',
                'No compression library specified',
                'Specify compression library for better compression performance.',
                'Not specified',
                'gzip, lzf, or snappy'
            );
        } elseif ($compressionLib === 'gzip') {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'Redis',
                'Using gzip compression',
                'Consider using lzf or snappy for better performance.',
                'gzip',
                'lzf or snappy'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check Redis extension
     *
     * @return IssueInterface[]
     */
    private function checkRedisExtension(): array
    {
        $issues = [];
        
        // Check which Redis extension is installed
        $hasPhpRedis = extension_loaded('redis');
        $hasPredis = class_exists('Predis\\Client');
        
        if (!$hasPhpRedis && !$hasPredis) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'Redis',
                'No Redis PHP extension',
                'Redis extension is required for Redis functionality.',
                'Not installed',
                'phpredis extension'
            );
        } elseif (!$hasPhpRedis && $hasPredis) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Redis',
                'Using Predis library',
                'phpredis extension provides better performance than Predis library.',
                'Predis',
                'phpredis extension'
            );
        }
        
        return $issues;
    }
}