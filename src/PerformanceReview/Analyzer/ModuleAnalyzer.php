<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Component\ComponentRegistrarInterface;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;

/**
 * Module analyzer
 */
class ModuleAnalyzer
{
    /**
     * Performance impacting modules
     */
    private const PERFORMANCE_IMPACTING_MODULES = [
        'Magento_Logging' => 'Extensive database logging can impact performance',
        'Magento_AdminGws' => 'Admin permissions checking adds overhead',
        'Magento_Staging' => 'Content staging adds database complexity',
        'Magento_CatalogStaging' => 'Catalog staging increases database size',
        'Magento_CatalogPermissions' => 'Category/product permissions add query complexity',
        'Magento_CustomerSegment' => 'Customer segmentation adds processing overhead',
        'Magento_TargetRule' => 'Related product rules add query complexity',
        'Temando_Shipping' => 'Known to cause performance issues',
        'Vertex_Tax' => 'External API calls can slow checkout',
        'Dotdigitalgroup_Email' => 'Synchronization can impact performance',
        'Dotdigitalgroup_Chat' => 'Real-time features add overhead',
        'Klarna_Core' => 'Payment processing overhead',
        'Klarna_Ordermanagement' => 'Order synchronization overhead',
        'Amazon_Payment' => 'External API dependencies',
        'Amazon_Login' => 'External authentication overhead',
        'MSP_TwoFactorAuth' => 'Additional authentication checks',
        'Amasty_*' => 'Some Amasty modules impact performance',
        'Mirasvit_*' => 'Some Mirasvit modules impact performance',
        'Aheadworks_*' => 'Some Aheadworks modules impact performance'
    ];
    
    /**
     * Duplicate functionality modules
     */
    private const DUPLICATE_FUNCTIONALITY_GROUPS = [
        'search' => [
            'Magento_Elasticsearch',
            'Magento_Elasticsearch6',
            'Magento_Elasticsearch7',
            'Magento_CatalogSearch',
            'Amasty_ElasticSearch',
            'Amasty_Xsearch',
            'Mirasvit_Search',
            'Mirasvit_SearchElastic',
            'Smile_ElasticsuiteCore'
        ],
        'layered_navigation' => [
            'Amasty_Shopby',
            'Mirasvit_LayeredNavigation',
            'Aheadworks_Layerednav',
            'Emthemes_FilterProducts'
        ],
        'seo' => [
            'Amasty_SeoToolKit',
            'Mirasvit_Seo',
            'Aheadworks_Seo',
            'Mageplaza_Seo'
        ],
        'cache' => [
            'Amasty_Fpc',
            'Mirasvit_Cache',
            'Lesti_Fpc',
            'Magento_PageCache'
        ]
    ];
    
    /**
     * @var ModuleListInterface
     */
    private ModuleListInterface $moduleList;
    
    /**
     * @var ModuleManager
     */
    private ModuleManager $moduleManager;
    
    /**
     * @var ComponentRegistrarInterface
     */
    private ComponentRegistrarInterface $componentRegistrar;
    
    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;
    
    /**
     * Constructor
     *
     * @param ModuleListInterface $moduleList
     * @param ModuleManager $moduleManager
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        ModuleListInterface $moduleList,
        ModuleManager $moduleManager,
        ComponentRegistrarInterface $componentRegistrar,
        IssueFactory $issueFactory
    ) {
        $this->moduleList = $moduleList;
        $this->moduleManager = $moduleManager;
        $this->componentRegistrar = $componentRegistrar;
        $this->issueFactory = $issueFactory;
    }
    
    /**
     * Analyze modules
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check third-party module count
        $issues = array_merge($issues, $this->checkThirdPartyModuleCount());
        
        // Check for performance impacting modules
        $issues = array_merge($issues, $this->checkPerformanceImpactingModules());
        
        // Check for disabled modules in code
        $issues = array_merge($issues, $this->checkDisabledModules());
        
        // Check for duplicate functionality
        $issues = array_merge($issues, $this->checkDuplicateFunctionality());
        
        return $issues;
    }
    
    /**
     * Check third-party module count
     *
     * @return IssueInterface[]
     */
    private function checkThirdPartyModuleCount(): array
    {
        $issues = [];
        
        $allModules = $this->moduleList->getNames();
        $thirdPartyModules = [];
        
        foreach ($allModules as $moduleName) {
            if (!$this->isCoreModule($moduleName)) {
                $thirdPartyModules[] = $moduleName;
            }
        }
        
        $count = count($thirdPartyModules);
        
        if ($count > 50) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'Modules',
                'Excessive number of third-party modules',
                'Too many third-party modules can significantly impact performance, increase complexity, and cause conflicts.',
                (string) $count,
                'Under 30',
                ['module_list' => $thirdPartyModules]
            );
        } elseif ($count > 30) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Modules',
                'High number of third-party modules',
                'Many third-party modules can impact performance. Review and remove unused modules.',
                (string) $count,
                'Under 30',
                ['module_list' => $thirdPartyModules]
            );
        }
        
        return $issues;
    }
    
    /**
     * Check for performance impacting modules
     *
     * @return IssueInterface[]
     */
    private function checkPerformanceImpactingModules(): array
    {
        $issues = [];
        $impactingModules = [];
        
        $enabledModules = $this->moduleList->getNames();
        
        foreach ($enabledModules as $moduleName) {
            foreach (self::PERFORMANCE_IMPACTING_MODULES as $pattern => $reason) {
                if ($this->moduleMatches($moduleName, $pattern) && $this->moduleManager->isEnabled($moduleName)) {
                    $impactingModules[$moduleName] = $reason;
                }
            }
        }
        
        if (!empty($impactingModules)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Modules',
                'Performance-impacting modules detected',
                sprintf('%d module(s) are known to impact performance. Review if they are necessary.', count($impactingModules)),
                implode(', ', array_keys(array_slice($impactingModules, 0, 3))) . (count($impactingModules) > 3 ? '...' : ''),
                'Only necessary modules enabled',
                ['impacting_modules' => $impactingModules]
            );
        }
        
        return $issues;
    }
    
    /**
     * Check for disabled modules still in codebase
     *
     * @return IssueInterface[]
     */
    private function checkDisabledModules(): array
    {
        $issues = [];
        $disabledModules = [];
        
        // Get all registered modules
        $allComponents = $this->componentRegistrar->getPaths('module');
        
        foreach ($allComponents as $moduleName => $path) {
            if (!$this->moduleManager->isEnabled($moduleName) && !$this->isCoreModule($moduleName)) {
                // Check if it's in app/code (custom modules)
                if (strpos($path, '/app/code/') !== false) {
                    $disabledModules[] = $moduleName;
                }
            }
        }
        
        if (!empty($disabledModules)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'Modules',
                'Disabled modules in codebase',
                'Disabled modules still consume resources during compilation. Consider removing them completely.',
                (string) count($disabledModules),
                '0',
                ['disabled_modules' => $disabledModules]
            );
        }
        
        return $issues;
    }
    
    /**
     * Check for duplicate functionality
     *
     * @return IssueInterface[]
     */
    private function checkDuplicateFunctionality(): array
    {
        $issues = [];
        $duplicates = [];
        
        $enabledModules = $this->moduleList->getNames();
        
        foreach (self::DUPLICATE_FUNCTIONALITY_GROUPS as $functionality => $moduleGroup) {
            $foundModules = [];
            
            foreach ($enabledModules as $enabledModule) {
                foreach ($moduleGroup as $pattern) {
                    if ($this->moduleMatches($enabledModule, $pattern)) {
                        $foundModules[] = $enabledModule;
                    }
                }
            }
            
            if (count($foundModules) > 1) {
                $duplicates[$functionality] = [
                    'count' => count($foundModules),
                    'modules' => $foundModules
                ];
            }
        }
        
        if (!empty($duplicates)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Modules',
                'Duplicate functionality detected',
                'Multiple modules providing similar functionality can cause conflicts and performance issues.',
                sprintf('%d group(s) with duplicates', count($duplicates)),
                'Single module per functionality',
                ['duplicate_modules' => $duplicates]
            );
        }
        
        return $issues;
    }
    
    /**
     * Check if module is core Magento module
     *
     * @param string $moduleName
     * @return bool
     */
    private function isCoreModule(string $moduleName): bool
    {
        return strpos($moduleName, 'Magento_') === 0;
    }
    
    /**
     * Check if module name matches pattern
     *
     * @param string $moduleName
     * @param string $pattern
     * @return bool
     */
    private function moduleMatches(string $moduleName, string $pattern): bool
    {
        // Handle wildcard patterns
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
            return preg_match($regex, $moduleName) === 1;
        }
        
        return $moduleName === $pattern;
    }
}