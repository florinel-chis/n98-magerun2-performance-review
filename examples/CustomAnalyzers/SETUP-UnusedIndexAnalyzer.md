# Quick Setup Guide: UnusedIndexAnalyzer

This guide will help you set up and run the UnusedIndexAnalyzer in 5 minutes.

## Step 1: Verify Prerequisites

### Check Performance Schema is Enabled

```bash
# Connect to MySQL and check
mysql -u root -p -e "SHOW VARIABLES LIKE 'performance_schema'"
```

**Expected output:**
```
+--------------------+-------+
| Variable_name      | Value |
+--------------------+-------+
| performance_schema | ON    |
+--------------------+-------+
```

If it shows `OFF`, see [Enabling Performance Schema](#enabling-performance-schema) below.

## Step 2: Create Configuration File

Create a file at `<magento-root>/n98-magerun2.yaml`:

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

**Location options:**

1. **Project-specific (RECOMMENDED):** `<magento-root>/n98-magerun2.yaml`
2. **Alternative project location:** `<magento-root>/app/etc/n98-magerun2.yaml`
3. **User-specific:** `~/.n98-magerun2.yaml`
4. **System-wide:** `/etc/n98-magerun2.yaml`

## Step 3: Verify Registration

```bash
cd /path/to/magento
n98-magerun2.phar performance:review --list-analyzers | grep -i "unused"
```

**Expected output:**
```
unused-indexes          Detect unused database indexes that waste space and slow writes
```

If you don't see this output, see [Troubleshooting](#troubleshooting) below.

## Step 4: Run the Analyzer

```bash
# Run all database analyzers (includes unused-indexes)
n98-magerun2.phar performance:review --category=database

# OR run with verbose output for more details
n98-magerun2.phar performance:review --category=database -v

# OR save results to a file
n98-magerun2.phar performance:review --category=database --output-file=unused-indexes-report.txt
```

## Step 5: Review Results

### If Unused Indexes Found

The analyzer will show output like:

```
High Priority Issues (1):
┌──────────┬────────────────────────────────────────────────┐
│ Priority │ Issue                                          │
├──────────┼────────────────────────────────────────────────┤
│ High     │ Unused index 'idx_custom_field' on table       │
│          │ 'catalog_product_entity'                       │
│          │                                                │
│          │ Current: Size: 543.21 MB, Usage: 0 queries    │
│          │ Recommended: ALTER TABLE                       │
│          │ `catalog_product_entity` DROP INDEX            │
│          │ `idx_custom_field`;                            │
└──────────┴────────────────────────────────────────────────┘
```

**IMPORTANT:** Before running any DROP INDEX commands:

1. Test in staging/development first
2. Confirm with your development team
3. Take a database backup
4. Monitor slow query logs after dropping

### If No Issues Found

```
No issues detected! ✓

All database indexes appear to be in use.
```

This is good! It means all your indexes are being utilized.

## Complete Example: First Run

```bash
# Navigate to Magento root
cd /var/www/html/magento2

# Verify analyzer is registered
n98-magerun2.phar performance:review --list-analyzers | grep unused-indexes

# Run the analyzer
n98-magerun2.phar performance:review --category=database -v

# If issues found, save detailed report
n98-magerun2.phar performance:review --category=database --output-file=unused-indexes-$(date +%Y%m%d).txt

# Review the report
cat unused-indexes-*.txt
```

## Enabling Performance Schema

If performance_schema is disabled (shows `OFF`):

### For MySQL/Percona Server

1. **Edit MySQL config file:**

   ```bash
   # Find your config file
   mysql --help | grep my.cnf

   # Common locations:
   # - /etc/my.cnf
   # - /etc/mysql/my.cnf
   # - /etc/mysql/mysql.conf.d/mysqld.cnf
   ```

2. **Add to [mysqld] section:**

   ```ini
   [mysqld]
   performance_schema = ON
   ```

3. **Restart MySQL:**

   ```bash
   # Ubuntu/Debian
   sudo systemctl restart mysql

   # CentOS/RHEL
   sudo systemctl restart mysqld

   # macOS (Homebrew)
   brew services restart mysql
   ```

4. **Verify it's enabled:**

   ```bash
   mysql -e "SHOW VARIABLES LIKE 'performance_schema'"
   ```

### For MariaDB

Performance schema is supported but may have different default settings. Follow same steps as MySQL above.

### Performance Impact

Performance Schema has minimal impact:
- CPU overhead: <3%
- Memory usage: ~100-200MB
- Worth it for the insights gained!

## Troubleshooting

### Analyzer Not Showing in --list-analyzers

**Check file exists:**
```bash
ls -la ~/.n98-magerun2/modules/performance-review/examples/CustomAnalyzers/UnusedIndexAnalyzer.php
```

**Check YAML file exists:**
```bash
# For project-specific config
ls -la /path/to/magento/n98-magerun2.yaml

# For user config
ls -la ~/.n98-magerun2.yaml
```

**Verify YAML syntax:**
- Use spaces for indentation, NOT tabs
- Check quotes around class name
- Verify autoloader path is correct

**Run with debug output:**
```bash
n98-magerun2.phar performance:review --list-analyzers -vvv
```

### "Performance schema not available" Error

**Solution:** Enable performance_schema (see above section)

### "Analysis failed" Error

**Check MySQL permissions:**
```sql
-- Verify your MySQL user has these permissions
SHOW GRANTS FOR CURRENT_USER();

-- Should include:
-- GRANT SELECT ON `performance_schema`.* TO 'user'@'host'
-- GRANT SELECT ON `information_schema`.* TO 'user'@'host'
```

**Run with verbose output to see details:**
```bash
n98-magerun2.phar performance:review --category=database -vvv
```

### No Unused Indexes Found (but you expect some)

**Possible reasons:**

1. **Performance schema is newly enabled**
   - Statistics need time to accumulate
   - Wait 7-14 days with production traffic

2. **All indexes are actually being used**
   - This is good! No action needed.

3. **Indexes are too small**
   - Lower the `min_size_mb` threshold in config
   - Default is 10MB to reduce noise

4. **MySQL was recently restarted**
   - Performance schema statistics reset on restart
   - Wait for queries to run again

## Customizing Configuration

### For Large Databases (>100GB)

```yaml
config:
  min_size_mb: 50          # Ignore smaller indexes
  high_priority_mb: 1000   # Only critical for very large
  medium_priority_mb: 250
```

### For Small Databases (<10GB)

```yaml
config:
  min_size_mb: 1           # Report even small unused indexes
  high_priority_mb: 100
  medium_priority_mb: 25
```

### Skip This Analyzer

If you want to temporarily disable it:

```bash
# Skip via command line
n98-magerun2.phar performance:review --skip-analyzer=unused-indexes

# Or disable in YAML
commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      custom:
        - id: unused-indexes
          class: 'MyCompany\PerformanceAnalyzer\UnusedIndexAnalyzer'
          enabled: false  # Disable
```

## Next Steps

After running the analyzer:

1. **Review the full documentation:** See `README-UnusedIndexAnalyzer.md` for complete details
2. **Test in staging:** Never drop indexes directly in production
3. **Monitor results:** Check slow query logs after any changes
4. **Run regularly:** Schedule quarterly reviews to catch old indexes

## Quick Reference

```bash
# List analyzers
n98-magerun2.phar performance:review --list-analyzers

# Run database category (includes unused-indexes)
n98-magerun2.phar performance:review --category=database

# Run with verbose output
n98-magerun2.phar performance:review --category=database -v

# Save to file
n98-magerun2.phar performance:review --category=database --output-file=report.txt

# Skip specific analyzer
n98-magerun2.phar performance:review --skip-analyzer=unused-indexes

# Check performance_schema status
mysql -e "SHOW VARIABLES LIKE 'performance_schema'"
```

## Support

Need help?

1. Read the full documentation: `README-UnusedIndexAnalyzer.md`
2. Check the troubleshooting guide in performance-review module
3. Review example analyzers in `examples/CustomAnalyzers/`
4. Submit an issue to n98-magerun2 repository

---

**Setup Time:** 5 minutes
**First Run Time:** 1-5 seconds
**Recommended Frequency:** Quarterly

Happy optimizing!
