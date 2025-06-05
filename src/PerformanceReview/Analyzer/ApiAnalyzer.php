<?php
declare(strict_types=1);

namespace PerformanceReview\Analyzer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\ResourceConnection;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;

/**
 * API configuration analyzer
 */
class ApiAnalyzer
{
    /**
     * Configuration paths
     */
    private const XML_PATH_API_CONSUMER_ENABLE = 'webapi/async/consumer/enabled';
    private const XML_PATH_API_DEFAULT_RESPONSE_CHARSET = 'webapi/soap/charset';
    private const XML_PATH_API_RATE_LIMIT = 'webapi/rate_limiting/enabled';
    private const XML_PATH_OAUTH_CLEANUP_PROBABILITY = 'oauth/cleanup/cleanup_probability';
    private const XML_PATH_OAUTH_EXPIRATION_PERIOD = 'oauth/consumer/expiration_period';
    
    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;
    
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;
    
    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;
    
    /**
     * Constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resourceConnection
     * @param IssueFactory $issueFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection,
        IssueFactory $issueFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
        $this->issueFactory = $issueFactory;
    }
    
    /**
     * Analyze API configuration
     *
     * @return IssueInterface[]
     */
    public function analyze(): array
    {
        $issues = [];
        
        // Check API usage
        $issues = array_merge($issues, $this->checkApiUsage());
        
        // Check async API configuration
        $issues = array_merge($issues, $this->checkAsyncApiConfiguration());
        
        // Check OAuth configuration
        $issues = array_merge($issues, $this->checkOAuthConfiguration());
        
        // Check integration tokens
        $issues = array_merge($issues, $this->checkIntegrationTokens());
        
        // Check rate limiting
        $issues = array_merge($issues, $this->checkRateLimiting());
        
        return $issues;
    }
    
    /**
     * Check API usage
     *
     * @return IssueInterface[]
     */
    private function checkApiUsage(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // Check for active integrations
            $integrationTable = $this->resourceConnection->getTableName('integration');
            if ($connection->isTableExists($integrationTable)) {
                $activeIntegrations = (int) $connection->fetchOne(
                    "SELECT COUNT(*) FROM {$integrationTable} WHERE status = 1"
                );
                
                if ($activeIntegrations > 10) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'API',
                        'High number of active integrations',
                        'Many active integrations can impact performance. Review and disable unused integrations.',
                        $activeIntegrations . ' integrations',
                        'Only necessary integrations'
                    );
                }
            }
            
            // Check OAuth token count
            $oauthTokenTable = $this->resourceConnection->getTableName('oauth_token');
            if ($connection->isTableExists($oauthTokenTable)) {
                $tokenCount = (int) $connection->fetchOne(
                    "SELECT COUNT(*) FROM {$oauthTokenTable}"
                );
                
                if ($tokenCount > 10000) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'API',
                        'Large OAuth token table',
                        'Too many OAuth tokens can slow down API authentication. Enable regular cleanup.',
                        $tokenCount . ' tokens',
                        'Regular cleanup'
                    );
                }
                
                // Check for expired tokens
                $expiredTokens = (int) $connection->fetchOne(
                    "SELECT COUNT(*) FROM {$oauthTokenTable} 
                     WHERE expires_at < NOW() AND expires_at IS NOT NULL"
                );
                
                if ($expiredTokens > 1000) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_LOW,
                        'API',
                        'Many expired OAuth tokens',
                        'Expired tokens should be cleaned up regularly.',
                        $expiredTokens . ' expired tokens',
                        'Automated cleanup'
                    );
                }
            }
        } catch (\Exception $e) {
            // API usage check failed
        }
        
        return $issues;
    }
    
    /**
     * Check async API configuration
     *
     * @return IssueInterface[]
     */
    private function checkAsyncApiConfiguration(): array
    {
        $issues = [];
        
        // Check if async API is enabled
        $asyncEnabled = $this->scopeConfig->isSetFlag(
            self::XML_PATH_API_CONSUMER_ENABLE,
            ScopeInterface::SCOPE_STORE
        );
        
        if ($asyncEnabled) {
            // Check message queue configuration
            try {
                $connection = $this->resourceConnection->getConnection();
                $queueTable = $this->resourceConnection->getTableName('queue_message_status');
                
                if ($connection->isTableExists($queueTable)) {
                    // Check for stuck messages
                    $stuckMessages = (int) $connection->fetchOne(
                        "SELECT COUNT(*) FROM {$queueTable} 
                         WHERE status IN (2, 3) 
                         AND updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                    );
                    
                    if ($stuckMessages > 100) {
                        $issues[] = $this->issueFactory->createIssue(
                            IssueInterface::PRIORITY_HIGH,
                            'API',
                            'Stuck async API messages',
                            'Many messages stuck in processing state indicates consumer issues.',
                            $stuckMessages . ' stuck messages',
                            'Working consumers'
                        );
                    }
                }
            } catch (\Exception $e) {
                // Queue check failed
            }
        }
        
        return $issues;
    }
    
    /**
     * Check OAuth configuration
     *
     * @return IssueInterface[]
     */
    private function checkOAuthConfiguration(): array
    {
        $issues = [];
        
        // Check cleanup probability
        $cleanupProbability = (int) $this->scopeConfig->getValue(
            self::XML_PATH_OAUTH_CLEANUP_PROBABILITY,
            ScopeInterface::SCOPE_STORE
        );
        
        if ($cleanupProbability === 0) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_MEDIUM,
                'API',
                'OAuth cleanup disabled',
                'OAuth tokens should be cleaned up periodically to prevent table growth.',
                'Disabled',
                'Enable cleanup'
            );
        } elseif ($cleanupProbability < 10) {
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'API',
                'Low OAuth cleanup probability',
                'Increase cleanup probability for more frequent token cleanup.',
                $cleanupProbability . '%',
                '10% or higher'
            );
        }
        
        // Check expiration period
        $expirationPeriod = (int) $this->scopeConfig->getValue(
            self::XML_PATH_OAUTH_EXPIRATION_PERIOD,
            ScopeInterface::SCOPE_STORE
        );
        
        if ($expirationPeriod > 3600) { // 1 hour
            $issues[] = $this->issueFactory->createIssue(
                IssueInterface::PRIORITY_LOW,
                'API',
                'Long OAuth token expiration',
                'Shorter token expiration improves security and reduces token table size.',
                $expirationPeriod . ' seconds',
                '3600 seconds or less'
            );
        }
        
        return $issues;
    }
    
    /**
     * Check integration tokens
     *
     * @return IssueInterface[]
     */
    private function checkIntegrationTokens(): array
    {
        $issues = [];
        
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // Check for integrations with permanent tokens
            $integrationTable = $this->resourceConnection->getTableName('integration');
            $oauthTokenTable = $this->resourceConnection->getTableName('oauth_token');
            
            if ($connection->isTableExists($integrationTable) && $connection->isTableExists($oauthTokenTable)) {
                $permanentTokens = $connection->fetchOne(
                    "SELECT COUNT(DISTINCT i.integration_id) 
                     FROM {$integrationTable} i
                     JOIN {$oauthTokenTable} t ON i.consumer_id = t.consumer_id
                     WHERE i.status = 1 
                     AND (t.expires_at IS NULL OR t.expires_at > DATE_ADD(NOW(), INTERVAL 1 YEAR))"
                );
                
                if ($permanentTokens > 0) {
                    $issues[] = $this->issueFactory->createIssue(
                        IssueInterface::PRIORITY_MEDIUM,
                        'API',
                        'Integrations with permanent tokens',
                        'Permanent tokens pose security risks. Use expiring tokens when possible.',
                        $permanentTokens . ' integrations',
                        'Expiring tokens'
                    );
                }
            }
        } catch (\Exception $e) {
            // Token check failed
        }
        
        return $issues;
    }
    
    /**
     * Check rate limiting
     *
     * @return IssueInterface[]
     */
    private function checkRateLimiting(): array
    {
        $issues = [];
        
        // Check if rate limiting is enabled (if available)
        try {
            $rateLimitEnabled = $this->scopeConfig->isSetFlag(
                self::XML_PATH_API_RATE_LIMIT,
                ScopeInterface::SCOPE_STORE
            );
            
            if (!$rateLimitEnabled) {
                $issues[] = $this->issueFactory->createIssue(
                    IssueInterface::PRIORITY_MEDIUM,
                    'API',
                    'API rate limiting disabled',
                    'Rate limiting protects against API abuse and DoS attacks.',
                    'Disabled',
                    'Enabled'
                );
            }
        } catch (\Exception $e) {
            // Rate limiting config may not exist in older versions
        }
        
        return $issues;
    }
}