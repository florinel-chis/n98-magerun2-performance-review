# UnusedIndexAnalyzer

## Purpose

Detects MySQL database indexes that are never used by queries, helping identify wasted storage space and performance overhead. Unused indexes consume disk space and slow down INSERT, UPDATE, and DELETE operations because MySQL must maintain them on every write operation.

## How It Works

The analyzer queries MySQL's `performance_schema.table_io_waits_summary_by_index_usage` table to identify indexes with zero usage. It then calculates the size of each unused index and reports those exceeding configurable thresholds.

## Prerequisites

### MySQL Configuration Required

The analyzer requires MySQL `performance_schema` to be enabled. This is the default in MySQL 5.7+ and 8.0+.

**To verify performance_schema is enabled:**

```bash
mysql -e "SHOW VARIABLES LIKE 'performance_schema'"
```

Should show `ON`.

**To enable performance_schema** (if disabled):

Add to your MySQL configuration file (`my.cnf` or `my.ini`):

```ini
[mysqld]
performance_schema = ON
```

Then restart MySQL.

**Note:** Performance Schema has minimal performance impact (typically <3% in production environments).

## Configuration

### Default Configuration

```yaml
- id: unused-indexes
  class: 'MyCompany\PerformanceAnalyzer\UnusedIndexAnalyzer'
  description: 'Detect unused database indexes that waste space and slow writes'
  category: database
  config:
    min_size_mb: 10          # Only report indexes larger than this (MB)
    high_priority_mb: 500    # Indexes >500MB are high priority
    medium_priority_mb: 100  # Indexes >100MB are medium priority
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `min_size_mb` | integer | 10 | Minimum index size in MB to report. Smaller indexes are ignored to reduce noise. |
| `high_priority_mb` | integer | 500 | Indexes larger than this threshold are reported as HIGH priority. |
| `medium_priority_mb` | integer | 100 | Indexes larger than this threshold are reported as MEDIUM priority. |

### Customizing Thresholds

You can adjust thresholds based on your environment:

**For large databases (>100GB):**
```yaml
config:
  min_size_mb: 50          # Ignore smaller indexes
  high_priority_mb: 1000   # Higher threshold for high priority
  medium_priority_mb: 250
```

**For small databases (<10GB):**
```yaml
config:
  min_size_mb: 1           # Report even small unused indexes
  high_priority_mb: 100
  medium_priority_mb: 25
```

## Priority Levels

The analyzer assigns priority based on index size:

| Priority | Size Threshold | Impact | Example |
|----------|---------------|--------|---------|
| **HIGH** | >500MB (default) | Significant storage waste and write performance impact | Large catalog_product_entity index |
| **MEDIUM** | >100MB (default) | Notable storage waste and moderate write impact | Large URL rewrite index |
| **LOW** | 10-100MB (default) | Minor improvement opportunity | Small custom table indexes |

## Issues Detected

### When Issues Are Created

An issue is created for each unused index that meets these criteria:

1. Index usage count = 0 (never used according to performance_schema)
2. Index size >= `min_size_mb` threshold
3. Index is NOT a PRIMARY key (PRIMARY keys are always needed)
4. Table uses InnoDB storage engine

### Issue Output Format

```
Priority | Recommendation | Details
---------|----------------|--------
High     | Unused index 'idx_custom_field' on table 'catalog_product_entity' | This index has never been used...
         |                | Current: Size: 543.21 MB, Usage: 0 queries
         |                | Recommended: ALTER TABLE `catalog_product_entity` DROP INDEX `idx_custom_field`;
```

## Usage

### Setup (One-time)

1. **Create configuration file** at `<magento-root>/n98-magerun2.yaml`:

```yaml
# PSR-4 autoloader for custom analyzers
autoloaders_psr4:
  MyCompany\PerformanceAnalyzer\: '~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers'

# Register the analyzer
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      custom:
        - id: unused-indexes
          class: 'MyCompany\PerformanceAnalyzer\UnusedIndexAnalyzer'
          description: 'Detect unused database indexes that waste space and slow writes'
          category: database
          config:
            min_size_mb: 10
            high_priority_mb: 500
            medium_priority_mb: 100
```

### Running the Analyzer

```bash
# Verify analyzer is registered
n98-magerun2.phar performance:review --list-analyzers | grep -i "unused"

# Run all database analyzers (includes unused-indexes)
n98-magerun2.phar performance:review --category=database

# Run ONLY the unused index analyzer
n98-magerun2.phar performance:review --category=database --skip-analyzer=database-size --skip-analyzer=database-tables

# Run with verbose output for debugging
n98-magerun2.phar performance:review --category=database -vvv

# Run from specific Magento directory
cd /path/to/magento && n98-magerun2.phar performance:review --category=database

# Save results to file
n98-magerun2.phar performance:review --category=database --output-file=unused-indexes-report.txt
```

## Example Output

### When Unused Indexes Are Found

```
================================================================================
                         Performance Review Report
================================================================================

High Priority Issues (1):
┌──────────┬────────────────────────────────────────────────┬─────────────────┐
│ Priority │ Issue                                          │ Details         │
├──────────┼────────────────────────────────────────────────┼─────────────────┤
│ High     │ Unused index 'idx_special_price' on table      │ This index has  │
│          │ 'catalog_product_entity_decimal'               │ never been used │
│          │                                                │ by any queries  │
│          │                                                │                 │
│          │ Current: Size: 543.21 MB, Usage: 0 queries    │                 │
│          │ Recommended: ALTER TABLE                       │                 │
│          │ `catalog_product_entity_decimal`               │                 │
│          │ DROP INDEX `idx_special_price`;                │                 │
└──────────┴────────────────────────────────────────────────┴─────────────────┘

Medium Priority Issues (2):
[Similar output for medium priority indexes]

Low Priority Issues (5):
[Similar output for low priority indexes]

Total Issues Found: 8
High Priority: 1
Medium Priority: 2
Low Priority: 5
```

### When Performance Schema Is Disabled

```
Low Priority Issues (1):
┌──────────┬────────────────────────────────────────────────┬─────────────────┐
│ Priority │ Issue                                          │ Details         │
├──────────┼────────────────────────────────────────────────┼─────────────────┤
│ Low      │ MySQL performance_schema not available         │ The performance │
│          │ for index analysis                             │ schema is       │
│          │                                                │ required to     │
│          │                                                │ detect unused   │
│          │ Recommended: Enable performance_schema         │ indexes.        │
│          │ in my.cnf                                      │                 │
└──────────┴────────────────────────────────────────────────┴─────────────────┘
```

### When No Unused Indexes Found

```
================================================================================
                         Performance Review Report
================================================================================

No issues detected! ✓

All database indexes appear to be in use.
```

## Acting on Results

### Before Dropping Any Index

**CRITICAL: Always follow these steps before dropping indexes:**

1. **Verify in staging/development first**
   - Never drop indexes directly in production
   - Test the DROP command in a non-production environment

2. **Confirm with development team**
   - Check if the index is needed for specific queries
   - Verify application code doesn't rely on the index

3. **Check application logs**
   - Review slow query logs for any mention of the index
   - Check if specific customer queries might use it

4. **Monitor after removal**
   - Watch slow query logs after dropping
   - Monitor query performance metrics
   - Be prepared to recreate if needed

### Dropping an Unused Index

```sql
-- Example: Drop unused index from catalog_product_entity
ALTER TABLE `catalog_product_entity` DROP INDEX `idx_custom_field`;
```

### If You Need to Recreate an Index

If you accidentally drop a needed index, recreate it:

```sql
-- Example: Recreate index
ALTER TABLE `catalog_product_entity`
ADD INDEX `idx_custom_field` (`custom_field`);
```

## Troubleshooting

### "Performance schema not available"

**Cause:** MySQL performance_schema is disabled.

**Solution:**
1. Edit MySQL config file (`my.cnf` or `my.ini`)
2. Add: `performance_schema = ON`
3. Restart MySQL server
4. Wait a few days for statistics to accumulate

### "No unused indexes found" but you expect some

**Possible causes:**

1. **Performance schema statistics are new**
   - Statistics only accumulate after performance_schema is enabled
   - Wait a few days for realistic usage data

2. **All indexes are actually being used**
   - This is good! No action needed.

3. **Indexes are too small**
   - Adjust `min_size_mb` to a lower value
   - Default is 10MB to reduce noise

4. **Database has been recently reset**
   - Performance schema statistics reset on MySQL restart
   - Wait for production queries to run

### "Analysis failed" error

**Cause:** Query failed (uncommon MySQL versions or permissions issue).

**Solution:**
1. Check MySQL user has SELECT permission on `performance_schema`
2. Check MySQL user has SELECT permission on `information_schema`
3. Run with verbose mode: `n98-magerun2.phar performance:review -vvv`
4. Check error details in output

### Analyzer not showing up in --list-analyzers

**Troubleshooting steps:**

1. **Verify file exists:**
   ```bash
   ls -la ~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers/UnusedIndexAnalyzer.php
   ```

2. **Check YAML syntax:**
   - Ensure proper indentation (spaces, not tabs)
   - Verify class name matches exactly: `MyCompany\PerformanceAnalyzer\UnusedIndexAnalyzer`

3. **Verify YAML location:**
   ```bash
   ls -la <magento-root>/n98-magerun2.yaml
   ```

4. **Test with verbose output:**
   ```bash
   n98-magerun2.phar performance:review --list-analyzers -vvv
   ```

## Performance Impact

### On Analysis

- **Execution time:** 1-5 seconds for typical databases
- **Memory usage:** Minimal (<10MB)
- **Database load:** Very light (read-only queries on system tables)

### Of Dropping Indexes

**Positive impacts:**
- Reduced disk space usage
- Faster INSERT/UPDATE/DELETE operations
- Reduced backup size and time
- Lower replication lag (if using MySQL replication)

**Potential risks:**
- If index was actually needed, queries become slower
- This is why testing in staging first is CRITICAL

## MySQL Versions Supported

- MySQL 5.7+ (performance_schema enabled by default)
- MySQL 8.0+ (performance_schema enabled by default)
- MariaDB 10.2+ (performance_schema supported)
- Percona Server 5.7+ (performance_schema supported)

Older versions may work if performance_schema is manually enabled.

## Best Practices

1. **Let statistics accumulate**
   - Run the analyzer after at least 7-14 days of production traffic
   - Seasonal businesses should wait through a full cycle

2. **Review regularly**
   - Run quarterly to catch indexes from old customizations
   - Run after major module installations/uninstallations

3. **Document decisions**
   - Keep a log of which indexes you drop and why
   - Note the date and who approved the change

4. **Staged rollout**
   - Drop indexes in staging first
   - Monitor for 1-2 weeks
   - Then proceed to production

5. **Backup first**
   - Always have a database backup before dropping indexes
   - Know your restore procedure

## Related Analyzers

Other analyzers that work well with UnusedIndexAnalyzer:

- **DatabaseAnalyzer** (core) - Reports database size
- **MysqlConfigurationAnalyzer** (core) - Checks MySQL settings
- **DatabaseTableAnalyzer** (core) - Reports large tables

## Technical Notes

### Query Used

The analyzer uses this approach:

1. Query `information_schema` for InnoDB index sizes
2. Join with `performance_schema.table_io_waits_summary_by_index_usage` for usage stats
3. Filter where `COUNT_STAR = 0` (never used)
4. Exclude PRIMARY keys
5. Apply size threshold

### Limitations

- **Only detects completely unused indexes** - Rarely used indexes still show as "used"
- **Statistics reset on MySQL restart** - May show false positives after recent restart
- **InnoDB only** - Does not analyze MyISAM tables (deprecated in Magento anyway)
- **Requires performance_schema** - Won't work if disabled

### False Positives

Rare scenarios where "unused" indexes might still be needed:

1. **Disaster recovery queries** - Rarely run but critical
2. **Seasonal queries** - Used only during specific times of year
3. **Admin-only queries** - Rarely executed but important
4. **Backup/export queries** - Not captured in normal operation

Always verify with your team before dropping!

## Support

For issues or questions:

1. Check TROUBLESHOOTING.md in the performance-review module
2. Review CUSTOM_ANALYZERS.md for custom analyzer patterns
3. Submit an issue to the n98-magerun2 repository

## Version History

- **1.0.0** (2025-01-31) - Initial release
  - Detects unused indexes via performance_schema
  - Configurable size thresholds
  - Priority assignment based on index size
  - Graceful handling of disabled performance_schema

---

**Created for:** n98-magerun2 Performance Review Module v2.0
**PHP Version:** 8.0+
**MySQL Version:** 5.7+
