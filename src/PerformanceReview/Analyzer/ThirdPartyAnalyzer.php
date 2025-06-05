<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Component\ComponentRegistrarInterface;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;

/**
 * Third-party extension analyzer
 */
class ThirdPartyAnalyzer
{
    /**
     * Known problematic extensions
     */
    private const PROBLEMATIC_EXTENSIONS = [
        'GT_Gtspeed' => 'Known to cause performance issues with page loading',
        'Amasty_Fpc' => 'Conflicts with built-in Full Page Cache',
        'Xtento_OrderExport' => 'Can cause memory issues with large exports',
        'Wyomind_SimpleGoogleShopping' => 'Resource intensive feed generation',
        'Mirasvit_Seo' => 'Can slow down category pages with large catalogs',
        'Mageplaza_LayeredNavigation' => 'Performance impact on category pages',
        'Mageworx_OptionFeatures' => 'Heavy JavaScript on product pages',
        'Webkul_Marketplace' => 'Database intensive operations',
        'Magento_SampleData' => 'Should be removed from production',
        'MSP_DevTools' => 'Development tool should not be in production'
    ];
    
    /**
     * Extension quality indicators
     */
    private const QUALITY_INDICATORS = [
        'deprecated_code' => [
            'Zend_',
            'Varien_',
            'Magento\Framework\Model\Resource\Db\AbstractDb',
            'each(',
            'create_function'
        ],
        'bad_practices' => [
            'ObjectManager::getInstance',
            'include_once',
            'require_once',
            'eval(',
            '__construct.*Interceptor'
        ]
    ];
    
    /**
     * @var ModuleListInterface
     */
    private ModuleListInterface $moduleList;
    
    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $productMetadata;
    
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
     * @param ProductMetadataInterface $productMetadata
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        ModuleListInterface $moduleList,
        ProductMetadataInterface $productMetadata,
        ComponentRegistrarInterface $componentRegistrar,
        IssueFactory $issueFactory
    ) {
        $this->moduleList = $moduleList;
        $this->productMetadata = $productMetadata;
        $this->componentRegistrar = $componentRegistrar;
        $this->issueFactory = $issueFactory;
    }
    
    /**
     * Analyze third-party extensions
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check for problematic extensions
        $issues = array_merge($issues, $this->checkProblematicExtensions());
        
        // Check extension compatibility
        $issues = array_merge($issues, $this->checkExtensionCompatibility());
        
        // Check for outdated extensions
        $issues = array_merge($issues, $this->checkOutdatedExtensions());
        
        // Check code quality
        $issues = array_merge($issues, $this->checkCodeQuality());
        
        // Check for development extensions
        $issues = array_merge($issues, $this->checkDevelopmentExtensions());
        
        return $issues;
    }
    
    /**
     * Check for problematic extensions
     *
     * @return IssueInterface[]
     */
    private function checkProblematicExtensions(): array
    {
        $issues = [];
        $foundProblematic = [];
        
        $enabledModules = $this->moduleList->getNames();
        
        foreach ($enabledModules as $moduleName) {
            if (isset(self::PROBLEMATIC_EXTENSIONS[$moduleName])) {
                $foundProblematic[$moduleName] = self::PROBLEMATIC_EXTENSIONS[$moduleName];
            }
        }
        
        if (!empty($foundProblematic)) {
            foreach ($foundProblematic as $module => $reason) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Third-party',
                    sprintf('Problematic extension: %s', $module),
                    $reason,
                    'Enabled',
                    'Review necessity'
                );
            }
        }
        
        return $issues;
    }
    
    /**
     * Check extension compatibility
     *
     * @return IssueInterface[]
     */
    private function checkExtensionCompatibility(): array
    {
        $issues = [];
        
        try {
            $magentoVersion = $this->productMetadata->getVersion();
            $incompatibleModules = [];
            
            // Check module composer.json for version constraints
            $modulePaths = $this->componentRegistrar->getPaths('module');
            
            foreach ($modulePaths as $moduleName => $path) {
                if ($this->isCoreModule($moduleName)) {
                    continue;
                }
                
                $composerFile = $path . '/composer.json';
                if (file_exists($composerFile)) {
                    $composerData = json_decode(file_get_contents($composerFile), true);
                    
                    if (isset($composerData['require']['magento/product-community-edition'])) {
                        $constraint = $composerData['require']['magento/product-community-edition'];
                        
                        // Simple check for obvious incompatibilities
                        if (strpos($constraint, '2.3') !== false && version_compare($magentoVersion, '2.4.0', '>=')) {
                            $incompatibleModules[] = $moduleName;
                        } elseif (strpos($constraint, '2.2') !== false && version_compare($magentoVersion, '2.3.0', '>=')) {
                            $incompatibleModules[] = $moduleName;
                        }
                    }
                }
            }
            
            if (!empty($incompatibleModules)) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Third-party',
                    'Potentially incompatible extensions',
                    'Extensions may not be compatible with current Magento version.',
                    implode(', ', array_slice($incompatibleModules, 0, 3)) . (count($incompatibleModules) > 3 ? '...' : ''),
                    'Updated extensions',
                    ['incompatible_modules' => $incompatibleModules]
                );
            }
        } catch (\Exception $e) {
            // Compatibility check failed
        }
        
        return $issues;
    }
    
    /**
     * Check for outdated extensions
     *
     * @return IssueInterface[]
     */
    private function checkOutdatedExtensions(): array
    {
        $issues = [];
        $outdatedIndicators = 0;
        
        $modulePaths = $this->componentRegistrar->getPaths('module');
        
        foreach ($modulePaths as $moduleName => $path) {
            if ($this->isCoreModule($moduleName)) {
                continue;
            }
            
            // Check if module has old structure
            if (file_exists($path . '/etc/config.xml') && !file_exists($path . '/etc/module.xml')) {
                $outdatedIndicators++;
            }
            
            // Check for old class naming
            if (is_dir($path . '/Model')) {
                $files = glob($path . '/Model/*.php');
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if (strpos($content, 'extends Mage_') !== false || 
                        strpos($content, 'extends Varien_') !== false) {
                        $outdatedIndicators++;
                        break;
                    }
                }
            }
        }
        
        if ($outdatedIndicators > 0) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Third-party',
                'Outdated extension structure detected',
                'Some extensions appear to use outdated Magento 1.x patterns.',
                $outdatedIndicators . ' indicators',
                'Modern extensions'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check code quality
     *
     * @return IssueInterface[]
     */
    private function checkCodeQuality(): array
    {
        $issues = [];
        $qualityIssues = [];
        
        $modulePaths = $this->componentRegistrar->getPaths('module');
        
        foreach ($modulePaths as $moduleName => $path) {
            if ($this->isCoreModule($moduleName)) {
                continue;
            }
            
            $moduleIssues = [];
            
            // Scan PHP files for quality issues
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                
                $content = file_get_contents($file->getPathname());
                
                // Check for deprecated code
                foreach (self::QUALITY_INDICATORS['deprecated_code'] as $pattern) {
                    if (stripos($content, $pattern) !== false) {
                        $moduleIssues['deprecated'][] = $pattern;
                    }
                }
                
                // Check for bad practices
                foreach (self::QUALITY_INDICATORS['bad_practices'] as $pattern) {
                    if (stripos($content, $pattern) !== false) {
                        $moduleIssues['bad_practices'][] = $pattern;
                    }
                }
            }
            
            if (!empty($moduleIssues)) {
                $qualityIssues[$moduleName] = $moduleIssues;
            }
        }
        
        if (!empty($qualityIssues)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Third-party',
                'Code quality issues in extensions',
                'Extensions contain deprecated code or bad practices that may impact performance.',
                count($qualityIssues) . ' extensions with issues',
                'Quality extensions',
                ['quality_issues' => $qualityIssues]
            );
        }
        
        return $issues;
    }
    
    /**
     * Check for development extensions
     *
     * @return IssueInterface[]
     */
    private function checkDevelopmentExtensions(): array
    {
        $issues = [];
        $devExtensions = [];
        
        $devPatterns = [
            'Debug', 'Dev', 'Test', 'Demo', 'Sample', 'Example', 'Profiler', 'Toolbar'
        ];
        
        $enabledModules = $this->moduleList->getNames();
        
        foreach ($enabledModules as $moduleName) {
            foreach ($devPatterns as $pattern) {
                if (stripos($moduleName, $pattern) !== false) {
                    $devExtensions[] = $moduleName;
                    break;
                }
            }
        }
        
        if (!empty($devExtensions)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'Third-party',
                'Development extensions in production',
                'Development/debugging extensions should not be enabled in production.',
                implode(', ', array_slice($devExtensions, 0, 3)) . (count($devExtensions) > 3 ? '...' : ''),
                'Disabled in production',
                ['dev_extensions' => $devExtensions]
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
}