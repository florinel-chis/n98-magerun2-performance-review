<?php
declare(strict_types=1);

namespace PerformanceReview\Model\Issue;

use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\IssueInterface;

/**
 * Builder class for creating issues fluently
 */
class IssueBuilder
{
    /**
     * @var Collection
     */
    private Collection $collection;
    
    /**
     * @var IssueFactory
     */
    private IssueFactory $issueFactory;
    
    /**
     * @var array
     */
    private array $data = [];
    
    /**
     * Constructor
     * 
     * @param Collection $collection
     * @param IssueFactory $issueFactory
     */
    public function __construct(Collection $collection, IssueFactory $issueFactory)
    {
        $this->collection = $collection;
        $this->issueFactory = $issueFactory;
    }
    
    /**
     * Set issue priority
     * 
     * @param string $priority One of: high, medium, low
     * @return self
     */
    public function setPriority(string $priority): self
    {
        $this->data['priority'] = $priority;
        return $this;
    }
    
    /**
     * Set issue category
     * 
     * @param string $category
     * @return self
     */
    public function setCategory(string $category): self
    {
        $this->data['category'] = $category;
        return $this;
    }
    
    /**
     * Set issue title/recommendation
     * 
     * @param string $issue
     * @return self
     */
    public function setIssue(string $issue): self
    {
        $this->data['issue'] = $issue;
        return $this;
    }
    
    /**
     * Set issue details
     * 
     * @param string $details
     * @return self
     */
    public function setDetails(string $details): self
    {
        $this->data['details'] = $details;
        return $this;
    }
    
    /**
     * Set current value
     * 
     * @param mixed $value
     * @return self
     */
    public function setCurrentValue($value): self
    {
        $this->data['current_value'] = (string) $value;
        return $this;
    }
    
    /**
     * Set recommended value
     * 
     * @param mixed $value
     * @return self
     */
    public function setRecommendedValue($value): self
    {
        $this->data['recommended_value'] = (string) $value;
        return $this;
    }
    
    /**
     * Set additional data
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setData(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * Build and add the issue to the collection
     * 
     * @return void
     */
    public function add(): void
    {
        $issue = $this->issueFactory->create($this->data);
        $this->collection->addIssue($issue);
    }
}