<?php
declare(strict_types=1);

namespace PerformanceReview\Api;

/**
 * Interface for analyzers that need configuration
 */
interface ConfigAwareInterface
{
    /**
     * Set configuration for the analyzer
     * 
     * @param array $config Configuration array from YAML
     * @return void
     */
    public function setConfig(array $config): void;
}