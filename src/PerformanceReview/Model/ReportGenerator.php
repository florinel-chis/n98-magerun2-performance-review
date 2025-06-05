<?php
declare(strict_types=1);

namespace PerformanceReview\Model;

/**
 * Report generator for performance review
 */
class ReportGenerator
{
    /**
     * Priority color mappings
     */
    private const PRIORITY_COLORS = [
        IssueInterface::PRIORITY_HIGH => "\033[31m",    // Red
        IssueInterface::PRIORITY_MEDIUM => "\033[33m",  // Yellow
        IssueInterface::PRIORITY_LOW => "\033[32m"      // Green
    ];

    /**
     * Reset color constant
     */
    private const RESET_COLOR = "\033[0m";

    /**
     * Generate performance report
     *
     * @param IssueInterface[] $issues
     * @param bool $showDetails
     * @return string
     */
    public function generateReport(array $issues, bool $showDetails = false): string
    {
        $report = $this->generateHeader();
        
        // Group issues by category
        $groupedIssues = $this->groupIssuesByCategory($issues);
        
        // Generate sections
        foreach ($groupedIssues as $category => $categoryIssues) {
            $report .= $this->generateCategorySection($category, $categoryIssues, $showDetails);
        }
        
        // Add summary
        $report .= $this->generateSummary($issues);
        
        return $report;
    }

    /**
     * Generate report header
     *
     * @return string
     */
    private function generateHeader(): string
    {
        $header = "\n";
        $header .= str_repeat('=', 80) . "\n";
        $header .= "                    MAGENTO 2 PERFORMANCE REVIEW REPORT\n";
        $header .= str_repeat('=', 80) . "\n";
        $header .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $header .= str_repeat('=', 80) . "\n\n";
        
        return $header;
    }

    /**
     * Group issues by category
     *
     * @param IssueInterface[] $issues
     * @return array
     */
    private function groupIssuesByCategory(array $issues): array
    {
        $grouped = [];
        foreach ($issues as $issue) {
            $category = $issue->getCategory() ?: 'Other';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $issue;
        }
        
        // Sort categories in logical order
        $sortedGrouped = [];
        $categoryOrder = ['Config', 'Database', 'Indexing', 'Cron', 'Frontend', 'Modules', 'Third-party', 'Codebase', 'API', 'PHP', 'MySQL', 'Redis'];
        
        foreach ($categoryOrder as $category) {
            if (isset($grouped[$category])) {
                $sortedGrouped[$category] = $grouped[$category];
                unset($grouped[$category]);
            }
        }
        
        // Add any remaining categories
        foreach ($grouped as $category => $categoryIssues) {
            $sortedGrouped[$category] = $categoryIssues;
        }
        
        return $sortedGrouped;
    }

    /**
     * Generate category section
     *
     * @param string $category
     * @param IssueInterface[] $issues
     * @param bool $showDetails
     * @return string
     */
    private function generateCategorySection(string $category, array $issues, bool $showDetails = false): string
    {
        $section = "== $category ==\n";
        $section .= str_repeat('-', 80) . "\n";
        
        // Sort issues by priority (High -> Medium -> Low)
        usort($issues, function(IssueInterface $a, IssueInterface $b) {
            $priorities = [
                IssueInterface::PRIORITY_HIGH => 3,
                IssueInterface::PRIORITY_MEDIUM => 2,
                IssueInterface::PRIORITY_LOW => 1
            ];
            return ($priorities[$b->getPriority()] ?? 0) - ($priorities[$a->getPriority()] ?? 0);
        });
        
        // Create table
        $section .= sprintf("%-10s | %-40s | %-25s\n", "Priority", "Recommendation", "Details");
        $section .= str_repeat('-', 10) . '+' . str_repeat('-', 42) . '+' . str_repeat('-', 27) . "\n";
        
        foreach ($issues as $issue) {
            $priority = $issue->getPriority();
            $color = self::PRIORITY_COLORS[$priority] ?? '';
            $recommendation = $this->truncateString($issue->getIssue(), 40);
            
            // First line of the table
            $section .= sprintf(
                "%s%-10s%s | %-40s | %-25s\n",
                $color,
                ucfirst($priority),
                self::RESET_COLOR,
                $recommendation,
                $this->truncateString($issue->getDetails(), 25)
            );
            
            // Additional details on separate lines if needed
            if (strlen($issue->getDetails()) > 25) {
                $detailLines = $this->wrapText($issue->getDetails(), 65);
                foreach (array_slice($detailLines, 1) as $line) {
                    $section .= sprintf("%-10s | %-40s | %s\n", "", "", $line);
                }
            }
            
            // Show current vs recommended values
            if ($issue->getCurrentValue() !== null && $issue->getRecommendedValue() !== null) {
                $section .= sprintf(
                    "%-10s | %-40s | Current: %s\n",
                    "",
                    "",
                    $issue->getCurrentValue()
                );
                $section .= sprintf(
                    "%-10s | %-40s | Recommended: %s\n",
                    "",
                    "",
                    $issue->getRecommendedValue()
                );
            }
            
            // Show detailed information if requested
            if ($showDetails) {
                // Show module list if available
                $moduleList = $issue->getData('module_list');
                if ($moduleList && is_array($moduleList)) {
                    $section .= sprintf("%-10s | %-40s | \n", "", "");
                    $section .= sprintf("%-10s | %-40s | Module List:\n", "", "");
                    foreach ($moduleList as $module) {
                        $section .= sprintf("%-10s | %-40s | - %s\n", "", "", $module);
                    }
                }
                
                // Show disabled modules if available
                $disabledModules = $issue->getData('disabled_modules');
                if ($disabledModules && is_array($disabledModules)) {
                    $section .= sprintf("%-10s | %-40s | \n", "", "");
                    $section .= sprintf("%-10s | %-40s | Disabled Modules:\n", "", "");
                    foreach ($disabledModules as $module) {
                        $section .= sprintf("%-10s | %-40s | - %s\n", "", "", $module);
                    }
                }
                
                // Show impacting modules if available
                $impactingModules = $issue->getData('impacting_modules');
                if ($impactingModules && is_array($impactingModules)) {
                    $section .= sprintf("%-10s | %-40s | \n", "", "");
                    $section .= sprintf("%-10s | %-40s | Performance-impacting modules:\n", "", "");
                    foreach ($impactingModules as $module => $reason) {
                        $section .= sprintf("%-10s | %-40s | - %s\n", "", "", $module);
                        $section .= sprintf("%-10s | %-40s |   %s\n", "", "", $reason);
                    }
                }
            }
            
            $section .= str_repeat('-', 10) . '+' . str_repeat('-', 42) . '+' . str_repeat('-', 27) . "\n";
        }
        
        $section .= "\n";
        return $section;
    }

    /**
     * Generate summary section
     *
     * @param IssueInterface[] $issues
     * @return string
     */
    private function generateSummary(array $issues): string
    {
        $summary = "== Summary ==\n";
        $summary .= str_repeat('=', 80) . "\n\n";
        
        // Count issues by priority
        $priorityCounts = [
            IssueInterface::PRIORITY_HIGH => 0,
            IssueInterface::PRIORITY_MEDIUM => 0,
            IssueInterface::PRIORITY_LOW => 0
        ];
        
        foreach ($issues as $issue) {
            $priority = $issue->getPriority();
            if (isset($priorityCounts[$priority])) {
                $priorityCounts[$priority]++;
            }
        }
        
        $summary .= "Total Issues Found: " . count($issues) . "\n\n";
        
        foreach ($priorityCounts as $priority => $count) {
            $color = self::PRIORITY_COLORS[$priority];
            $summary .= sprintf(
                "  %s%-8s%s: %d issue%s\n",
                $color,
                ucfirst($priority),
                self::RESET_COLOR,
                $count,
                $count !== 1 ? 's' : ''
            );
        }
        
        $summary .= "\n";
        $summary .= "Recommended Actions:\n";
        $summary .= "1. Address all High priority issues first\n";
        $summary .= "2. Review Medium priority issues based on your specific use case\n";
        $summary .= "3. Consider Low priority issues for optimization\n";
        $summary .= "\n";
        $summary .= "For detailed information on each issue, refer to the sections above.\n";
        $summary .= str_repeat('=', 80) . "\n";
        
        return $summary;
    }

    /**
     * Truncate string to specified length
     *
     * @param string $string
     * @param int $length
     * @return string
     */
    private function truncateString(string $string, int $length): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }
        return substr($string, 0, $length - 3) . '...';
    }

    /**
     * Wrap text to specified width
     *
     * @param string $text
     * @param int $width
     * @return array
     */
    private function wrapText(string $text, int $width): array
    {
        $lines = [];
        $words = explode(' ', $text);
        $currentLine = '';
        
        foreach ($words as $word) {
            if (strlen($currentLine . ' ' . $word) <= $width) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }
        
        if ($currentLine) {
            $lines[] = $currentLine;
        }
        
        return $lines ?: [$text];
    }
}