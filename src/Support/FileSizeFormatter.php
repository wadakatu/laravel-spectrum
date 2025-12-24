<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

/**
 * Utility class for formatting file sizes to human-readable format.
 */
class FileSizeFormatter
{
    private const BYTES_PER_GB = 1073741824;

    private const BYTES_PER_MB = 1048576;

    private const BYTES_PER_KB = 1024;

    /**
     * Format file size to human readable format.
     */
    public static function format(int $bytes): string
    {
        if ($bytes >= self::BYTES_PER_GB) {
            $size = $bytes / self::BYTES_PER_GB;

            return $size == (int) $size ? sprintf('%dGB', (int) $size) : sprintf('%.1fGB', $size);
        }

        if ($bytes >= self::BYTES_PER_MB) {
            $size = $bytes / self::BYTES_PER_MB;

            return $size == (int) $size ? sprintf('%dMB', (int) $size) : sprintf('%.1fMB', $size);
        }

        if ($bytes >= self::BYTES_PER_KB) {
            $size = $bytes / self::BYTES_PER_KB;

            return $size == (int) $size ? sprintf('%dKB', (int) $size) : sprintf('%.1fKB', $size);
        }

        return sprintf('%dB', $bytes);
    }
}
