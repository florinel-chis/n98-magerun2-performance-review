<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;
use PerformanceReview\Util\ByteConverter;

/**
 * PHP configuration analyzer
 */
class PhpConfigurationAnalyzer
{
    /**
     * Required PHP version
     */
    private const MINIMUM_PHP_VERSION = '7.4.0';
    private const RECOMMENDED_PHP_VERSION = '8.1.0';
    
    /**
     * Memory limits
     */
    private const MINIMUM_MEMORY_LIMIT = '2G';
    private const RECOMMENDED_MEMORY_LIMIT = '4G';
    
    /**
     * Required extensions
     */
    private const REQUIRED_EXTENSIONS = [
        'bcmath',
        'ctype',
        'curl',
        'dom',
        'gd',
        'hash',
        'iconv',
        'intl',
        'json',
        'libxml',
        'mbstring',
        'openssl',
        'pcre',
        'pdo',
        'pdo_mysql',
        'simplexml',
        'soap',
        'sockets',
        'sodium',
        'spl',
        'tokenizer',
        'xmlwriter',
        'xsl',
        'zip'
    ];
    
    /**
     * Performance extensions
     */
    private const PERFORMANCE_EXTENSIONS = [
        'opcache' => 'OPcache significantly improves PHP performance',
        'apcu' => 'APCu provides user cache for improved performance',
        'redis' => 'Redis extension for better session/cache performance',
        'imagick' => 'ImageMagick provides better image processing than GD'
    ];
    
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
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        IssueFactory $issueFactory
    ) {
        $this->issueFactory = $issueFactory;
        $this->byteConverter = new ByteConverter();
    }
    
    /**
     * Analyze PHP configuration
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check PHP version
        $issues = array_merge($issues, $this->checkPhpVersion());
        
        // Check memory limit
        $issues = array_merge($issues, $this->checkMemoryLimit());
        
        // Check required extensions
        $issues = array_merge($issues, $this->checkRequiredExtensions());
        
        // Check performance extensions
        $issues = array_merge($issues, $this->checkPerformanceExtensions());
        
        // Check OPcache configuration
        $issues = array_merge($issues, $this->checkOpcacheConfiguration());
        
        // Check execution limits
        $issues = array_merge($issues, $this->checkExecutionLimits());
        
        // Check file upload limits
        $issues = array_merge($issues, $this->checkFileUploadLimits());
        
        return $issues;
    }
    
    /**
     * Check PHP version
     *
     * @return IssueInterface[]
     */
    private function checkPhpVersion(): array
    {
        $issues = [];
        
        $currentVersion = PHP_VERSION;
        
        if (version_compare($currentVersion, self::MINIMUM_PHP_VERSION, '<')) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'PHP',
                'PHP version too old',
                'Your PHP version is below the minimum required version for Magento 2.',
                $currentVersion,
                self::MINIMUM_PHP_VERSION . ' or higher'
            );
        } elseif (version_compare($currentVersion, self::RECOMMENDED_PHP_VERSION, '<')) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'PHP',
                'PHP version not optimal',
                'Newer PHP versions provide better performance and security.',
                $currentVersion,
                self::RECOMMENDED_PHP_VERSION . ' or higher'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check memory limit
     *
     * @return IssueInterface[]
     */
    private function checkMemoryLimit(): array
    {
        $issues = [];
        
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->byteConverter->toBytes($memoryLimit);
        $minimumBytes = $this->byteConverter->toBytes(self::MINIMUM_MEMORY_LIMIT);
        $recommendedBytes = $this->byteConverter->toBytes(self::RECOMMENDED_MEMORY_LIMIT);
        
        if ($memoryLimit === '-1') {
            // Unlimited memory
            return $issues;
        }
        
        if ($memoryBytes < $minimumBytes) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'PHP',
                'Memory limit too low',
                'Low memory limit can cause out of memory errors during catalog operations.',
                $memoryLimit,
                self::MINIMUM_MEMORY_LIMIT . ' or higher'
            );
        } elseif ($memoryBytes < $recommendedBytes) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'PHP',
                'Memory limit below recommended',
                'Higher memory limit improves performance for large catalogs and import/export operations.',
                $memoryLimit,
                self::RECOMMENDED_MEMORY_LIMIT
            );
        }
        
        return $issues;
    }
    
    /**
     * Check required extensions
     *
     * @return IssueInterface[]
     */
    private function checkRequiredExtensions(): array
    {
        $issues = [];
        $missingExtensions = [];
        
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }
        
        if (!empty($missingExtensions)) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'PHP',
                'Missing required PHP extensions',
                'These extensions are required for Magento 2 to function properly.',
                implode(', ', $missingExtensions),
                'All required extensions',
                ['missing_extensions' => $missingExtensions]
            );
        }
        
        return $issues;
    }
    
    /**
     * Check performance extensions
     *
     * @return IssueInterface[]
     */
    private function checkPerformanceExtensions(): array
    {
        $issues = [];
        $missingExtensions = [];
        
        foreach (self::PERFORMANCE_EXTENSIONS as $extension => $description) {
            if (!extension_loaded($extension)) {
                $missingExtensions[$extension] = $description;
            }
        }
        
        if (!empty($missingExtensions)) {
            $extensionList = array_keys($missingExtensions);
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'PHP',
                'Missing performance extensions',
                'Installing these extensions can significantly improve performance.',
                implode(', ', $extensionList),
                'Performance extensions installed',
                ['missing_performance_extensions' => $missingExtensions]
            );
        }
        
        return $issues;
    }
    
    /**
     * Check OPcache configuration
     *
     * @return IssueInterface[]
     */
    private function checkOpcacheConfiguration(): array
    {
        $issues = [];
        
        if (!extension_loaded('Zend OPcache')) {
            return $issues; // Already reported in performance extensions
        }
        
        // Check if OPcache is enabled
        if (!ini_get('opcache.enable')) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_HIGH,
                'PHP',
                'OPcache disabled',
                'OPcache is installed but not enabled. Enable it for significant performance improvement.',
                'Disabled',
                'Enabled'
            );
            return $issues;
        }
        
        // Check OPcache memory
        $opcacheMemory = ini_get('opcache.memory_consumption');
        if ($opcacheMemory < 256) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'PHP',
                'OPcache memory too low',
                'Increase OPcache memory for better performance with large codebases.',
                $opcacheMemory . 'MB',
                '512MB or higher'
            );
        }
        
        // Check interned strings buffer
        $internedStrings = ini_get('opcache.interned_strings_buffer');
        if ($internedStrings < 16) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'PHP',
                'OPcache interned strings buffer low',
                'Increase buffer size for better string caching.',
                $internedStrings . 'MB',
                '16MB or higher'
            );
        }
        
        // Check max accelerated files
        $maxFiles = ini_get('opcache.max_accelerated_files');
        if ($maxFiles < 20000) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'PHP',
                'OPcache max files too low',
                'Magento 2 has many files. Increase the limit for complete caching.',
                (string) $maxFiles,
                '20000 or higher'
            );
        }
        
        // Check validate timestamps
        $validateTimestamps = ini_get('opcache.validate_timestamps');
        $revalidateFreq = ini_get('opcache.revalidate_freq');
        
        if ($validateTimestamps && $revalidateFreq < 60) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'PHP',
                'OPcache revalidation too frequent',
                'Frequent revalidation impacts performance. Increase interval or disable in production.',
                $revalidateFreq . ' seconds',
                '60 seconds or disabled'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check execution limits
     *
     * @return IssueInterface[]
     */
    private function checkExecutionLimits(): array
    {
        $issues = [];
        
        // Check max execution time
        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime > 0 && $maxExecutionTime < 18000) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'PHP',
                'Max execution time too low',
                'Low execution time can cause timeouts during reindexing or import/export operations.',
                $maxExecutionTime . ' seconds',
                '18000 seconds'
            );
        }
        
        // Check max input time
        $maxInputTime = ini_get('max_input_time');
        if ($maxInputTime > 0 && $maxInputTime < 900) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'PHP',
                'Max input time too low',
                'Low input time can cause issues with large file uploads.',
                $maxInputTime . ' seconds',
                '900 seconds'
            );
        }
        
        // Check max input vars
        $maxInputVars = ini_get('max_input_vars');
        if ($maxInputVars < 10000) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'PHP',
                'Max input vars too low',
                'Low input vars limit can cause issues with large forms in admin.',
                (string) $maxInputVars,
                '10000 or higher'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check file upload limits
     *
     * @return IssueInterface[]
     */
    private function checkFileUploadLimits(): array
    {
        $issues = [];
        
        // Check upload max filesize
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $uploadBytes = $this->byteConverter->toBytes($uploadMaxFilesize);
        
        if ($uploadBytes < 64 * 1024 * 1024) { // 64MB
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'PHP',
                'Upload max filesize too low',
                'Low upload size limit can prevent uploading large product images or import files.',
                $uploadMaxFilesize,
                '64M or higher'
            );
        }
        
        // Check post max size
        $postMaxSize = ini_get('post_max_size');
        $postBytes = $this->byteConverter->toBytes($postMaxSize);
        
        if ($postBytes < 64 * 1024 * 1024) { // 64MB
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'PHP',
                'Post max size too low',
                'Post size should be equal or larger than upload_max_filesize.',
                $postMaxSize,
                '64M or higher'
            );
        }
        
        // Check if post_max_size is less than upload_max_filesize
        if ($postBytes < $uploadBytes) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'PHP',
                'Post max size less than upload max filesize',
                'post_max_size must be larger than upload_max_filesize for uploads to work properly.',
                sprintf('post: %s, upload: %s', $postMaxSize, $uploadMaxFilesize),
                'post_max_size >= upload_max_filesize'
            );
        }
        
        return $issues;
    }
}