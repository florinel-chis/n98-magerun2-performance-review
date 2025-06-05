<?php
declare(strict_types=1);

namespace PerformanceReview\Model;

/**
 * Performance issue implementation
 */
class Issue implements IssueInterface
{
    /**
     * @var array
     */
    private array $data = [];
    
    /**
     * Constructor
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
        
        // Set defaults
        if (!isset($this->data['priority'])) {
            $this->data['priority'] = self::PRIORITY_MEDIUM;
        }
        if (!isset($this->data['category'])) {
            $this->data['category'] = self::CATEGORY_CONFIGURATION;
        }
    }
    
    /**
     * @inheritdoc
     */
    public function getPriority(): string
    {
        return (string) ($this->data['priority'] ?? '');
    }
    
    /**
     * @inheritdoc
     */
    public function getCategory(): string
    {
        return (string) ($this->data['category'] ?? '');
    }
    
    /**
     * @inheritdoc
     */
    public function getIssue(): string
    {
        return (string) ($this->data['issue'] ?? '');
    }
    
    /**
     * @inheritdoc
     */
    public function getDetails(): string
    {
        return (string) ($this->data['details'] ?? '');
    }
    
    /**
     * @inheritdoc
     */
    public function getCurrentValue(): ?string
    {
        return isset($this->data['current_value']) ? (string) $this->data['current_value'] : null;
    }
    
    /**
     * @inheritdoc
     */
    public function getRecommendedValue(): ?string
    {
        return isset($this->data['recommended_value']) ? (string) $this->data['recommended_value'] : null;
    }
    
    /**
     * @inheritdoc
     */
    public function getData(?string $key = null)
    {
        if ($key === null) {
            return $this->data;
        }
        return $this->data[$key] ?? null;
    }
}