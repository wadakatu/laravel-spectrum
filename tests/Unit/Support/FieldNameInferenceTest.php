<?php

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\FieldNameInference;
use LaravelSpectrum\Tests\TestCase;

class FieldNameInferenceTest extends TestCase
{
    private FieldNameInference $inference;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inference = new FieldNameInference;
    }

    public function test_infers_id_fields(): void
    {
        $result = $this->inference->inferFieldType('id');
        $this->assertEquals('id', $result['type']);
        $this->assertEquals('integer', $result['format']);

        $result = $this->inference->inferFieldType('user_id');
        $this->assertEquals('id', $result['type']);
        $this->assertEquals('integer', $result['format']);

        $result = $this->inference->inferFieldType('post_id');
        $this->assertEquals('id', $result['type']);
        $this->assertEquals('integer', $result['format']);
    }

    public function test_infers_timestamp_fields(): void
    {
        $result = $this->inference->inferFieldType('created_at');
        $this->assertEquals('timestamp', $result['type']);
        $this->assertEquals('datetime', $result['format']);

        $result = $this->inference->inferFieldType('updated_at');
        $this->assertEquals('timestamp', $result['type']);
        $this->assertEquals('datetime', $result['format']);

        $result = $this->inference->inferFieldType('deleted_at');
        $this->assertEquals('timestamp', $result['type']);
        $this->assertEquals('datetime', $result['format']);

        $result = $this->inference->inferFieldType('published_at');
        $this->assertEquals('timestamp', $result['type']);
        $this->assertEquals('datetime', $result['format']);
    }

    public function test_infers_boolean_fields(): void
    {
        $result = $this->inference->inferFieldType('is_active');
        $this->assertEquals('boolean', $result['type']);
        $this->assertEquals('boolean', $result['format']);

        $result = $this->inference->inferFieldType('is_verified');
        $this->assertEquals('boolean', $result['type']);
        $this->assertEquals('boolean', $result['format']);

        $result = $this->inference->inferFieldType('has_children');
        $this->assertEquals('boolean', $result['type']);
        $this->assertEquals('boolean', $result['format']);

        $result = $this->inference->inferFieldType('has_access');
        $this->assertEquals('boolean', $result['type']);
        $this->assertEquals('boolean', $result['format']);
    }

    public function test_infers_url_fields(): void
    {
        $result = $this->inference->inferFieldType('website_url');
        $this->assertEquals('url', $result['type']);
        $this->assertEquals('url', $result['format']);

        $result = $this->inference->inferFieldType('image_url');
        $this->assertEquals('url', $result['type']);
        $this->assertEquals('url', $result['format']);

        $result = $this->inference->inferFieldType('profile_link');
        $this->assertEquals('url', $result['type']);
        $this->assertEquals('url', $result['format']);

        $result = $this->inference->inferFieldType('download_link');
        $this->assertEquals('url', $result['type']);
        $this->assertEquals('url', $result['format']);
    }

    public function test_infers_common_field_patterns(): void
    {
        // Email
        $result = $this->inference->inferFieldType('email');
        $this->assertEquals('email', $result['type']);
        $this->assertEquals('email', $result['format']);

        // Password
        $result = $this->inference->inferFieldType('password');
        $this->assertEquals('password', $result['type']);
        $this->assertEquals('password', $result['format']);

        // Username
        $result = $this->inference->inferFieldType('username');
        $this->assertEquals('username', $result['type']);
        $this->assertEquals('alphanumeric', $result['format']);

        // Phone
        $result = $this->inference->inferFieldType('phone');
        $this->assertEquals('phone', $result['type']);
        $this->assertEquals('phone', $result['format']);

        $result = $this->inference->inferFieldType('mobile');
        $this->assertEquals('phone', $result['type']);
        $this->assertEquals('mobile', $result['format']);

        // Money
        $result = $this->inference->inferFieldType('price');
        $this->assertEquals('money', $result['type']);
        $this->assertEquals('decimal', $result['format']);

        $result = $this->inference->inferFieldType('amount');
        $this->assertEquals('money', $result['type']);
        $this->assertEquals('decimal', $result['format']);

        // Status
        $result = $this->inference->inferFieldType('status');
        $this->assertEquals('status', $result['type']);
        $this->assertEquals('string', $result['format']);

        // Age
        $result = $this->inference->inferFieldType('age');
        $this->assertEquals('age', $result['type']);
        $this->assertEquals('integer', $result['format']);
    }

    public function test_infers_name_fields(): void
    {
        $result = $this->inference->inferFieldType('first_name');
        $this->assertEquals('name', $result['type']);
        $this->assertEquals('first_name', $result['format']);

        $result = $this->inference->inferFieldType('last_name');
        $this->assertEquals('name', $result['type']);
        $this->assertEquals('last_name', $result['format']);
    }

    public function test_infers_text_fields(): void
    {
        $result = $this->inference->inferFieldType('description');
        $this->assertEquals('text', $result['type']);
        $this->assertEquals('text', $result['format']);

        $result = $this->inference->inferFieldType('content');
        $this->assertEquals('text', $result['type']);
        $this->assertEquals('html', $result['format']);
    }

    public function test_handles_unknown_fields(): void
    {
        $result = $this->inference->inferFieldType('random_field');
        $this->assertEquals('string', $result['type']);
        $this->assertEquals('text', $result['format']);

        $result = $this->inference->inferFieldType('some_data');
        $this->assertEquals('string', $result['type']);
        $this->assertEquals('text', $result['format']);
    }

    public function test_handles_compound_field_names(): void
    {
        // user_email should be recognized as email
        $result = $this->inference->inferFieldType('user_email');
        $this->assertEquals('email', $result['type']);
        $this->assertEquals('email', $result['format']);

        // profile_image_url should be recognized as URL
        $result = $this->inference->inferFieldType('profile_image_url');
        $this->assertEquals('url', $result['type']);
        $this->assertEquals('url', $result['format']);
    }

    public function test_handles_plural_field_names(): void
    {
        // Field names that might be arrays but we're inferring the type of individual items
        $result = $this->inference->inferFieldType('tags');
        $this->assertEquals('string', $result['type']);
        $this->assertEquals('text', $result['format']);

        $result = $this->inference->inferFieldType('images');
        $this->assertEquals('url', $result['type']);
        $this->assertEquals('image_url', $result['format']);
    }
}
