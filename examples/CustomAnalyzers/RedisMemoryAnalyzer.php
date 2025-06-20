<?php
declare(strict_types=1);

namespace MyCompany\PerformanceAnalyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Api\ConfigAwareInterface;
use PerformanceReview\Api\DependencyAwareInterface;
use PerformanceReview\Model\Issue\Collection;

/**
 * Example custom analyzer for Redis memory usage
 * 
 * This analyzer checks Redis memory fragmentation and usage patterns
 */
class RedisMemoryAnalyzer implements AnalyzerCheckInterface, ConfigAwareInterface, DependencyAwareInterface
{
    /**
     * @var array
     */
    private array $config = [];
    
    /**
     * @var array
     */
    private array $dependencies = [];
    
    /**
     * Set configuration for the analyzer
     * 
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
    
    /**
     * Set Magento dependencies
     * 
     * @param array $dependencies
     * @return void
     */
    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }
    
    /**
     * Run the analysis
     * 
     * @param Collection $results
     * @return void
     */
    public function analyze(Collection $results): void
    {
        $threshold = $this->config['fragmentation_threshold'] ?? 1.5;
        $memoryLimitMB = $this->config['memory_limit_mb'] ?? 1024;
        
        // Get Redis connection info from deployment config
        $deploymentConfig = $this->dependencies['deploymentConfig'] ?? null;
        if (!$deploymentConfig) {
            return;
        }
        
        $cacheConfig = $deploymentConfig->get('cache');
        if (!isset($cacheConfig['frontend']['default']['backend_options']['server'])) {
            return;
        }
        
        $redisServer = $cacheConfig['frontend']['default']['backend_options']['server'];
        $redisPort = $cacheConfig['frontend']['default']['backend_options']['port'] ?? 6379;
        
        try {
            // Connect to Redis
            $redis = new \Redis();
            $redis->connect($redisServer, $redisPort);
            
            // Get Redis INFO
            $info = $redis->info('memory');
            
            // Check memory fragmentation
            $usedMemory = $info['used_memory'] ?? 0;
            $usedMemoryRss = $info['used_memory_rss'] ?? 0;
            
            if ($usedMemory > 0) {
                $fragmentation = $usedMemoryRss / $usedMemory;
                
                if ($fragmentation > $threshold) {
                    $results->createIssue()
                        ->setPriority('high')
                        ->setCategory('Redis')
                        ->setIssue('High Redis memory fragmentation')
                        ->setDetails(
                            'Memory fragmentation indicates wasted memory. ' .
                            'This can be caused by frequent key deletions or varying key sizes.'
                        )
                        ->setCurrentValue(sprintf('%.2f', $fragmentation))
                        ->setRecommendedValue(sprintf('< %.1f', $threshold))
                        ->add();
                }
            }
            
            // Check memory usage
            $usedMemoryMB = $usedMemory / 1024 / 1024;
            if ($usedMemoryMB > $memoryLimitMB) {
                $results->createIssue()
                    ->setPriority('medium')
                    ->setCategory('Redis')
                    ->setIssue('Redis memory usage exceeds limit')
                    ->setDetails(
                        'High memory usage may lead to evictions and performance degradation. ' .
                        'Consider increasing maxmemory or optimizing cache usage.'
                    )
                    ->setCurrentValue(sprintf('%.0f MB', $usedMemoryMB))
                    ->setRecommendedValue(sprintf('< %d MB', $memoryLimitMB))
                    ->add();
            }
            
            // Check evicted keys
            $evictedKeys = $info['evicted_keys'] ?? 0;
            if ($evictedKeys > 1000) {
                $results->createIssue()
                    ->setPriority('high')
                    ->setCategory('Redis')
                    ->setIssue('Redis is evicting keys')
                    ->setDetails(
                        'Key eviction indicates memory pressure. ' .
                        'This severely impacts cache effectiveness.'
                    )
                    ->setCurrentValue(number_format($evictedKeys) . ' keys evicted')
                    ->setRecommendedValue('0 evictions')
                    ->add();
            }
            
        } catch (\Exception $e) {
            // Redis connection failed - add as low priority issue
            $results->createIssue()
                ->setPriority('low')
                ->setCategory('Redis')
                ->setIssue('Could not connect to Redis for analysis')
                ->setDetails($e->getMessage())
                ->add();
        }
    }
}