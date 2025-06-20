<?php
declare(strict_types=1);

namespace PerformanceReview\Api;

/**
 * Interface for analyzers that need Magento dependencies
 */
interface DependencyAwareInterface
{
    /**
     * Set Magento dependencies for the analyzer
     * 
     * @param array $dependencies Array of injected dependencies
     * @return void
     */
    public function setDependencies(array $dependencies): void;
}