<?php

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
}
