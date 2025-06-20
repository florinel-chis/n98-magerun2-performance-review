<?php
declare(strict_types=1);

namespace PerformanceReview\Api;

use PerformanceReview\Model\Issue\Collection;

/**
 * Interface for performance analyzer checks
 * 
 * This interface follows the n98-magerun2 sys:check pattern
 * allowing custom analyzers to be registered via YAML configuration
 */
interface AnalyzerCheckInterface
{
    /**
     * Run the analysis check
     * 
     * @param Collection $results Collection to add issues to
     * @return void
     */
    public function analyze(Collection $results): void;
}