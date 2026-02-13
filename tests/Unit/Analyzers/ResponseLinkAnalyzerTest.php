<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\ResponseLinkAnalyzer;
use LaravelSpectrum\DTO\ResponseLinkInfo;
use LaravelSpectrum\Tests\Fixtures\Controllers\ResponseLinkTestController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ResponseLinkAnalyzerTest extends TestCase
{
    private ResponseLinkAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new ResponseLinkAnalyzer;
    }

    #[Test]
    public function it_detects_single_response_link_attribute(): void
    {
        $method = new ReflectionMethod(ResponseLinkTestController::class, 'store');

        $links = $this->analyzer->analyze($method);

        $this->assertCount(1, $links);
        $this->assertInstanceOf(ResponseLinkInfo::class, $links[0]);
        $this->assertSame(201, $links[0]->statusCode);
        $this->assertSame('GetUserById', $links[0]->name);
        $this->assertSame('usersShow', $links[0]->operationId);
        $this->assertSame(['user' => '$response.body#/id'], $links[0]->parameters);
    }

    #[Test]
    public function it_detects_multiple_response_link_attributes(): void
    {
        $method = new ReflectionMethod(ResponseLinkTestController::class, 'show');

        $links = $this->analyzer->analyze($method);

        $this->assertCount(2, $links);
        $this->assertSame('GetUserComments', $links[0]->name);
        $this->assertSame('GetUserPosts', $links[1]->name);
        $this->assertSame('usersPostsIndex', $links[1]->operationId);
    }

    #[Test]
    public function it_returns_empty_array_for_method_without_response_links(): void
    {
        $method = new ReflectionMethod(ResponseLinkTestController::class, 'index');

        $links = $this->analyzer->analyze($method);

        $this->assertEmpty($links);
    }

    #[Test]
    public function it_merges_config_response_links(): void
    {
        $configLinks = [
            ResponseLinkTestController::class.'@index' => [
                [
                    'statusCode' => 200,
                    'name' => 'GetUserById',
                    'operationId' => 'usersShow',
                    'parameters' => ['user' => '$response.body#/data/0/id'],
                ],
            ],
        ];

        $analyzer = new ResponseLinkAnalyzer($configLinks);
        $method = new ReflectionMethod(ResponseLinkTestController::class, 'index');

        $links = $analyzer->analyze($method);

        $this->assertCount(1, $links);
        $this->assertSame('GetUserById', $links[0]->name);
        $this->assertSame('usersShow', $links[0]->operationId);
    }

    #[Test]
    public function it_collects_error_for_invalid_config_response_link(): void
    {
        $configLinks = [
            ResponseLinkTestController::class.'@index' => [
                ['statusCode' => 200, 'name' => 'missing-operation'],
            ],
        ];

        $analyzer = new ResponseLinkAnalyzer($configLinks);
        $method = new ReflectionMethod(ResponseLinkTestController::class, 'index');

        $links = $analyzer->analyze($method);

        $this->assertEmpty($links);
        $this->assertTrue($analyzer->getErrorCollector()->hasErrors());
    }

    #[Test]
    public function it_combines_attribute_and_config_response_links(): void
    {
        $configLinks = [
            ResponseLinkTestController::class.'@store' => [
                [
                    'statusCode' => 201,
                    'name' => 'GetUserPosts',
                    'operationId' => 'usersPostsIndex',
                    'parameters' => ['user' => '$response.body#/id'],
                ],
            ],
        ];

        $analyzer = new ResponseLinkAnalyzer($configLinks);
        $method = new ReflectionMethod(ResponseLinkTestController::class, 'store');

        $links = $analyzer->analyze($method);

        $this->assertCount(2, $links);
        $this->assertSame('GetUserById', $links[0]->name);
        $this->assertSame('GetUserPosts', $links[1]->name);
    }

    #[Test]
    public function it_collects_error_for_invalid_attribute_response_link(): void
    {
        $method = new ReflectionMethod(ResponseLinkTestController::class, 'invalid');

        $links = $this->analyzer->analyze($method);

        $this->assertEmpty($links);
        $this->assertTrue($this->analyzer->getErrorCollector()->hasErrors());
    }
}
