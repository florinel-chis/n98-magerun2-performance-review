<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Asset\Minification;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;

/**
 * Frontend analyzer
 */
class FrontendAnalyzer
{
    /**
     * Configuration paths
     */
    private const XML_PATH_JS_BUNDLING = 'dev/js/enable_js_bundling';
    private const XML_PATH_JS_MINIFY = 'dev/js/minify_files';
    private const XML_PATH_JS_MERGE = 'dev/js/merge_files';
    private const XML_PATH_CSS_MINIFY = 'dev/css/minify_files';
    private const XML_PATH_CSS_MERGE = 'dev/css/merge_css_files';
    private const XML_PATH_HTML_MINIFY = 'dev/template/minify_html';
    private const XML_PATH_SIGN_STATIC = 'dev/static/sign';
    private const XML_PATH_LAZY_LOADING = 'catalog/frontend/lazy_loading_enable';
    private const XML_PATH_IMAGE_OPTIMIZATION = 'system/upload_configuration/enable_resize_images';
    
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;
    
    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;
    
    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;
    
    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Filesystem $filesystem
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Filesystem $filesystem,
        IssueFactory $issueFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->filesystem = $filesystem;
        $this->issueFactory = $issueFactory;
    }
    
    /**
     * Analyze frontend configuration
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check JavaScript optimization
        $issues = array_merge($issues, $this->checkJavaScriptOptimization());
        
        // Check CSS optimization
        $issues = array_merge($issues, $this->checkCssOptimization());
        
        // Check HTML minification
        $issues = array_merge($issues, $this->checkHtmlMinification());
        
        // Check static content signing
        $issues = array_merge($issues, $this->checkStaticContentSigning());
        
        // Check image optimization
        $issues = array_merge($issues, $this->checkImageOptimization());
        
        // Check lazy loading
        $issues = array_merge($issues, $this->checkLazyLoading());
        
        // Check theme count
        $issues = array_merge($issues, $this->checkThemeCount());
        
        return $issues;
    }
    
    /**
     * Check JavaScript optimization
     *
     * @return IssueInterface[]
     */
    private function checkJavaScriptOptimization(): array
    {
        $issues = [];
        
        $jsMinify = $this->scopeConfig->isSetFlag(self::XML_PATH_JS_MINIFY, ScopeInterface::SCOPE_STORE);
        $jsMerge = $this->scopeConfig->isSetFlag(self::XML_PATH_JS_MERGE, ScopeInterface::SCOPE_STORE);
        $jsBundling = $this->scopeConfig->isSetFlag(self::XML_PATH_JS_BUNDLING, ScopeInterface::SCOPE_STORE);
        
        if (!$jsMinify) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'Frontend',
                'JavaScript minification disabled',
                'Minifying JavaScript reduces file size and improves page load times.',
                'Disabled',
                'Enabled'
            );
        }
        
        if (!$jsMerge && !$jsBundling) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Frontend',
                'JavaScript merging/bundling disabled',
                'Merging or bundling JavaScript files reduces HTTP requests and improves performance.',
                'Both disabled',
                'Enable merging or bundling'
            );
        }
        
        if ($jsMerge && $jsBundling) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'Frontend',
                'Both JS merge and bundling enabled',
                'Using both merge and bundling can cause conflicts. Choose one approach.',
                'Both enabled',
                'Use one method'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check CSS optimization
     *
     * @return IssueInterface[]
     */
    private function checkCssOptimization(): array
    {
        $issues = [];
        
        $cssMinify = $this->scopeConfig->isSetFlag(self::XML_PATH_CSS_MINIFY, ScopeInterface::SCOPE_STORE);
        $cssMerge = $this->scopeConfig->isSetFlag(self::XML_PATH_CSS_MERGE, ScopeInterface::SCOPE_STORE);
        
        if (!$cssMinify) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'Frontend',
                'CSS minification disabled',
                'Minifying CSS reduces file size and improves page load times.',
                'Disabled',
                'Enabled'
            );
        }
        
        if (!$cssMerge) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Frontend',
                'CSS merging disabled',
                'Merging CSS files reduces HTTP requests and improves performance.',
                'Disabled',
                'Enabled'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check HTML minification
     *
     * @return IssueInterface[]
     */
    private function checkHtmlMinification(): array
    {
        $issues = [];
        
        $htmlMinify = $this->scopeConfig->isSetFlag(self::XML_PATH_HTML_MINIFY, ScopeInterface::SCOPE_STORE);
        
        if (!$htmlMinify) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'Frontend',
                'HTML minification disabled',
                'Minifying HTML reduces page size and improves load times.',
                'Disabled',
                'Enabled'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check static content signing
     *
     * @return IssueInterface[]
     */
    private function checkStaticContentSigning(): array
    {
        $issues = [];
        
        $staticSigning = $this->scopeConfig->isSetFlag(self::XML_PATH_SIGN_STATIC, ScopeInterface::SCOPE_STORE);
        
        if (!$staticSigning) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'Frontend',
                'Static content signing disabled',
                'Signing static files enables better browser caching after deployments.',
                'Disabled',
                'Enabled'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check image optimization
     *
     * @return IssueInterface[]
     */
    private function checkImageOptimization(): array
    {
        $issues = [];
        
        try {
            // Check for WebP support
            $pubDir = $this->filesystem->getDirectoryRead(DirectoryList::PUB);
            $mediaPath = $pubDir->getAbsolutePath() . '/media/catalog/product';
            
            if (is_dir($mediaPath)) {
                $hasWebP = false;
                $files = scandir($mediaPath);
                
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'webp') {
                        $hasWebP = true;
                        break;
                    }
                }
                
                if (!$hasWebP) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Frontend',
                        'No WebP images detected',
                        'WebP format provides better compression than JPEG/PNG. Consider using WebP for product images.',
                        'No WebP images',
                        'WebP format enabled'
                    );
                }
            }
            
            // Check image resize configuration
            $imageResize = $this->scopeConfig->isSetFlag(
                self::XML_PATH_IMAGE_OPTIMIZATION,
                ScopeInterface::SCOPE_STORE
            );
            
            if (!$imageResize) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Frontend',
                    'Image resize on upload disabled',
                    'Resizing images on upload prevents oversized images from being served.',
                    'Disabled',
                    'Enabled'
                );
            }
        } catch (\Exception $e) {
            // Image optimization check failed
        }
        
        return $issues;
    }
    
    /**
     * Check lazy loading
     *
     * @return IssueInterface[]
     */
    private function checkLazyLoading(): array
    {
        $issues = [];
        
        // Check if lazy loading path exists (Magento 2.4.2+)
        try {
            $lazyLoading = $this->scopeConfig->isSetFlag(
                self::XML_PATH_LAZY_LOADING,
                ScopeInterface::SCOPE_STORE
            );
            
            if (!$lazyLoading) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'Frontend',
                    'Lazy loading disabled',
                    'Lazy loading images improves initial page load performance.',
                    'Disabled',
                    'Enabled'
                );
            }
        } catch (\Exception $e) {
            // Lazy loading configuration may not exist in older versions
        }
        
        return $issues;
    }
    
    /**
     * Check theme count
     *
     * @return IssueInterface[]
     */
    private function checkThemeCount(): array
    {
        $issues = [];
        
        try {
            $designDir = $this->filesystem->getDirectoryRead(DirectoryList::APP);
            $themePath = $designDir->getAbsolutePath() . '/design/frontend';
            
            if (is_dir($themePath)) {
                $themeCount = 0;
                $vendors = scandir($themePath);
                
                foreach ($vendors as $vendor) {
                    if ($vendor === '.' || $vendor === '..') {
                        continue;
                    }
                    
                    $vendorPath = $themePath . '/' . $vendor;
                    if (is_dir($vendorPath)) {
                        $themes = scandir($vendorPath);
                        foreach ($themes as $theme) {
                            if ($theme === '.' || $theme === '..') {
                                continue;
                            }
                            
                            if (is_dir($vendorPath . '/' . $theme)) {
                                $themeCount++;
                            }
                        }
                    }
                }
                
                if ($themeCount > 5) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_LOW,
                        'Frontend',
                        'Multiple themes detected',
                        'Having many themes increases deployment time and maintenance complexity.',
                        $themeCount . ' themes',
                        'Minimal themes'
                    );
                }
            }
        } catch (\Exception $e) {
            // Theme count check failed
        }
        
        return $issues;
    }
}