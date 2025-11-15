# Development Workflow - Adding New Features

This guide shows the complete workflow for adding new features to the Performance Review module, using the sub-agents and best practices.

## Example: Adding "Unused Database Indexes" Analyzer

Let's walk through adding a new analyzer to detect unused database indexes in Magento.

---

## Workflow Overview

```
1. Requirements → 2. Create → 3. Test → 4. Review → 5. Optimize → 6. Document → 7. Deploy
      ↓              ↓          ↓         ↓           ↓            ↓             ↓
   Planning    analyzer-   test-    code-     performance-  documentation-   Git commit
               creator     writer   reviewer   optimizer     updater          & push
```

**Estimated Time:** 2-3 hours for a new analyzer (with sub-agents)

---

## Phase 1: Requirements & Planning (5-10 min)

### Define What to Check

For "Unused Indexes" analyzer:

**Goal:** Identify database indexes that exist but are never used, wasting space and slowing down writes.

**Requirements:**
- Check all Magento database tables
- Identify indexes with zero usage
- Exclude primary keys and foreign keys (always needed)
- Report index size and table name
- Priority: Medium (optimization opportunity, not critical)

**Detection Logic:**
```sql
-- MySQL 5.7+: Query performance_schema
SELECT
    t.TABLE_SCHEMA,
    t.TABLE_NAME,
    s.INDEX_NAME,
    ROUND(((s.DATA_LENGTH + s.INDEX_LENGTH) / 1024 / 1024), 2) as size_mb
FROM information_schema.TABLES t
JOIN information_schema.STATISTICS s ON t.TABLE_NAME = s.TABLE_NAME
LEFT JOIN performance_schema.table_io_waits_summary_by_index_usage u
    ON s.INDEX_NAME = u.INDEX_NAME
WHERE u.COUNT_STAR = 0
    AND s.INDEX_NAME != 'PRIMARY'
    AND t.TABLE_SCHEMA = DATABASE()
```

**Thresholds:**
- Report all unused indexes
- Priority based on size: >100MB = medium, <100MB = low

---

## Phase 2: Create the Analyzer (30-45 min)

### Using analyzer-creator Sub-agent

**Ask Claude Code:**
```
Create a custom analyzer to detect unused database indexes in Magento.

Requirements:
- Check all Magento database tables for indexes that are never used
- Exclude PRIMARY keys and foreign keys
- Report index name, table name, and size
- Priority: medium for indexes >100MB, low for smaller indexes
- Use MySQL performance_schema to detect usage
```

**What the analyzer-creator agent will do:**
1. ✅ Ask clarifying questions (if needed)
2. ✅ Design the analyzer structure
3. ✅ Create complete PHP class
4. ✅ Create YAML configuration
5. ✅ Provide testing commands
6. ✅ Document usage

**Expected Output:**

**File 1:** `app/code/YourCompany/PerformanceAnalyzer/UnusedIndexAnalyzer.php`
```php
<?php
declare(strict_types=1);

namespace YourCompany\PerformanceAnalyzer;

use PerformanceReview\Api\AnalyzerCheckInterface;
use PerformanceReview\Api\DependencyAwareInterface;
use PerformanceReview\Model\Issue\Collection;

class UnusedIndexAnalyzer implements AnalyzerCheckInterface, DependencyAwareInterface
{
    private array $dependencies = [];

    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    public function analyze(Collection $results): void
    {
        try {
            $connection = $this->dependencies['resourceConnection']->getConnection();

            $sql = "
                SELECT
                    s.TABLE_NAME,
                    s.INDEX_NAME,
                    ROUND(((t.DATA_LENGTH + t.INDEX_LENGTH) / 1024 / 1024), 2) as size_mb
                FROM information_schema.STATISTICS s
                JOIN information_schema.TABLES t
                    ON s.TABLE_NAME = t.TABLE_NAME
                    AND s.TABLE_SCHEMA = t.TABLE_SCHEMA
                LEFT JOIN performance_schema.table_io_waits_summary_by_index_usage u
                    ON s.TABLE_NAME = u.OBJECT_NAME
                    AND s.INDEX_NAME = u.INDEX_NAME
                WHERE u.COUNT_STAR = 0
                    AND s.INDEX_NAME != 'PRIMARY'
                    AND s.TABLE_SCHEMA = DATABASE()
                GROUP BY s.TABLE_NAME, s.INDEX_NAME
                HAVING size_mb > 10
                ORDER BY size_mb DESC
            ";

            $unusedIndexes = $connection->fetchAll($sql);

            foreach ($unusedIndexes as $index) {
                $priority = $index['size_mb'] > 100 ? 'medium' : 'low';

                $results->createIssue()
                    ->setPriority($priority)
                    ->setCategory('Database')
                    ->setIssue("Unused index '{$index['INDEX_NAME']}' on table '{$index['TABLE_NAME']}'")
                    ->setDetails('This index consumes space but is never used for queries. Consider removing it to improve write performance and reduce storage.')
                    ->setCurrentValue("Size: {$index['size_mb']} MB, Usage: 0")
                    ->setRecommendedValue("DROP INDEX `{$index['INDEX_NAME']}` ON `{$index['TABLE_NAME']}`")
                    ->add();
            }

        } catch (\Exception $e) {
            $results->createIssue()
                ->setPriority('low')
                ->setCategory('System')
                ->setIssue('Unused index analysis failed')
                ->setDetails($e->getMessage())
                ->add();
        }
    }
}
```

**File 2:** `<magento-root>/n98-magerun2.yaml`
```yaml
autoloaders_psr4:
  YourCompany\PerformanceAnalyzer\: 'app/code/YourCompany/PerformanceAnalyzer'

commands:
  PerformanceReview\Command\PerformanceReviewCommand:
    analyzers:
      custom:
        - id: unused-indexes
          class: 'YourCompany\PerformanceAnalyzer\UnusedIndexAnalyzer'
          description: 'Check for unused database indexes'
          category: database
```

---

## Phase 3: Test the Analyzer (10-15 min)

### Manual Testing First

```bash
# 1. Verify analyzer is registered
n98-magerun2.phar performance:review --list-analyzers | grep "unused-indexes"
# Should see: unused-indexes - Check for unused database indexes

# 2. Run the analyzer
cd /path/to/magento
n98-magerun2.phar performance:review --category=database -v

# 3. Check output for unused indexes
# Should see table with unused indexes if any exist

# 4. Test with debug verbosity
n98-magerun2.phar performance:review --category=database -vvv
```

**If it doesn't work:**
```
Ask Claude Code: "My unused-indexes analyzer isn't showing in --list-analyzers"
→ analyzer-debugger agent will diagnose and fix
```

### Create Automated Tests

**Ask Claude Code:**
```
Write tests for the UnusedIndexAnalyzer
```

**What the test-writer agent will do:**
1. ✅ Read and understand the analyzer
2. ✅ Create comprehensive test file
3. ✅ Provide mock helpers for database
4. ✅ Test all scenarios (unused indexes found, none found, errors)
5. ✅ Run tests to verify they pass

**Expected Output:**

**File:** `tests/Unit/Analyzer/UnusedIndexAnalyzerTest.php`
```php
<?php
namespace PerformanceReview\Test\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use YourCompany\PerformanceAnalyzer\UnusedIndexAnalyzer;
use PerformanceReview\Model\Issue\Collection;

class UnusedIndexAnalyzerTest extends TestCase
{
    private UnusedIndexAnalyzer $analyzer;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->analyzer = new UnusedIndexAnalyzer();
        $this->collection = new Collection();
    }

    public function testDetectsUnusedIndexes(): void
    {
        // Arrange
        $mockConnection = $this->createMockConnection([
            [
                'TABLE_NAME' => 'catalog_product_entity',
                'INDEX_NAME' => 'IDX_UNUSED_FIELD',
                'size_mb' => 150.5
            ]
        ]);

        $mockResourceConnection = $this->createMock(ResourceConnection::class);
        $mockResourceConnection->method('getConnection')->willReturn($mockConnection);

        $this->analyzer->setDependencies(['resourceConnection' => $mockResourceConnection]);

        // Act
        $this->analyzer->analyze($this->collection);

        // Assert
        $issues = $this->collection->getIssues();
        $this->assertCount(1, $issues);
        $this->assertEquals('medium', $issues[0]->getPriority()); // >100MB
        $this->assertStringContainsString('IDX_UNUSED_FIELD', $issues[0]->getIssue());
    }

    public function testNoIssuesWhenNoUnusedIndexes(): void
    {
        // Test when no unused indexes found
        $mockConnection = $this->createMockConnection([]);
        // ... test logic
    }

    public function testHandlesExceptionGracefully(): void
    {
        // Test error handling
    }

    private function createMockConnection(array $results)
    {
        $mock = $this->createMock(AdapterInterface::class);
        $mock->method('fetchAll')->willReturn($results);
        return $mock;
    }
}
```

**Run tests:**
```bash
vendor/bin/phpunit tests/Unit/Analyzer/UnusedIndexAnalyzerTest.php
# Should see: OK (3 tests, 8 assertions)
```

---

## Phase 4: Code Review (15-20 min)

### Get Quality Review

**Ask Claude Code:**
```
Review the UnusedIndexAnalyzer code before I commit
```

**What the code-reviewer agent will do:**
1. ✅ Check interface implementation
2. ✅ Review error handling
3. ✅ Check performance (uses COUNT, not loading collections)
4. ✅ Verify priority assignment
5. ✅ Check issue quality
6. ✅ Review code quality
7. ✅ Security audit
8. ✅ Provide structured report

**Expected Feedback:**

```markdown
## Code Review: UnusedIndexAnalyzer

### Summary
Overall quality: Good - Well-structured with appropriate error handling
Ready to commit: Yes, with minor suggestions

### Minor Issues ⚪

1. **Consider limiting results**
   Location: Line 35
   Suggestion: Add LIMIT 100 to prevent huge result sets

   ```php
   // Add to SQL
   ORDER BY size_mb DESC
   LIMIT 100  // Add this
   ```

2. **Add minimum size threshold**
   Suggestion: Skip indexes < 10MB (already in SQL, good!)

### Positive Aspects ✅
- ✅ Uses direct SQL query (efficient)
- ✅ Proper error handling
- ✅ Appropriate priority assignment
- ✅ Clear issue messages
- ✅ Actionable recommendations (DROP INDEX command)

### Overall Recommendation
✅ APPROVED - Ready to commit
```

**Apply suggestions:**
```bash
# Edit the file to apply minor improvements
# Re-run tests to ensure still passing
vendor/bin/phpunit tests/Unit/Analyzer/UnusedIndexAnalyzerTest.php
```

---

## Phase 5: Performance Check (Optional, 10-15 min)

**If analyzer is slow (>5 seconds):**

```
Ask Claude Code: "Optimize the UnusedIndexAnalyzer, it's taking 8 seconds to run"
```

**What the performance-optimizer agent will do:**
1. ✅ Profile execution time
2. ✅ Identify bottleneck (likely the SQL query)
3. ✅ Apply optimization (add indexes, limit results, cache)
4. ✅ Measure improvement
5. ✅ Verify functionality unchanged

**For this analyzer:** Should be fast already (direct SQL, no collection loading)

---

## Phase 6: Update Documentation (10-15 min)

### Update Module Docs

**Ask Claude Code:**
```
Update documentation for the new UnusedIndexAnalyzer
```

**What the documentation-updater agent will do:**
1. ✅ Identify affected files (README.md, CLAUDE.md, CHANGELOG.md)
2. ✅ Update README.md with new analyzer
3. ✅ Update CLAUDE.md patterns if needed
4. ✅ Add CHANGELOG.md entry
5. ✅ Ensure consistency across docs

**Expected Updates:**

**README.md:**
```markdown
### 12. **Database Optimization** (`database`)
- Unused index detection
- Index usage analysis
- Storage optimization recommendations
```

**CHANGELOG.md:**
```markdown
## [Unreleased]

### Added
- UnusedIndexAnalyzer: Detects database indexes that are never used, wasting space and slowing writes
```

**CLAUDE.md:**
```markdown
12. **database** - ... unused indexes, ...
```

---

## Phase 7: Final Verification (5 min)

### Complete Checklist

```bash
# 1. Analyzer appears in list
n98-magerun2.phar performance:review --list-analyzers | grep unused

# 2. Analyzer runs without errors
n98-magerun2.phar performance:review --category=database

# 3. All tests pass
vendor/bin/phpunit tests/Unit/Analyzer/UnusedIndexAnalyzerTest.php

# 4. Documentation updated
git status
# Should see: README.md, CHANGELOG.md, CLAUDE.md modified

# 5. Code reviewed
# ✅ code-reviewer agent approved
```

---

## Phase 8: Commit & Deploy (5-10 min)

### Git Workflow

```bash
# 1. Stage changes
git add app/code/YourCompany/PerformanceAnalyzer/UnusedIndexAnalyzer.php
git add n98-magerun2.yaml
git add tests/Unit/Analyzer/UnusedIndexAnalyzerTest.php
git add README.md CHANGELOG.md CLAUDE.md

# 2. Review changes
git diff --cached

# 3. Commit with descriptive message
git commit -m "feat: add UnusedIndexAnalyzer to detect unused database indexes

- Analyzes MySQL performance_schema for unused indexes
- Reports index size and provides DROP INDEX command
- Priority: medium for >100MB, low for smaller indexes
- Includes comprehensive unit tests
- Updates documentation

🤖 Generated with Claude Code
Co-Authored-By: Claude <noreply@anthropic.com>"

# 4. Push to remote
git push origin feature/unused-indexes

# 5. Create pull request (if using GitHub/GitLab)
gh pr create --title "Add Unused Database Indexes Analyzer" \
  --body "$(cat <<'EOF'
## Summary
Adds analyzer to detect unused database indexes in Magento installations.

## What Changed
- New `UnusedIndexAnalyzer` checks MySQL performance_schema
- Detects indexes with zero usage
- Reports size and provides DROP INDEX command
- Comprehensive unit tests included

## Test Plan
- [x] Analyzer appears in --list-analyzers
- [x] Runs without errors
- [x] Detects unused indexes correctly
- [x] All unit tests pass
- [x] Documentation updated

## Performance Impact
Minimal - uses single SQL query against performance_schema

🤖 Generated with Claude Code
EOF
)"
```

---

## Complete Workflow Summary

### Time Breakdown

| Phase | Duration | Agent Used | Output |
|-------|----------|------------|--------|
| 1. Requirements | 5-10 min | Manual | Specification |
| 2. Create | 30-45 min | analyzer-creator | PHP class + YAML |
| 3. Test | 10-15 min | test-writer | Test file |
| 4. Review | 15-20 min | code-reviewer | Review report |
| 5. Optimize | 10-15 min | performance-optimizer | Optimized code (if needed) |
| 6. Document | 10-15 min | documentation-updater | Updated docs |
| 7. Verify | 5 min | Manual | Final checks |
| 8. Deploy | 5-10 min | Manual | Git commit |

**Total: 2-3 hours** (vs 4-6 hours manually)

---

## Sub-agent Integration Flow

```
You: "Create analyzer to detect unused database indexes"
    ↓
analyzer-creator: Creates UnusedIndexAnalyzer.php + YAML config
    ↓
You: Test manually
    ↓
You: "Write tests for UnusedIndexAnalyzer"
    ↓
test-writer: Creates comprehensive test suite
    ↓
You: "Review the code"
    ↓
code-reviewer: Provides quality review report
    ↓
You: Apply suggestions, re-run tests
    ↓
You (if slow): "Optimize this analyzer"
    ↓
performance-optimizer: Improves performance
    ↓
You: "Update documentation"
    ↓
documentation-updater: Updates README, CLAUDE.md, CHANGELOG
    ↓
You: Commit and push
```

---

## Best Practices

### Do's ✅

1. **Start with clear requirements** - Know what you want to check
2. **Use analyzer-creator first** - Don't write from scratch
3. **Test early, test often** - Manual testing before automated
4. **Always review code** - Use code-reviewer before committing
5. **Document as you go** - Use documentation-updater
6. **Write descriptive commits** - Include context and purpose
7. **Follow the workflow** - Each phase builds on previous

### Don'ts ❌

1. **Don't skip testing** - Tests catch regressions
2. **Don't ignore code review** - Quality matters
3. **Don't forget documentation** - Future you will thank you
4. **Don't optimize prematurely** - Only if actually slow
5. **Don't commit broken code** - All tests must pass
6. **Don't skip manual testing** - Automated tests aren't enough

---

## Troubleshooting

### "Analyzer not showing in list"
```
Ask: "My unused-indexes analyzer isn't showing in --list-analyzers"
→ analyzer-debugger will diagnose
```

### "Tests are failing"
```
Ask: "Tests for UnusedIndexAnalyzer are failing"
→ analyzer-debugger will help fix
```

### "Analyzer is too slow"
```
Ask: "Optimize UnusedIndexAnalyzer, it takes 10 seconds"
→ performance-optimizer will improve it
```

### "Not sure if code is good"
```
Ask: "Review UnusedIndexAnalyzer code"
→ code-reviewer will assess quality
```

---

## Real-World Example: Complete Session

Here's what a real development session looks like:

```
# Session start: 9:00 AM

You: "Create an analyzer to detect unused database indexes in Magento.
      Check performance_schema, exclude PRIMARY keys, report size and
      table name. Priority medium for >100MB, low for smaller."

Claude (analyzer-creator): [Creates complete analyzer + YAML]
# 9:30 AM - Analyzer created

# Test manually
$ n98-magerun2.phar performance:review --list-analyzers | grep unused
unused-indexes - Check for unused database indexes ✓

$ n98-magerun2.phar performance:review --category=database -v
...
Medium | Unused index 'IDX_OLD_FIELD' on 'catalog_product_entity' | 125MB
Low    | Unused index 'IDX_LEGACY' on 'customer_entity' | 45MB
...
# 9:35 AM - Works!

You: "Write tests for the UnusedIndexAnalyzer"

Claude (test-writer): [Creates comprehensive test suite]
# 9:50 AM - Tests created

$ vendor/bin/phpunit tests/Unit/Analyzer/UnusedIndexAnalyzerTest.php
OK (3 tests, 8 assertions) ✓
# 9:55 AM - Tests pass

You: "Review this analyzer code before I commit"

Claude (code-reviewer): [Provides detailed review]
Overall: Good, ready to commit with minor suggestions
# 10:10 AM - Code reviewed

# Apply minor suggestions
# 10:15 AM - Improvements applied

You: "Update documentation for this new analyzer"

Claude (documentation-updater): [Updates README, CHANGELOG, CLAUDE.md]
# 10:25 AM - Docs updated

# Final verification
$ n98-magerun2.phar performance:review --category=database ✓
$ vendor/bin/phpunit tests/Unit/Analyzer/UnusedIndexAnalyzerTest.php ✓

# Commit
$ git add .
$ git commit -m "feat: add UnusedIndexAnalyzer..." ✓
$ git push ✓

# Session end: 10:35 AM
# Total time: 1 hour 35 minutes
# Quality: High (reviewed, tested, documented)
```

---

## Additional Examples

### Example 2: Custom Module Status Analyzer

```
You: "Create analyzer to check if my custom module Vendor_Module is enabled"
→ analyzer-creator: Creates ModuleStatusAnalyzer
→ test-writer: Creates tests
→ code-reviewer: Reviews code
→ documentation-updater: Updates docs
→ Commit!

Time: ~1 hour
```

### Example 3: Redis Memory Usage Analyzer

```
You: "Create analyzer to monitor Redis memory usage and alert at 80%"
→ analyzer-creator: Creates RedisMemoryAnalyzer
→ test-writer: Creates tests
→ "This analyzer is taking 5 seconds"
→ performance-optimizer: Optimizes (caches Redis info)
→ documentation-updater: Updates docs
→ Commit!

Time: ~2 hours
```

---

## Tips for Success

### 1. Be Specific in Requests
```
❌ "Create an analyzer for indexes"
✅ "Create analyzer to detect unused database indexes using MySQL
   performance_schema, exclude PRIMARY keys, report size >10MB,
   priority medium for >100MB"
```

### 2. Follow the Agent's Suggestions
Each agent suggests what to do next - follow the workflow.

### 3. Test Locally First
Always test on development Magento before production.

### 4. Use Descriptive Commit Messages
Future you (and your team) will appreciate context.

### 5. Document as You Go
Don't leave documentation for "later" - use documentation-updater.

---

## Conclusion

With the sub-agents, adding new analyzers is:
- **Fast:** 1-3 hours instead of 4-6 hours
- **Quality:** Code reviewed, tested, documented
- **Consistent:** Follows module patterns
- **Maintainable:** Well-documented and tested
- **Collaborative:** Easy for team to understand

**The sub-agents handle the tedious parts, you handle the creative decisions.**

---

**Ready to try?** Start with a simple analyzer and follow this workflow!
