<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\Support\FormatInferrer;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FormatInferrerTest extends TestCase
{
    private FormatInferrer $inferrer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inferrer = new FormatInferrer;
    }

    #[Test]
    public function it_infers_date_format_from_date_rule(): void
    {
        $this->assertEquals('date', $this->inferrer->inferDateFormat(['date']));
        $this->assertEquals('date', $this->inferrer->inferDateFormat('date'));
    }

    #[Test]
    public function it_infers_date_format_from_date_format_rule(): void
    {
        $this->assertEquals('date', $this->inferrer->inferDateFormat(['date_format:Y-m-d']));
        $this->assertEquals('date', $this->inferrer->inferDateFormat('date_format:Y/m/d'));
    }

    #[Test]
    public function it_infers_datetime_format_from_date_format_with_time(): void
    {
        $this->assertEquals('date-time', $this->inferrer->inferDateFormat(['date_format:Y-m-d H:i:s']));
        $this->assertEquals('date-time', $this->inferrer->inferDateFormat('date_format:Y-m-d H:i'));
        $this->assertEquals('date-time', $this->inferrer->inferDateFormat('date_format:H:i:s'));
    }

    #[Test]
    public function it_returns_null_for_non_date_rules(): void
    {
        $this->assertNull($this->inferrer->inferDateFormat(['string', 'max:255']));
        $this->assertNull($this->inferrer->inferDateFormat('required|string'));
    }

    #[Test]
    public function it_infers_email_format(): void
    {
        $this->assertEquals('email', $this->inferrer->inferFormat(['email']));
        $this->assertEquals('email', $this->inferrer->inferFormat('required|email'));
    }

    #[Test]
    public function it_infers_uri_format_from_url_rule(): void
    {
        $this->assertEquals('uri', $this->inferrer->inferFormat(['url']));
        $this->assertEquals('uri', $this->inferrer->inferFormat('required|url'));
    }

    #[Test]
    public function it_infers_uuid_format(): void
    {
        $this->assertEquals('uuid', $this->inferrer->inferFormat(['uuid']));
        $this->assertEquals('uuid', $this->inferrer->inferFormat('required|uuid'));
    }

    #[Test]
    public function it_infers_ipv4_format(): void
    {
        $this->assertEquals('ipv4', $this->inferrer->inferFormat(['ip']));
        $this->assertEquals('ipv4', $this->inferrer->inferFormat(['ipv4']));
        $this->assertEquals('ipv4', $this->inferrer->inferFormat('required|ip'));
    }

    #[Test]
    public function it_infers_ipv6_format(): void
    {
        $this->assertEquals('ipv6', $this->inferrer->inferFormat(['ipv6']));
        $this->assertEquals('ipv6', $this->inferrer->inferFormat('required|ipv6'));
    }

    #[Test]
    public function it_infers_date_format_via_infer_format(): void
    {
        $this->assertEquals('date', $this->inferrer->inferFormat(['date']));
        $this->assertEquals('date', $this->inferrer->inferFormat(['date_format:Y-m-d']));
        $this->assertEquals('date-time', $this->inferrer->inferFormat(['date_format:Y-m-d H:i:s']));
    }

    #[Test]
    public function it_returns_null_for_unknown_format(): void
    {
        $this->assertNull($this->inferrer->inferFormat(['string', 'max:255']));
        $this->assertNull($this->inferrer->inferFormat('required|string'));
    }

    #[Test]
    public function it_handles_non_string_rules_in_array(): void
    {
        $rules = ['required', fn () => true, 'email'];
        $this->assertEquals('email', $this->inferrer->inferFormat($rules));

        $rules = [fn () => true, 'string'];
        $this->assertNull($this->inferrer->inferFormat($rules));
    }

    #[Test]
    public function it_handles_mixed_rules_with_format(): void
    {
        $this->assertEquals('email', $this->inferrer->inferFormat(['required', 'string', 'email', 'max:255']));
        $this->assertEquals('uuid', $this->inferrer->inferFormat(['required', 'uuid']));
    }

    #[Test]
    public function it_handles_empty_input(): void
    {
        $this->assertNull($this->inferrer->inferFormat([]));
        $this->assertNull($this->inferrer->inferFormat(''));
        $this->assertNull($this->inferrer->inferDateFormat([]));
        $this->assertNull($this->inferrer->inferDateFormat(''));
    }

    #[Test]
    public function it_returns_first_matching_format(): void
    {
        // First format rule wins
        $this->assertEquals('email', $this->inferrer->inferFormat(['email', 'url']));
        $this->assertEquals('uri', $this->inferrer->inferFormat(['url', 'email']));
    }
}
