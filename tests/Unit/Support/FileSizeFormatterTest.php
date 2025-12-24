<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\FileSizeFormatter;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileSizeFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_bytes(): void
    {
        $this->assertEquals('500B', FileSizeFormatter::format(500));
        $this->assertEquals('0B', FileSizeFormatter::format(0));
        $this->assertEquals('1023B', FileSizeFormatter::format(1023));
    }

    #[Test]
    public function it_formats_kilobytes(): void
    {
        $this->assertEquals('1KB', FileSizeFormatter::format(1024));
        $this->assertEquals('1.5KB', FileSizeFormatter::format(1536));
        $this->assertEquals('100KB', FileSizeFormatter::format(102400));
    }

    #[Test]
    public function it_formats_megabytes(): void
    {
        $this->assertEquals('1MB', FileSizeFormatter::format(1048576));
        $this->assertEquals('2MB', FileSizeFormatter::format(2097152));
        $this->assertEquals('2.5MB', FileSizeFormatter::format(2621440));
    }

    #[Test]
    public function it_formats_gigabytes(): void
    {
        $this->assertEquals('1GB', FileSizeFormatter::format(1073741824));
        $this->assertEquals('1.5GB', FileSizeFormatter::format(1610612736));
    }

    #[Test]
    public function it_formats_whole_numbers_without_decimal(): void
    {
        // Exact 1KB
        $this->assertEquals('1KB', FileSizeFormatter::format(1024));
        // Exact 1MB
        $this->assertEquals('1MB', FileSizeFormatter::format(1048576));
        // Exact 1GB
        $this->assertEquals('1GB', FileSizeFormatter::format(1073741824));
    }

    #[Test]
    public function it_formats_fractional_numbers_with_one_decimal(): void
    {
        // 1.5KB
        $this->assertEquals('1.5KB', FileSizeFormatter::format(1536));
        // 2.5MB
        $this->assertEquals('2.5MB', FileSizeFormatter::format(2621440));
        // 1.5GB
        $this->assertEquals('1.5GB', FileSizeFormatter::format(1610612736));
    }

    #[Test]
    public function it_formats_values_just_below_thresholds(): void
    {
        // Just below MB threshold (1048575 bytes = 1023.999... KB)
        $this->assertEquals('1024.0KB', FileSizeFormatter::format(1048575));

        // Just below GB threshold (1073741823 bytes = 1023.999... MB)
        $this->assertEquals('1024.0MB', FileSizeFormatter::format(1073741823));
    }

    #[Test]
    public function it_rounds_fractional_values_to_one_decimal(): void
    {
        // 1.333KB = 1365 bytes - verify rounding behavior
        $this->assertEquals('1.3KB', FileSizeFormatter::format(1365));

        // 1.666KB = 1706 bytes
        $this->assertEquals('1.7KB', FileSizeFormatter::format(1706));
    }

    #[Test]
    public function it_throws_exception_for_negative_bytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Byte value cannot be negative');

        FileSizeFormatter::format(-1);
    }

    #[Test]
    public function it_formats_large_gigabyte_values(): void
    {
        // 10GB
        $this->assertEquals('10GB', FileSizeFormatter::format(10737418240));

        // 100GB
        $this->assertEquals('100GB', FileSizeFormatter::format(107374182400));
    }
}
