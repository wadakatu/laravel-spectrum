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

    // ========== Password format tests ==========

    #[Test]
    public function it_infers_password_format_from_password_rule(): void
    {
        $this->assertEquals('password', $this->inferrer->inferFormat(['Password::min(8)']));
        $this->assertEquals('password', $this->inferrer->inferFormat(['required', 'Password::min(8)->mixedCase()']));
    }

    #[Test]
    public function it_infers_password_format_from_fully_qualified_class(): void
    {
        $this->assertEquals('password', $this->inferrer->inferFormat(['\\Illuminate\\Validation\\Rules\\Password::min(8)']));
        $this->assertEquals('password', $this->inferrer->inferFormat(['Illuminate\\Validation\\Rules\\Password::min(12)']));
    }

    #[Test]
    public function it_infers_password_format_with_chained_methods(): void
    {
        $rules = ['Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()'];
        $this->assertEquals('password', $this->inferrer->inferFormat($rules));
    }

    #[Test]
    public function it_infers_password_format_from_default_method(): void
    {
        $this->assertEquals('password', $this->inferrer->inferFormat(['Password::default()']));
    }

    #[Test]
    public function it_prioritizes_password_format_over_other_rules(): void
    {
        // Password:: should be detected even with other format-related rules
        $rules = ['required', 'string', 'Password::min(8)'];
        $this->assertEquals('password', $this->inferrer->inferFormat($rules));
    }

    #[Test]
    public function it_does_not_infer_password_from_non_password_rules(): void
    {
        // Non-password rules should not return 'password' format
        $this->assertNull($this->inferrer->inferFormat(['required', 'string', 'min:8']));
        $this->assertNull($this->inferrer->inferFormat(['confirmed']));
    }
}
