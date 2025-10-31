<?php
declare(strict_types=1);

namespace MyCompany\PerformanceAnalyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Api\ConfigAwareInterface;
use PerformanceReview\Api\DependencyAwareInterface;
use PerformanceReview\Model\Issue\Collection;

/**
 * Analyzes MySQL database for unused indexes that waste storage and slow down writes
 *
 * This analyzer queries performance_schema to detect indexes that are never used in queries.
 * Unused indexes consume disk space and degrade INSERT/UPDATE performance.
 */
class UnusedIndexAnalyzer implements AnalyzerCheckInterface, ConfigAwareInterface, DependencyAwareInterface
{
    /**
     * @var array
     */
    private array $config = [];

    /**
     * @var array
     */
    private array $dependencies = [];

    /**
     * Set configuration for the analyzer
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

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
     * Run the unused index analysis
     *
     * @param Collection $results
     * @return void
     */
    public function analyze(Collection $results): void
    {
        try {
            // Validate dependencies first
            if (!$this->validateDependencies()) {
                return;
            }

            // Check if performance_schema is available
            if (!$this->isPerformanceSchemaAvailable()) {
                $this->createPerformanceSchemaWarning($results);
                return;
            }

            // Get configuration thresholds
            $minSizeMB = $this->config['min_size_mb'] ?? 10;
            $highPriorityMB = $this->config['high_priority_mb'] ?? 500;
            $mediumPriorityMB = $this->config['medium_priority_mb'] ?? 100;

            // Find unused indexes
            $unusedIndexes = $this->getUnusedIndexes($minSizeMB);

            // Create issues for each unused index
            foreach ($unusedIndexes as $index) {
                $this->createIndexIssue($results, $index, $highPriorityMB, $mediumPriorityMB);
            }

        } catch (\Exception $e) {
            // NEVER let exceptions break the report
            $results->createIssue()
                ->setPriority('low')
                ->setCategory('Database')
                ->setIssue('Unused index analysis failed')
                ->setDetails('Could not analyze unused indexes: ' . $e->getMessage())
                ->add();
        }
    }

    /**
     * Validate that required dependencies are available
     *
     * @return bool
     */
    private function validateDependencies(): bool
    {
        return isset($this->dependencies['resourceConnection']);
    }

    /**
     * Check if MySQL performance_schema is enabled and accessible
     *
     * @return bool
     */
    private function isPerformanceSchemaAvailable(): bool
    {
        $connection = $this->dependencies['resourceConnection']->getConnection();

        try {
            // Check if performance_schema is enabled
            $result = $connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'performance_schema'"
            );

            if ($result == 0) {
                return false;
            }

            // Check if the specific table we need exists
            $result = $connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = 'performance_schema'
                 AND TABLE_NAME = 'table_io_waits_summary_by_index_usage'"
            );

            return $result > 0;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create warning issue when performance_schema is not available
     *
     * @param Collection $results
     * @return void
     */
    private function createPerformanceSchemaWarning(Collection $results): void
    {
        $results->createIssue()
            ->setPriority('low')
            ->setCategory('Database')
            ->setIssue('MySQL performance_schema not available for index analysis')
            ->setDetails(
                'The performance_schema is required to detect unused indexes. ' .
                'Enable it in MySQL configuration (performance_schema = ON) and restart MySQL. ' .
                'Note: This has minimal performance impact in production.'
            )
            ->setRecommendedValue('Enable performance_schema in my.cnf')
            ->add();
    }

    /**
     * Get list of unused indexes above the minimum size threshold
     *
     * @param int $minSizeMB Minimum index size in MB to report
     * @return array Array of unused index information
     */
    private function getUnusedIndexes(int $minSizeMB): array
    {
        $connection = $this->dependencies['resourceConnection']->getConnection();
        $databaseName = $connection->fetchOne('SELECT DATABASE()');

        // Query to find unused indexes with size calculation
        $sql = "
            SELECT
                t.TABLE_NAME,
                t.INDEX_NAME,
                ROUND(SUM(s.stat_value * @@innodb_page_size) / 1024 / 1024, 2) as size_mb,
                ps.COUNT_STAR as usage_count
            FROM information_schema.INNODB_SYS_TABLESTATS s
            INNER JOIN information_schema.INNODB_SYS_INDEXES i
                ON s.TABLE_ID = i.TABLE_ID
            INNER JOIN information_schema.TABLES t
                ON t.TABLE_NAME = SUBSTRING_INDEX(s.NAME, '/', -1)
                AND t.TABLE_SCHEMA = ?
            LEFT JOIN performance_schema.table_io_waits_summary_by_index_usage ps
                ON ps.OBJECT_SCHEMA = t.TABLE_SCHEMA
                AND ps.OBJECT_NAME = t.TABLE_NAME
                AND ps.INDEX_NAME = i.NAME
            WHERE t.TABLE_SCHEMA = ?
                AND i.NAME != 'PRIMARY'
                AND t.ENGINE = 'InnoDB'
                AND (ps.COUNT_STAR = 0 OR ps.COUNT_STAR IS NULL)
            GROUP BY t.TABLE_NAME, t.INDEX_NAME, ps.COUNT_STAR
            HAVING size_mb >= ?
            ORDER BY size_mb DESC
        ";

        try {
            return $connection->fetchAll($sql, [$databaseName, $databaseName, $minSizeMB]);
        } catch (\Exception $e) {
            // Fallback to simpler query if the above doesn't work
            return $this->getUnusedIndexesFallback($databaseName, $minSizeMB);
        }
    }

    /**
     * Fallback method using simpler query for index detection
     *
     * @param string $databaseName
     * @param int $minSizeMB
     * @return array
     */
    private function getUnusedIndexesFallback(string $databaseName, int $minSizeMB): array
    {
        $connection = $this->dependencies['resourceConnection']->getConnection();

        // Simpler query that should work on more MySQL versions
        $sql = "
            SELECT
                t.TABLE_NAME,
                s.INDEX_NAME,
                ROUND(
                    (s.STAT_VALUE *
                     (SELECT @@innodb_page_size)) / 1024 / 1024, 2
                ) as size_mb,
                ps.COUNT_STAR as usage_count
            FROM information_schema.INNODB_TABLESTATS_INDEXES s
            INNER JOIN information_schema.TABLES t
                ON t.TABLE_SCHEMA = ?
                AND t.TABLE_NAME = s.TABLE_NAME
            LEFT JOIN performance_schema.table_io_waits_summary_by_index_usage ps
                ON ps.OBJECT_SCHEMA = ?
                AND ps.OBJECT_NAME = s.TABLE_NAME
                AND ps.INDEX_NAME = s.INDEX_NAME
            WHERE s.DATABASE_NAME = ?
                AND s.INDEX_NAME != 'PRIMARY'
                AND (ps.COUNT_STAR = 0 OR ps.COUNT_STAR IS NULL)
            HAVING size_mb >= ?
            ORDER BY size_mb DESC
        ";

        try {
            return $connection->fetchAll(
                $sql,
                [$databaseName, $databaseName, $databaseName, $minSizeMB]
            );
        } catch (\Exception $e) {
            // Return empty array if query fails - graceful degradation
            return [];
        }
    }

    /**
     * Create issue for an unused index
     *
     * @param Collection $results
     * @param array $index Index information
     * @param int $highPriorityMB
     * @param int $mediumPriorityMB
     * @return void
     */
    private function createIndexIssue(
        Collection $results,
        array $index,
        int $highPriorityMB,
        int $mediumPriorityMB
    ): void {
        $tableName = $index['TABLE_NAME'];
        $indexName = $index['INDEX_NAME'];
        $sizeMB = (float)$index['size_mb'];

        // Determine priority based on size
        $priority = $this->determinePriority($sizeMB, $highPriorityMB, $mediumPriorityMB);

        // Build the DROP INDEX command
        $dropCommand = sprintf(
            "ALTER TABLE `%s` DROP INDEX `%s`;",
            $tableName,
            $indexName
        );

        // Create the issue
        $results->createIssue()
            ->setPriority($priority)
            ->setCategory('Database')
            ->setIssue(sprintf("Unused index '%s' on table '%s'", $indexName, $tableName))
            ->setDetails(
                "This index has never been used by any queries according to performance_schema statistics. " .
                "Unused indexes waste disk space and slow down INSERT, UPDATE, and DELETE operations because " .
                "MySQL must maintain the index on every write. Consider dropping this index after verifying " .
                "it's not needed for any queries.\n\n" .
                "IMPORTANT: Always test in a non-production environment first and verify with your development " .
                "team before dropping any indexes."
            )
            ->setCurrentValue(sprintf("Size: %.2f MB, Usage: 0 queries", $sizeMB))
            ->setRecommendedValue($dropCommand)
            ->add();
    }

    /**
     * Determine issue priority based on index size
     *
     * @param float $sizeMB
     * @param int $highPriorityMB
     * @param int $mediumPriorityMB
     * @return string
     */
    private function determinePriority(float $sizeMB, int $highPriorityMB, int $mediumPriorityMB): string
    {
        if ($sizeMB >= $highPriorityMB) {
            return 'high';
        } elseif ($sizeMB >= $mediumPriorityMB) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
