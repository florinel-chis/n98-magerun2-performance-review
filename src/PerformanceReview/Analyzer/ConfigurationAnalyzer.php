<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\State;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;

/**
 * Configuration analyzer
 */
class ConfigurationAnalyzer
{
    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $deploymentConfig;
    
    /**
     * @var State
     */
    private State $appState;
    
    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;
    
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
     * @param DeploymentConfig $deploymentConfig
     * @param State $appState
     * @param TypeListInterface $cacheTypeList
     * @param ScopeConfigInterface $scopeConfig
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        DeploymentConfig $deploymentConfig,
        State $appState,
        TypeListInterface $cacheTypeList,
        ScopeConfigInterface $scopeConfig,
        IssueFactory $issueFactory
    ) {
        $this->deploymentConfig = $deploymentConfig;
        $this->appState = $appState;
        $this->cacheTypeList = $cacheTypeList;
        $this->scopeConfig = $scopeConfig;
        $this->issueFactory = $issueFactory;
    }
    
    /**
     * Analyze configuration
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check mode
        $issues = array_merge($issues, $this->checkMode());
        
        // Check cache configuration
        $issues = array_merge($issues, $this->checkCacheConfiguration());
        
        // Check session configuration
        $issues = array_merge($issues, $this->checkSessionConfiguration());
        
        // Check JS/CSS settings
        $issues = array_merge($issues, $this->checkJsCssSettings());
        
        // Check flat catalog settings
        $issues = array_merge($issues, $this->checkFlatCatalogSettings());
        
        // Check cache types
        $issues = array_merge($issues, $this->checkCacheTypes());
        
        return $issues;
    }
    
    /**
     * Check application mode
     *
     * @return IssueInterface[]
     */
    private function checkMode(): array
    {
        $issues = [];
        
        try {
            $mode = $this->appState->getMode();
            
            if ($mode !== State::MODE_PRODUCTION) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Config',
                    'Switch from developer mode to production',
                    'Developer mode significantly impacts performance. Production mode disables certain debugging features and enables caching optimizations.',
                    $mode,
                    State::MODE_PRODUCTION
                );
            }
        } catch (\Exception $e) {
            // Mode detection failed
        }
        
        return $issues;
    }
    
    /**
     * Check cache configuration
     *
     * @return IssueInterface[]
     */
    private function checkCacheConfiguration(): array
    {
        $issues = [];
        
        try {
            $cacheConfig = $this->deploymentConfig->get('cache');
            
            if (!isset($cacheConfig['frontend']['default']['backend']) || 
                $cacheConfig['frontend']['default']['backend'] === 'Magento\\Framework\\Cache\\Backend\\File') {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Config',
                    'Use Redis for cache backend',
                    'File-based cache significantly impacts performance. Redis provides in-memory caching with much faster read/write operations.',
                    'File',
                    'Redis'
                );
            }
            
            // Check page cache
            if (!isset($cacheConfig['frontend']['page_cache']['backend']) || 
                $cacheConfig['frontend']['page_cache']['backend'] === 'Magento\\Framework\\Cache\\Backend\\File') {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Config',
                    'Use Varnish or Redis for page cache',
                    'File-based page cache severely limits performance. Varnish or Redis provide much better full page cache performance.',
                    'File',
                    'Varnish/Redis'
                );
            }
        } catch (\Exception $e) {
            // Cache config check failed
        }
        
        return $issues;
    }
    
    /**
     * Check session configuration
     *
     * @return IssueInterface[]
     */
    private function checkSessionConfiguration(): array
    {
        $issues = [];
        
        try {
            $sessionConfig = $this->deploymentConfig->get('session');
            
            if (!isset($sessionConfig['save']) || $sessionConfig['save'] === 'files') {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Config',
                    'Use Redis for session storage',
                    'File-based sessions can impact performance with high traffic. Redis provides better session handling and supports session clustering.',
                    'files',
                    'redis'
                );
            }
        } catch (\Exception $e) {
            // Session config check failed
        }
        
        return $issues;
    }
    
    /**
     * Check JS/CSS settings
     *
     * @return IssueInterface[]
     */
    private function checkJsCssSettings(): array
    {
        $issues = [];
        
        // Check JS minification
        if (!$this->scopeConfig->isSetFlag('dev/js/minify_files', ScopeInterface::SCOPE_STORE)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Config',
                'Enable JavaScript minification',
                'Minifying JavaScript files reduces file size by removing unnecessary characters, resulting in faster downloads.',
                'Disabled',
                'Enabled'
            );
        }
        
        // Check CSS minification
        if (!$this->scopeConfig->isSetFlag('dev/css/minify_files', ScopeInterface::SCOPE_STORE)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Config',
                'Enable CSS minification',
                'Minifying CSS files reduces file size and improves page load times.',
                'Disabled',
                'Enabled'
            );
        }
        
        // Check JS bundling
        if (!$this->scopeConfig->isSetFlag('dev/js/enable_js_bundling', ScopeInterface::SCOPE_STORE)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'Config',
                'Consider enabling JavaScript bundling',
                'JS bundling reduces the number of HTTP requests by combining multiple JS files.',
                'Disabled',
                'Enabled'
            );
        }
        
        // Check CSS merging
        if (!$this->scopeConfig->isSetFlag('dev/css/merge_css_files', ScopeInterface::SCOPE_STORE)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Config',
                'Enable CSS merging',
                'Merging CSS files reduces the number of HTTP requests, improving page load performance.',
                'Disabled',
                'Enabled'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check flat catalog settings
     *
     * @return IssueInterface[]
     */
    private function checkFlatCatalogSettings(): array
    {
        $issues = [];
        
        // Note: Flat catalog is deprecated in newer Magento versions
        try {
            $magentoVersion = $this->deploymentConfig->get('version');
            if (empty($magentoVersion)) {
                // Try to get version from composer
                $composerFile = BP . '/composer.json';
                if (file_exists($composerFile)) {
                    $composerData = json_decode(file_get_contents($composerFile), true);
                    $magentoVersion = $composerData['version'] ?? '2.4.0';
                } else {
                    $magentoVersion = '2.4.0'; // Default to 2.4.0
                }
            }
        } catch (\Exception $e) {
            $magentoVersion = '2.4.0'; // Default to 2.4.0
        }
        
        if (version_compare($magentoVersion, '2.3.0', '<')) {
            if (!$this->scopeConfig->isSetFlag('catalog/frontend/flat_catalog_category', ScopeInterface::SCOPE_STORE)) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Config',
                    'Enable flat catalog for categories',
                    'Flat catalog improves category page performance by denormalizing EAV data (for Magento < 2.3).',
                    'Disabled',
                    'Enabled'
                );
            }
            
            if (!$this->scopeConfig->isSetFlag('catalog/frontend/flat_catalog_product', ScopeInterface::SCOPE_STORE)) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Config',
                    'Enable flat catalog for products',
                    'Flat catalog improves product listing performance by denormalizing EAV data (for Magento < 2.3).',
                    'Disabled',
                    'Enabled'
                );
            }
        }
        
        return $issues;
    }
    
    /**
     * Check cache types
     *
     * @return IssueInterface[]
     */
    private function checkCacheTypes(): array
    {
        $issues = [];
        $disabledCaches = [];
        
        foreach ($this->cacheTypeList->getTypes() as $cacheType) {
            if (!$cacheType->getStatus()) {
                $disabledCaches[] = $cacheType->getCacheType();
            }
        }
        
        if (!empty($disabledCaches)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'Config',
                'Enable all cache types',
                sprintf('%d cache type(s) are disabled. Disabled caches significantly impact performance.', count($disabledCaches)),
                implode(', ', $disabledCaches),
                'All enabled',
                ['disabled_caches' => $disabledCaches]
            );
        }
        
        return $issues;
    }
}