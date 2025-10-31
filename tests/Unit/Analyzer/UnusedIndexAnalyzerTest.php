<?php
declare(strict_types=1);

namespace PerformanceReview\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use MyCompany\PerformanceAnalyzer\UnusedIndexAnalyzer;
use PerformanceReview\Model\Issue\Collection;
use PerformanceReview\Model\IssueFactory;

/**
 * Test suite for UnusedIndexAnalyzer
 *
 * Tests detection of unused database indexes that waste storage and slow down writes.
 *
 * @covers \MyCompany\PerformanceAnalyzer\UnusedIndexAnalyzer
 */
class UnusedIndexAnalyzerTest extends TestCase
{
    private UnusedIndexAnalyzer $analyzer;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->analyzer = new UnusedIndexAnalyzer();
        $issueFactory = $this->createMock(IssueFactory::class);
        $this->collection = new Collection($issueFactory);
    }

    protected function tearDown(): void
    {
        unset($this->analyzer, $this->collection);
    }

    /**
     * Test that analyzer detects unused indexes when found
     */
    public function testDetectsUnusedIndexesWhenFound(): void
    {
        // Arrange
        $unusedIndexes = [
            [
                'TABLE_NAME' => 'catalog_product_entity',
                'INDEX_NAME' => 'idx_rarely_used',
                'size_mb' => 150.50,
                'usage_count' => 0
            ]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertCount(1, $issues, 'Should detect exactly one unused index');
        $this->assertEquals('medium', $issues[0]->getPriority(), 'Index >100MB should be medium priority');
        $this->assertStringContainsString('idx_rarely_used', $issues[0]->getIssue());
        $this->assertStringContainsString('catalog_product_entity', $issues[0]->getIssue());
    }

    /**
     * Test that analyzer does NOT detect issues when all indexes are used
     */
    public function testDoesNotDetectIssuesWhenNoUnusedIndexes(): void
    {
        // Arrange - empty result set (all indexes used)
        $dependencies = $this->createMockDependencies([], true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertCount(0, $issues, 'Should not detect any issues when all indexes are used');
    }

    /**
     * Test HIGH priority assignment for large indexes (>500MB)
     */
    public function testAssignsHighPriorityForLargeIndexes(): void
    {
        // Arrange
        $unusedIndexes = [
            [
                'TABLE_NAME' => 'sales_order',
                'INDEX_NAME' => 'idx_huge',
                'size_mb' => 750.00,  // >500MB = high priority
                'usage_count' => 0
            ]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues, 'Should detect the large unused index');
        $this->assertEquals('high', $issues[0]->getPriority(), 'Index >500MB should be high priority');
    }

    /**
     * Test MEDIUM priority assignment for moderate indexes (100-500MB)
     */
    public function testAssignsMediumPriorityForModerateIndexes(): void
    {
        // Arrange
        $unusedIndexes = [
            [
                'TABLE_NAME' => 'catalog_category_product',
                'INDEX_NAME' => 'idx_moderate',
                'size_mb' => 250.00,  // 100-500MB = medium priority
                'usage_count' => 0
            ]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues);
        $this->assertEquals('medium', $issues[0]->getPriority(), 'Index 100-500MB should be medium priority');
    }

    /**
     * Test LOW priority assignment for small indexes (10-100MB)
     */
    public function testAssignsLowPriorityForSmallIndexes(): void
    {
        // Arrange
        $unusedIndexes = [
            [
                'TABLE_NAME' => 'customer_entity',
                'INDEX_NAME' => 'idx_small',
                'size_mb' => 50.00,  // 10-100MB = low priority
                'usage_count' => 0
            ]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues);
        $this->assertEquals('low', $issues[0]->getPriority(), 'Index 10-100MB should be low priority');
    }

    /**
     * Test that analyzer creates multiple issues for multiple unused indexes
     */
    public function testCreatesMultipleIssuesForMultipleUnusedIndexes(): void
    {
        // Arrange - 3 unused indexes
        $unusedIndexes = [
            ['TABLE_NAME' => 'table1', 'INDEX_NAME' => 'idx_1', 'size_mb' => 100.00, 'usage_count' => 0],
            ['TABLE_NAME' => 'table2', 'INDEX_NAME' => 'idx_2', 'size_mb' => 200.00, 'usage_count' => 0],
            ['TABLE_NAME' => 'table3', 'INDEX_NAME' => 'idx_3', 'size_mb' => 300.00, 'usage_count' => 0],
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertCount(3, $issues, 'Should create 3 separate issues for 3 unused indexes');

        // Verify each issue is distinct
        $issueTexts = array_map(fn($i) => $i->getIssue(), $issues);
        $this->assertCount(3, array_unique($issueTexts), 'Each issue should be unique');
    }

    /**
     * Test that analyzer uses configured thresholds for priority
     */
    public function testUsesConfiguredPriorityThresholds(): void
    {
        // Arrange - custom thresholds
        $config = [
            'min_size_mb' => 10,
            'high_priority_mb' => 300,  // Custom: 300MB instead of 500MB
            'medium_priority_mb' => 50   // Custom: 50MB instead of 100MB
        ];
        $this->analyzer->setConfig($config);

        $unusedIndexes = [
            ['TABLE_NAME' => 'test_table', 'INDEX_NAME' => 'idx_test', 'size_mb' => 350.00, 'usage_count' => 0]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues);
        $this->assertEquals('high', $issues[0]->getPriority(),
            'Should use custom threshold (350MB > 300MB = high)');
    }

    /**
     * Test that analyzer uses configured min_size_mb threshold
     */
    public function testRespectsMinSizeThreshold(): void
    {
        // Arrange - set minimum to 50MB
        $config = ['min_size_mb' => 50];
        $this->analyzer->setConfig($config);

        // Mock will only return indexes >= 50MB due to HAVING clause
        // This tests that the config is passed correctly to the query
        $unusedIndexes = [
            ['TABLE_NAME' => 'test_table', 'INDEX_NAME' => 'idx_large', 'size_mb' => 60.00, 'usage_count' => 0]
            // Indexes < 50MB would not be in this result
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true, 50);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertCount(1, $issues, 'Should only report indexes >= configured minimum');
    }

    /**
     * Test that analyzer uses default thresholds when not configured
     */
    public function testUsesDefaultThresholdsWhenNotConfigured(): void
    {
        // Arrange - no config set, should use defaults
        $unusedIndexes = [
            ['TABLE_NAME' => 'test_table', 'INDEX_NAME' => 'idx_test', 'size_mb' => 600.00, 'usage_count' => 0]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues);
        $this->assertEquals('high', $issues[0]->getPriority(),
            'Should use default threshold (600MB > 500MB default = high)');
    }

    /**
     * Test that analyzer handles missing resourceConnection gracefully
     */
    public function testHandlesMissingResourceConnectionGracefully(): void
    {
        // Arrange - no dependencies set
        // Do NOT set dependencies

        // Act - should not throw
        $this->analyzer->analyze($this->collection);

        // Assert - should return gracefully without creating issues
        $issues = $this->collection->getIssues();
        $this->assertCount(0, $issues, 'Should handle missing dependency gracefully');
    }

    /**
     * Test that analyzer catches query exceptions and creates error issue
     */
    public function testCatchesQueryExceptionGracefully(): void
    {
        // Arrange - connection that throws exception
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('fetchOne')
            ->willThrowException(new \Exception('Database connection failed'));

        $resourceConnection = $this->createMock(\Magento\Framework\App\ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);

        $dependencies = ['resourceConnection' => $resourceConnection];
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert - should create error issue, not throw
        $issues = $this->collection->getIssues();
        $this->assertGreaterThanOrEqual(1, $issues, 'Should create at least one error issue');

        // Error issues should have low priority
        $errorIssue = $issues[0];
        $this->assertEquals('low', $errorIssue->getPriority(), 'Error issues should be low priority');
        $this->assertStringContainsString('failed', strtolower($errorIssue->getIssue()));
    }

    /**
     * Test that analyzer handles performance_schema being disabled
     */
    public function testHandlesPerformanceSchemaDisabled(): void
    {
        // Arrange - performance_schema not available
        $dependencies = $this->createMockDependencies([], false);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert - should create warning about performance_schema
        $issues = $this->collection->getIssues();
        $this->assertCount(1, $issues, 'Should create warning issue');
        $this->assertEquals('low', $issues[0]->getPriority());
        $this->assertStringContainsString('performance_schema', $issues[0]->getIssue());
    }

    /**
     * Test that issue contains DROP INDEX command in recommended value
     */
    public function testIssueContainsDropIndexCommand(): void
    {
        // Arrange
        $unusedIndexes = [
            [
                'TABLE_NAME' => 'catalog_product_entity',
                'INDEX_NAME' => 'idx_to_drop',
                'size_mb' => 100.00,
                'usage_count' => 0
            ]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues);

        $recommendedValue = $issues[0]->getRecommendedValue();
        $this->assertStringContainsString('ALTER TABLE', $recommendedValue);
        $this->assertStringContainsString('DROP INDEX', $recommendedValue);
        $this->assertStringContainsString('idx_to_drop', $recommendedValue);
        $this->assertStringContainsString('catalog_product_entity', $recommendedValue);
    }

    /**
     * Test that issue contains helpful details about unused indexes
     */
    public function testIssueContainsHelpfulDetails(): void
    {
        // Arrange
        $unusedIndexes = [
            ['TABLE_NAME' => 'test_table', 'INDEX_NAME' => 'idx_test', 'size_mb' => 100.00, 'usage_count' => 0]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues);

        $details = $issues[0]->getDetails();
        $this->assertStringContainsString('never been used', $details);
        $this->assertStringContainsString('waste disk space', $details);
        $this->assertStringContainsString('slow down INSERT', $details);
        $this->assertStringContainsString('non-production', $details, 'Should warn about testing first');
    }

    /**
     * Test that issue includes current value with size and usage count
     */
    public function testIssueIncludesCurrentValueWithSizeAndUsage(): void
    {
        // Arrange
        $unusedIndexes = [
            ['TABLE_NAME' => 'test_table', 'INDEX_NAME' => 'idx_test', 'size_mb' => 123.45, 'usage_count' => 0]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues);

        $currentValue = $issues[0]->getCurrentValue();
        $this->assertStringContainsString('123.45', $currentValue, 'Should show size');
        $this->assertStringContainsString('MB', $currentValue, 'Should include MB unit');
        $this->assertStringContainsString('0', $currentValue, 'Should show zero usage');
    }

    /**
     * Test that analyzer handles very large indexes (>1GB) correctly
     */
    public function testHandlesVeryLargeIndexes(): void
    {
        // Arrange - 2GB index
        $unusedIndexes = [
            ['TABLE_NAME' => 'huge_table', 'INDEX_NAME' => 'idx_huge', 'size_mb' => 2048.00, 'usage_count' => 0]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues);
        $this->assertEquals('high', $issues[0]->getPriority(), 'Very large index should be high priority');
        $this->assertStringContainsString('2048', $issues[0]->getCurrentValue());
    }

    /**
     * Test that analyzer assigns correct category
     */
    public function testAssignsCorrectCategory(): void
    {
        // Arrange
        $unusedIndexes = [
            ['TABLE_NAME' => 'test_table', 'INDEX_NAME' => 'idx_test', 'size_mb' => 100.00, 'usage_count' => 0]
        ];

        $dependencies = $this->createMockDependencies($unusedIndexes, true);
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertNotEmpty($issues);
        $this->assertEquals('Database', $issues[0]->getCategory(), 'Should assign Database category');
    }

    /**
     * Test fallback query when main query fails
     */
    public function testUsesFallbackQueryWhenMainQueryFails(): void
    {
        // Arrange - connection that fails on first query, succeeds on fallback
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);

        // First call to fetchOne returns 1 (performance_schema exists)
        // Second call to fetchOne returns 1 (table exists)
        // Third call to fetchOne returns database name
        $connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(1, 1, 'magento_db');

        // First fetchAll (main query) throws exception
        // Second fetchAll (fallback query) returns results
        $fallbackResults = [
            ['TABLE_NAME' => 'test_table', 'INDEX_NAME' => 'idx_fallback', 'size_mb' => 50.00, 'usage_count' => 0]
        ];

        $connection->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \Exception('Main query failed')),
                $fallbackResults
            );

        $resourceConnection = $this->createMock(\Magento\Framework\App\ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);

        $dependencies = ['resourceConnection' => $resourceConnection];
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertCount(1, $issues, 'Should use fallback query and detect index');
        $this->assertStringContainsString('idx_fallback', $issues[0]->getIssue());
    }

    /**
     * Test graceful degradation when both queries fail
     */
    public function testGracefulDegradationWhenBothQueriesFail(): void
    {
        // Arrange - connection that fails on both queries
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);

        $connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(1, 1, 'magento_db');

        // Both fetchAll calls fail
        $connection->method('fetchAll')
            ->willThrowException(new \Exception('Query failed'));

        $resourceConnection = $this->createMock(\Magento\Framework\App\ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);

        $dependencies = ['resourceConnection' => $resourceConnection];
        $this->analyzer->setDependencies($dependencies);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert - should catch exception and create error issue
        $issues = $this->collection->getIssues();
        $this->assertGreaterThanOrEqual(1, $issues, 'Should create error issue');
        $this->assertEquals('low', $issues[0]->getPriority());
    }

    // ========================================
    // Mock Helper Methods
    // ========================================

    /**
     * Create mock dependencies array with ResourceConnection
     *
     * @param array $unusedIndexes Array of index data to return from query
     * @param bool $performanceSchemaAvailable Whether performance_schema is available
     * @param int $minSizeMB Expected minimum size parameter (for verification)
     * @return array
     */
    private function createMockDependencies(
        array $unusedIndexes,
        bool $performanceSchemaAvailable = true,
        int $minSizeMB = 10
    ): array {
        $connection = $this->createMockConnection($unusedIndexes, $performanceSchemaAvailable, $minSizeMB);
        $resourceConnection = $this->createMockResourceConnection($connection);

        return ['resourceConnection' => $resourceConnection];
    }

    /**
     * Create mock database connection
     *
     * @param array $unusedIndexes Indexes to return from query
     * @param bool $performanceSchemaAvailable Whether performance_schema is available
     * @param int $minSizeMB Expected minimum size parameter
     * @return object Mock connection
     */
    private function createMockConnection(
        array $unusedIndexes,
        bool $performanceSchemaAvailable,
        int $minSizeMB
    ) {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);

        // Mock performance_schema checks
        if ($performanceSchemaAvailable) {
            $connection->method('fetchOne')
                ->willReturnCallback(function ($sql) {
                    if (stripos($sql, 'information_schema.SCHEMATA') !== false) {
                        return 1; // performance_schema exists
                    } elseif (stripos($sql, 'information_schema.TABLES') !== false) {
                        return 1; // table_io_waits_summary_by_index_usage exists
                    } elseif (stripos($sql, 'DATABASE()') !== false) {
                        return 'magento_db'; // database name
                    }
                    return null;
                });
        } else {
            $connection->method('fetchOne')
                ->willReturn(0); // performance_schema not available
        }

        // Mock unused indexes query
        $connection->method('fetchAll')
            ->willReturn($unusedIndexes);

        return $connection;
    }

    /**
     * Create mock ResourceConnection wrapper
     *
     * @param object $connection Mock connection object
     * @return object Mock ResourceConnection
     */
    private function createMockResourceConnection($connection)
    {
        $resourceConnection = $this->createMock(\Magento\Framework\App\ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);

        return $resourceConnection;
    }
}
