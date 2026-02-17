<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\SDK;

use InvalidArgumentException;
use LaravelSpectrum\SDK\SdkCommandBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SdkCommandBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_openapi_generator_command_with_additional_properties(): void
    {
        $builder = new SdkCommandBuilder;

        $command = $builder->build(
            generator: 'openapi-generator',
            language: 'typescript',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/typescript',
            sdkConfig: [
                'binaries' => [
                    'openapi-generator' => 'openapi-generator-cli',
                ],
            ],
            languageConfig: [
                'generator_name' => 'typescript-axios',
                'package_name_property' => 'npmName',
                'additional_properties' => [
                    'supportsES6' => true,
                    'snapshot' => false,
                ],
            ],
            packageName: '@myapp/api-client'
        );

        $this->assertSame([
            'openapi-generator-cli',
            'generate',
            '-i',
            '/tmp/openapi.json',
            '-g',
            'typescript-axios',
            '-o',
            '/tmp/sdk/typescript',
            '--additional-properties=supportsES6=true,snapshot=false,npmName=@myapp/api-client',
        ], $command);
    }

    #[Test]
    public function it_builds_openapi_typescript_command(): void
    {
        $builder = new SdkCommandBuilder;

        $command = $builder->build(
            generator: 'openapi-typescript',
            language: 'typescript',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/typescript',
            sdkConfig: [],
            languageConfig: [],
            packageName: null
        );

        $this->assertSame([
            'npx',
            'openapi-typescript',
            '/tmp/openapi.json',
            '--output',
            '/tmp/sdk/typescript/index.ts',
        ], $command);
    }

    #[Test]
    public function it_throws_for_unsupported_generator(): void
    {
        $builder = new SdkCommandBuilder;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported SDK generator');

        $builder->build(
            generator: 'unknown',
            language: 'typescript',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/typescript',
            sdkConfig: [],
            languageConfig: [],
            packageName: null
        );
    }

    #[Test]
    public function it_throws_when_openapi_typescript_is_used_for_non_typescript_language(): void
    {
        $builder = new SdkCommandBuilder;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only supports the typescript language');

        $builder->build(
            generator: 'openapi-typescript',
            language: 'swift',
            specPath: '/tmp/openapi.json',
            outputPath: '/tmp/sdk/swift',
            sdkConfig: [],
            languageConfig: [],
            packageName: null
        );
    }
}
