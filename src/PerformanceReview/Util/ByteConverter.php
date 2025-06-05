<?php
declare(strict_types=1);

namespace PerformanceReview\Util;

/**
 * Byte converter utility
 */
class ByteConverter
{
    /**
     * Convert bytes to human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public function convert(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Convert human readable string to bytes
     *
     * @param string $value
     * @return int
     */
    public function toBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}