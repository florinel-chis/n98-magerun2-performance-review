<?php
declare(strict_types=1);

namespace PerformanceReview\Model;

/**
 * Issue factory
 */
class IssueFactory
{
    /**
     * Create issue instance
     *
     * @param array $data
     * @return IssueInterface
     */
    public function create(array $data = []): IssueInterface
    {
        return new Issue($data);
    }
    
    /**
     * Create issue with all parameters
     *
     * @param string $priority
     * @param string $category
     * @param string $issue
     * @param string $details
     * @param string|null $currentValue
     * @param string|null $recommendedValue
     * @param array $additionalData
     * @return IssueInterface
     */
    public function createIssue(
        string $priority,
        string $category,
        string $issue,
        string $details,
        ?string $currentValue = null,
        ?string $recommendedValue = null,
        array $additionalData = []
    ): IssueInterface {
        $data = array_merge($additionalData, [
            'priority' => $priority,
            'category' => $category,
            'issue' => $issue,
            'details' => $details,
            'current_value' => $currentValue,
            'recommended_value' => $recommendedValue
        ]);
        
        return $this->create($data);
    }
}