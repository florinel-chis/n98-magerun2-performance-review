<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Api\ConfigAwareInterface;
use PerformanceReview\Api\DependencyAwareInterface;
use PerformanceReview\Model\Issue\Collection;

/**
 * Adapter to run legacy analyzers through the new extensible system
 */
class LegacyAnalyzerAdapter implements AnalyzerCheckInterface, ConfigAwareInterface, DependencyAwareInterface
{
    /**
     * @var object Legacy analyzer instance
     */
    private $legacyAnalyzer;
    
    /**
     * @var string
     */
    private string $analyzerClass;
    
    /**
     * @var array
     */
    private array $config = [];
    
    /**
     * @var array
     */
    private array $dependencies = [];
    
    /**
     * Constructor
     * 
     * @param string $analyzerClass Fully qualified class name of legacy analyzer
     */
    public function __construct(string $analyzerClass)
    {
        $this->analyzerClass = $analyzerClass;
    }
    
    /**
     * @inheritdoc
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
    
    /**
     * @inheritdoc
     */
    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
        $this->initializeLegacyAnalyzer();
    }
    
    /**
     * @inheritdoc
     */
    public function analyze(Collection $results): void
    {
        if (!$this->legacyAnalyzer) {
            throw new \RuntimeException('Legacy analyzer not initialized. Dependencies must be set first.');
        }
        
        try {
            // Call the legacy analyze method
            $issues = $this->legacyAnalyzer->analyze();
            
            // Convert legacy issues to new format
            foreach ($issues as $issue) {
                $builder = $results->createIssue()
                    ->setPriority($issue->getPriority())
                    ->setCategory($issue->getCategory())
                    ->setIssue($issue->getIssue())
                    ->setDetails($issue->getDetails());
                
                if ($issue->getCurrentValue() !== null) {
                    $builder->setCurrentValue($issue->getCurrentValue());
                }
                
                if ($issue->getRecommendedValue() !== null) {
                    $builder->setRecommendedValue($issue->getRecommendedValue());
                }
                
                // Transfer any additional data
                $data = $issue->getData();
                foreach ($data as $key => $value) {
                    if (!in_array($key, ['priority', 'category', 'issue', 'details', 'current_value', 'recommended_value'])) {
                        $builder->setData($key, $value);
                    }
                }
                
                $builder->add();
            }
        } catch (\Exception $e) {
            // Create an error issue for the failed analyzer
            $results->createIssue()
                ->setPriority('medium')
                ->setCategory('System')
                ->setIssue(sprintf('Analyzer %s failed', $this->getAnalyzerName()))
                ->setDetails($e->getMessage())
                ->add();
        }
    }
    
    /**
     * Initialize the legacy analyzer with dependencies
     * 
     * @return void
     */
    private function initializeLegacyAnalyzer(): void
    {
        // Map of analyzer classes to their required dependencies
        $analyzerDependencyMap = [
            'PerformanceReview\Analyzer\ConfigurationAnalyzer' => [
                'deploymentConfig',
                'appState',
                'cacheTypeList',
                'scopeConfig',
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\DatabaseAnalyzer' => [
                'resourceConnection',
                'productCollectionFactory',
                'categoryCollectionFactory',
                'urlRewriteCollectionFactory',
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\ModuleAnalyzer' => [
                'moduleList',
                'moduleManager',
                'componentRegistrar',
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\CodebaseAnalyzer' => [
                'filesystem',
                'componentRegistrar',
                'moduleList',
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\FrontendAnalyzer' => [
                'scopeConfig',
                'filesystem',
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\IndexerCronAnalyzer' => [
                'indexerRegistry',
                'resourceConnection',
                'scheduleCollectionFactory',
                'scopeConfig',
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\PhpConfigurationAnalyzer' => [
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\MysqlConfigurationAnalyzer' => [
                'resourceConnection',
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\RedisConfigurationAnalyzer' => [
                'deploymentConfig',
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\ApiAnalyzer' => [
                'scopeConfig',
                'resourceConnection',
                'issueFactory'
            ],
            'PerformanceReview\Analyzer\ThirdPartyAnalyzer' => [
                'moduleList',
                'productMetadata',
                'componentRegistrar',
                'issueFactory'
            ]
        ];
        
        $requiredDeps = $analyzerDependencyMap[$this->analyzerClass] ?? [];
        $constructorArgs = [];
        
        foreach ($requiredDeps as $depKey) {
            if (!isset($this->dependencies[$depKey])) {
                throw new \RuntimeException(
                    sprintf('Required dependency "%s" not provided for %s', $depKey, $this->analyzerClass)
                );
            }
            $constructorArgs[] = $this->dependencies[$depKey];
        }
        
        $this->legacyAnalyzer = new $this->analyzerClass(...$constructorArgs);
    }
    
    /**
     * Get analyzer name from class
     * 
     * @return string
     */
    private function getAnalyzerName(): string
    {
        $parts = explode('\\', $this->analyzerClass);
        $className = end($parts);
        return str_replace('Analyzer', '', $className);
    }
}