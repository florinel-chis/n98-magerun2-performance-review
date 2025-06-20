<?php
declare(strict_types=1);

namespace MyCompany\PerformanceAnalyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Api\DependencyAwareInterface;
use PerformanceReview\Model\Issue\Collection;

/**
 * Example custom analyzer for Elasticsearch health
 * 
 * This analyzer checks Elasticsearch cluster health and index status
 */
class ElasticsearchHealthAnalyzer implements AnalyzerCheckInterface, DependencyAwareInterface
{
    /**
     * @var array
     */
    private array $dependencies = [];
    
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
        $scopeConfig = $this->dependencies['scopeConfig'] ?? null;
        if (!$scopeConfig) {
            return;
        }
        
        // Check if Elasticsearch is configured as search engine
        $searchEngine = $scopeConfig->getValue('catalog/search/engine');
        if (!in_array($searchEngine, ['elasticsearch7', 'elasticsearch6', 'elasticsearch5'])) {
            return;
        }
        
        // Get Elasticsearch configuration
        $host = $scopeConfig->getValue('catalog/search/' . $searchEngine . '_server_hostname') ?: 'localhost';
        $port = $scopeConfig->getValue('catalog/search/' . $searchEngine . '_server_port') ?: '9200';
        $prefix = $scopeConfig->getValue('catalog/search/' . $searchEngine . '_index_prefix') ?: 'magento2';
        
        $baseUrl = sprintf('http://%s:%s', $host, $port);
        
        try {
            // Check cluster health
            $healthResponse = $this->makeRequest($baseUrl . '/_cluster/health');
            
            if ($healthResponse) {
                $health = json_decode($healthResponse, true);
                
                // Check cluster status
                if (isset($health['status'])) {
                    if ($health['status'] === 'red') {
                        $results->createIssue()
                            ->setPriority('high')
                            ->setCategory('Elasticsearch')
                            ->setIssue('Elasticsearch cluster status is RED')
                            ->setDetails(
                                'Red status indicates that some primary shards are not allocated. ' .
                                'This affects search functionality.'
                            )
                            ->setCurrentValue('RED')
                            ->setRecommendedValue('GREEN')
                            ->add();
                    } elseif ($health['status'] === 'yellow') {
                        $results->createIssue()
                            ->setPriority('medium')
                            ->setCategory('Elasticsearch')
                            ->setIssue('Elasticsearch cluster status is YELLOW')
                            ->setDetails(
                                'Yellow status indicates that replica shards are not allocated. ' .
                                'This reduces redundancy but search still works.'
                            )
                            ->setCurrentValue('YELLOW')
                            ->setRecommendedValue('GREEN')
                            ->add();
                    }
                }
                
                // Check unassigned shards
                if (isset($health['unassigned_shards']) && $health['unassigned_shards'] > 0) {
                    $results->createIssue()
                        ->setPriority('medium')
                        ->setCategory('Elasticsearch')
                        ->setIssue('Elasticsearch has unassigned shards')
                        ->setDetails(
                            'Unassigned shards indicate allocation problems. ' .
                            'This may affect search performance and reliability.'
                        )
                        ->setCurrentValue($health['unassigned_shards'] . ' unassigned shards')
                        ->setRecommendedValue('0 unassigned shards')
                        ->add();
                }
            }
            
            // Check index stats
            $indexName = $prefix . '_product_*';
            $statsResponse = $this->makeRequest($baseUrl . '/' . $indexName . '/_stats');
            
            if ($statsResponse) {
                $stats = json_decode($statsResponse, true);
                
                if (isset($stats['_all']['total']['store']['size_in_bytes'])) {
                    $sizeInGB = $stats['_all']['total']['store']['size_in_bytes'] / 1024 / 1024 / 1024;
                    
                    if ($sizeInGB > 50) {
                        $results->createIssue()
                            ->setPriority('low')
                            ->setCategory('Elasticsearch')
                            ->setIssue('Large Elasticsearch index size')
                            ->setDetails(
                                'Large index size may impact indexing and search performance. ' .
                                'Consider optimizing indexed attributes.'
                            )
                            ->setCurrentValue(sprintf('%.1f GB', $sizeInGB))
                            ->setRecommendedValue('< 50 GB')
                            ->add();
                    }
                }
            }
            
        } catch (\Exception $e) {
            // Connection failed
            $results->createIssue()
                ->setPriority('high')
                ->setCategory('Elasticsearch')
                ->setIssue('Cannot connect to Elasticsearch')
                ->setDetails(
                    'Failed to connect to Elasticsearch at ' . $baseUrl . '. ' .
                    'Search functionality may be impaired.'
                )
                ->add();
        }
    }
    
    /**
     * Make HTTP request to Elasticsearch
     * 
     * @param string $url
     * @return string|false
     */
    private function makeRequest(string $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200) ? $response : false;
    }
}