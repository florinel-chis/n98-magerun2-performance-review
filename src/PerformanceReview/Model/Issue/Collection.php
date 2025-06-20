<?php
declare(strict_types=1);

namespace PerformanceReview\Model\Issue;

use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;

/**
 * Collection class for performance issues
 * 
 * Similar to n98-magerun2's Result\Collection pattern
 */
class Collection
{
    /**
     * @var IssueInterface[]
     */
    private array $issues = [];
    
    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;
    
    /**
     * Constructor
     * 
     * @param IssueFactory $issueFactory
     */
    public function __construct(IssueFactory $issueFactory)
    {
        $this->issueFactory = $issueFactory;
    }
    
    /**
     * Create a new issue builder
     * 
     * @return IssueBuilder
     */
    public function createIssue(): IssueBuilder
    {
        return new IssueBuilder($this, $this->issueFactory);
    }
    
    /**
     * Add an issue to the collection
     * 
     * @param IssueInterface $issue
     * @return void
     */
    public function addIssue(IssueInterface $issue): void
    {
        $this->issues[] = $issue;
    }
    
    /**
     * Get all issues in the collection
     * 
     * @return IssueInterface[]
     */
    public function getIssues(): array
    {
        return $this->issues;
    }
    
    /**
     * Get count of issues
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->issues);
    }
    
    /**
     * Check if collection has issues
     * 
     * @return bool
     */
    public function hasIssues(): bool
    {
        return !empty($this->issues);
    }
}