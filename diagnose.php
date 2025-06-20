#!/usr/bin/env php
<?php
/**
 * Diagnostic script to check configuration loading
 */

// Check if we can find and load a YAML file
$locations = [
    'app/etc/n98-magerun2.yaml',
    'n98-magerun2.yaml',
    $_SERVER['HOME'] . '/.n98-magerun2.yaml',
];

echo "Checking for n98-magerun2.yaml files...\n";
echo "=====================================\n\n";

foreach ($locations as $location) {
    $path = $location;
    if (strpos($path, '/') !== 0 && strpos($path, '~') !== 0) {
        // Relative path, prepend current directory
        $path = getcwd() . '/' . $path;
    }
    
    $path = str_replace('~', $_SERVER['HOME'], $path);
    
    echo "Checking: $path\n";
    
    if (file_exists($path)) {
        echo "✓ Found!\n";
        
        // Try to parse YAML
        if (function_exists('yaml_parse_file')) {
            $config = yaml_parse_file($path);
            if ($config !== false) {
                echo "✓ Valid YAML\n";
                
                // Check for our command config
                if (isset($config['commands']['PerformanceReview\\Command\\PerformanceReviewCommand'])) {
                    echo "✓ Has PerformanceReview command configuration\n";
                    
                    $analyzers = $config['commands']['PerformanceReview\\Command\\PerformanceReviewCommand']['analyzers'] ?? [];
                    if (!empty($analyzers)) {
                        echo "✓ Has analyzer configuration\n";
                        echo "\nAnalyzer groups found:\n";
                        foreach ($analyzers as $group => $items) {
                            echo "  - $group\n";
                            if (is_array($items)) {
                                foreach ($items as $analyzer) {
                                    if (isset($analyzer['id'])) {
                                        echo "    - " . $analyzer['id'] . "\n";
                                    }
                                }
                            }
                        }
                    }
                } else {
                    echo "✗ No PerformanceReview command configuration found\n";
                }
            } else {
                echo "✗ Invalid YAML syntax\n";
            }
        } else {
            echo "⚠ YAML extension not available for parsing\n";
            // Try basic regex check
            $content = file_get_contents($path);
            if (strpos($content, 'PerformanceReview') !== false) {
                echo "✓ Contains PerformanceReview configuration (basic check)\n";
            }
        }
        echo "\nContents preview:\n";
        echo "----------------\n";
        echo substr(file_get_contents($path), 0, 500) . "...\n";
        echo "----------------\n";
    } else {
        echo "✗ Not found\n";
    }
    echo "\n";
}

echo "\nDiagnostic complete.\n";