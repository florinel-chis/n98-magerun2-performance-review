<?php
declare(strict_types=1);

namespace PerformanceReview\Model;

/**
 * Performance issue interface
 */
interface IssueInterface
{
    /**
     * Priority constants
     */
    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';
    
    /**
     * Category constants
     */
    const CATEGORY_CONFIGURATION = 'configuration';
    const CATEGORY_DATABASE = 'database';
    const CATEGORY_CODEBASE = 'codebase';
    const CATEGORY_FRONTEND = 'frontend';
    const CATEGORY_INDEXING = 'indexing';
    const CATEGORY_MODULE = 'module';
    const CATEGORY_THIRD_PARTY = 'third_party';
    const CATEGORY_API = 'api';
    const CATEGORY_PHP = 'php';
    const CATEGORY_MYSQL = 'mysql';
    const CATEGORY_REDIS = 'redis';
    
    /**
     * Get issue priority
     *
     * @return string
     */
    public function getPriority(): string;
    
    /**
     * Get issue category
     *
     * @return string
     */
    public function getCategory(): string;
    
    /**
     * Get issue (short description/recommendation)
     *
     * @return string
     */
    public function getIssue(): string;
    
    /**
     * Get details (long description)
     *
     * @return string
     */
    public function getDetails(): string;
    
    /**
     * Get current value
     *
     * @return string|null
     */
    public function getCurrentValue(): ?string;
    
    /**
     * Get recommended value
     *
     * @return string|null
     */
    public function getRecommendedValue(): ?string;
    
    /**
     * Get additional data
     *
     * @param string|null $key
     * @return mixed
     */
    public function getData(?string $key = null);
}