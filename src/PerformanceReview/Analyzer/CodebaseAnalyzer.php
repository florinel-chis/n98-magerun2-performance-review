<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Module\ModuleListInterface;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;
use PerformanceReview\Util\ByteConverter;

/**
 * Codebase analyzer
 */
class CodebaseAnalyzer
{
    /**
     * File size thresholds
     */
    private const GENERATED_DIR_WARNING_SIZE = 1024 * 1024 * 1024; // 1GB
    private const GENERATED_DIR_CRITICAL_SIZE = 5 * 1024 * 1024 * 1024; // 5GB
    private const VAR_DIR_WARNING_SIZE = 10 * 1024 * 1024 * 1024; // 10GB
    
    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;
    
    /**
     * @var ComponentRegistrarInterface
     */
    private ComponentRegistrarInterface $componentRegistrar;
    
    /**
     * @var ModuleListInterface
     */
    private ModuleListInterface $moduleList;
    
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
     * @param Filesystem $filesystem
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param ModuleListInterface $moduleList
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        Filesystem $filesystem,
        ComponentRegistrarInterface $componentRegistrar,
        ModuleListInterface $moduleList,
        IssueFactory $issueFactory
    ) {
        $this->filesystem = $filesystem;
        $this->componentRegistrar = $componentRegistrar;
        $this->moduleList = $moduleList;
        $this->issueFactory = $issueFactory;
        $this->byteConverter = new ByteConverter();
    }
    
    /**
     * Analyze codebase
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check generated directory size
        $issues = array_merge($issues, $this->checkGeneratedDirectory());
        
        // Check var directory size
        $issues = array_merge($issues, $this->checkVarDirectory());
        
        // Check for custom code in app/code
        $issues = array_merge($issues, $this->checkCustomCode());
        
        // Check for modifications to core files
        $issues = array_merge($issues, $this->checkCoreModifications());
        
        // Check for large media files
        $issues = array_merge($issues, $this->checkMediaFiles());
        
        return $issues;
    }
    
    /**
     * Check generated directory size
     *
     * @return IssueInterface[]
     */
    private function checkGeneratedDirectory(): array
    {
        $issues = [];
        
        try {
            $generatedDir = $this->filesystem->getDirectoryRead(DirectoryList::GENERATED);
            
            if ($generatedDir->isExist()) {
                $size = $this->getDirectorySize($generatedDir->getAbsolutePath());
                
                if ($size > self::GENERATED_DIR_CRITICAL_SIZE) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_HIGH,
                        'Codebase',
                        'Generated directory exceeds 5GB',
                        'Extremely large generated directory impacts deployment and can cause disk space issues.',
                        $this->byteConverter->convert($size),
                        'Under 1GB'
                    );
                } elseif ($size > self::GENERATED_DIR_WARNING_SIZE) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Codebase',
                        'Generated directory exceeds 1GB',
                        'Large generated directory may indicate need for cleanup. Run setup:di:compile after cleaning.',
                        $this->byteConverter->convert($size),
                        'Under 1GB'
                    );
                }
            }
        } catch (\Exception $e) {
            // Generated directory check failed
        }
        
        return $issues;
    }
    
    /**
     * Check var directory size
     *
     * @return IssueInterface[]
     */
    private function checkVarDirectory(): array
    {
        $issues = [];
        
        try {
            $varDir = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
            
            if ($varDir->isExist()) {
                $size = $this->getDirectorySize($varDir->getAbsolutePath());
                
                if ($size > self::VAR_DIR_WARNING_SIZE) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Codebase',
                        'Var directory exceeds 10GB',
                        'Large var directory indicates need for cleanup of logs, reports, and cache files.',
                        $this->byteConverter->convert($size),
                        'Regular cleanup'
                    );
                }
            }
        } catch (\Exception $e) {
            // Var directory check failed
        }
        
        return $issues;
    }
    
    /**
     * Check for custom code
     *
     * @return IssueInterface[]
     */
    private function checkCustomCode(): array
    {
        $issues = [];
        
        try {
            $appCodeDir = $this->filesystem->getDirectoryRead(DirectoryList::APP);
            $codePath = $appCodeDir->getAbsolutePath() . '/code';
            
            if (is_dir($codePath)) {
                $customModuleCount = 0;
                $largeModules = [];
                
                // Get vendor directories
                $vendorDirs = ['Magento', 'Zend', 'Symfony', 'Laminas'];
                
                // Scan app/code for custom modules
                $vendors = scandir($codePath);
                foreach ($vendors as $vendor) {
                    if ($vendor === '.' || $vendor === '..' || in_array($vendor, $vendorDirs)) {
                        continue;
                    }
                    
                    $vendorPath = $codePath . '/' . $vendor;
                    if (is_dir($vendorPath)) {
                        $modules = scandir($vendorPath);
                        foreach ($modules as $module) {
                            if ($module === '.' || $module === '..') {
                                continue;
                            }
                            
                            $modulePath = $vendorPath . '/' . $module;
                            if (is_dir($modulePath)) {
                                $customModuleCount++;
                                
                                // Check module size
                                $moduleSize = $this->getDirectorySize($modulePath);
                                if ($moduleSize > 50 * 1024 * 1024) { // 50MB
                                    $largeModules[] = sprintf(
                                        '%s_%s (%s)',
                                        $vendor,
                                        $module,
                                        $this->byteConverter->convert($moduleSize)
                                    );
                                }
                            }
                        }
                    }
                }
                
                // Check for too many custom modules
                if ($customModuleCount > 20) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'Codebase',
                        'High number of custom modules in app/code',
                        'Many custom modules can increase complexity and maintenance overhead. Consider consolidating functionality.',
                        (string) $customModuleCount,
                        'Consolidated modules'
                    );
                }
                
                // Check for large modules
                if (!empty($largeModules)) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_LOW,
                        'Codebase',
                        'Large custom modules detected',
                        'Large modules may contain unnecessary files or assets. Review and optimize module contents.',
                        implode(', ', array_slice($largeModules, 0, 3)) . (count($largeModules) > 3 ? '...' : ''),
                        'Optimized module size'
                    );
                }
            }
        } catch (\Exception $e) {
            // Custom code check failed
        }
        
        return $issues;
    }
    
    /**
     * Check for core modifications
     *
     * @return IssueInterface[]
     */
    private function checkCoreModifications(): array
    {
        $issues = [];
        
        try {
            // Check for local code pool (Magento 1 style modifications)
            $appDir = $this->filesystem->getDirectoryRead(DirectoryList::APP);
            $localPath = $appDir->getAbsolutePath() . '/code/local';
            
            if (is_dir($localPath)) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_HIGH,
                    'Codebase',
                    'Local code pool detected',
                    'app/code/local indicates Magento 1 style core modifications. This is not supported in Magento 2.',
                    'app/code/local exists',
                    'Use plugins/preferences'
                );
            }
            
            // Check for modifications in vendor directory
            $vendorDir = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
            $vendorPath = $vendorDir->getAbsolutePath() . '/vendor';
            
            if (is_dir($vendorPath)) {
                // Check if vendor is under version control (indicates possible modifications)
                $gitPath = $vendorPath . '/.git';
                if (is_dir($gitPath)) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_HIGH,
                        'Codebase',
                        'Vendor directory under version control',
                        'Having vendor directory in git may indicate core modifications. Use composer patches instead.',
                        'vendor/.git exists',
                        'Composer patches'
                    );
                }
            }
        } catch (\Exception $e) {
            // Core modification check failed
        }
        
        return $issues;
    }
    
    /**
     * Check media files
     *
     * @return IssueInterface[]
     */
    private function checkMediaFiles(): array
    {
        $issues = [];
        
        try {
            $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            
            if ($mediaDir->isExist()) {
                $largeFIles = [];
                $totalLargeFileSize = 0;
                
                // Check for large files in media directory
                $this->findLargeFiles(
                    $mediaDir->getAbsolutePath(),
                    10 * 1024 * 1024, // 10MB threshold
                    $largeFIles,
                    $totalLargeFileSize
                );
                
                if (!empty($largeFIles)) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_LOW,
                        'Codebase',
                        'Large media files detected',
                        sprintf(
                            'Found %d files over 10MB (total: %s). Consider using CDN or image optimization.',
                            count($largeFIles),
                            $this->byteConverter->convert($totalLargeFileSize)
                        ),
                        sprintf('%d large files', count($largeFIles)),
                        'Optimized media'
                    );
                }
            }
        } catch (\Exception $e) {
            // Media files check failed
        }
        
        return $issues;
    }
    
    /**
     * Get directory size
     *
     * @param string $path
     * @return int
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        
        if (!is_dir($path)) {
            return $size;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Find large files in directory
     *
     * @param string $path
     * @param int $threshold
     * @param array $largeFiles
     * @param int $totalSize
     * @param int $maxFiles
     */
    private function findLargeFiles(
        string $path,
        int $threshold,
        array &$largeFiles,
        int &$totalSize,
        int $maxFiles = 100
    ): void {
        if (!is_dir($path) || count($largeFiles) >= $maxFiles) {
            return;
        }
        
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getSize() > $threshold) {
                    $largeFiles[] = $file->getPathname();
                    $totalSize += $file->getSize();
                    
                    if (count($largeFiles) >= $maxFiles) {
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            // Skip directories we can't read
        }
    }
}