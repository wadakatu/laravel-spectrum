<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\SDK\SdkCommandResult;
use LaravelSpectrum\SDK\SdkCommandRunnerInterface;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class GenerateSdkCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/spectrum/sdk'));
        File::deleteDirectory(storage_path('app/sdk-tests'));

        parent::tearDown();
    }

    #[Test]
    public function it_generates_typescript_sdk_from_existing_spec_file(): void
    {
        $specPath = storage_path('app/sdk-tests/openapi.json');
        $outputPath = storage_path('app/sdk-tests/typescript');

        File::ensureDirectoryExists(dirname($specPath));
        File::put($specPath, json_encode($this->minimalOpenApiSpec(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        $runner = Mockery::mock(SdkCommandRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(function (array $command, string $workingDirectory) use ($specPath, $outputPath): bool {
                $this->assertSame(base_path(), $workingDirectory);
                $this->assertSame('openapi-generator-cli', $command[0] ?? null);
                $this->assertSame('generate', $command[1] ?? null);
                $this->assertContains('-i', $command);
                $this->assertContains('-g', $command);
                $this->assertContains('-o', $command);
                $this->assertContains($specPath, $command);
                $this->assertContains('typescript-axios', $command);
                $this->assertContains($outputPath, $command);
                $this->assertContains('--additional-properties=supportsES6=true,npmName=@myapp/api-client', $command);

                return true;
            })
            ->andReturn(new SdkCommandResult(0, 'ok', ''));

        $this->app->instance(SdkCommandRunnerInterface::class, $runner);

        $this->artisan('spectrum:sdk', [
            'language' => 'typescript',
            '--spec' => $specPath,
            '--output' => $outputPath,
            '--package-name' => '@myapp/api-client',
        ])
            ->expectsOutputToContain('✅ SDK generated to:')
            ->assertSuccessful();
    }

    #[Test]
    public function it_generates_spec_automatically_when_spec_option_is_missing(): void
    {
        Route::get('api/users', [UserController::class, 'index']);

        $outputPath = storage_path('app/sdk-tests/swift');
        $temporarySpecPath = storage_path('app/spectrum/sdk/openapi-sdk.json');

        $runner = Mockery::mock(SdkCommandRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(function (array $command) use ($temporarySpecPath, $outputPath): bool {
                $this->assertContains('-i', $command);
                $this->assertContains('-g', $command);
                $this->assertContains('-o', $command);
                $this->assertContains('swift5', $command);
                $this->assertContains($outputPath, $command);

                $specIndex = array_search('-i', $command, true);
                $this->assertIsInt($specIndex);
                $specPath = $command[$specIndex + 1] ?? '';
                $this->assertSame($temporarySpecPath, $specPath);
                $this->assertFileExists($specPath);

                return true;
            })
            ->andReturn(new SdkCommandResult(0, 'ok', ''));

        $this->app->instance(SdkCommandRunnerInterface::class, $runner);

        $this->artisan('spectrum:sdk', [
            'language' => 'swift',
            '--output' => $outputPath,
        ])->assertSuccessful();

        $this->assertFileDoesNotExist($temporarySpecPath);
    }

    #[Test]
    public function it_fails_for_unsupported_language(): void
    {
        $this->artisan('spectrum:sdk', [
            'language' => 'rust',
        ])
            ->expectsOutputToContain('Unsupported language: rust')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_builds_openapi_typescript_command_when_generator_is_overridden(): void
    {
        $specPath = storage_path('app/sdk-tests/openapi.json');
        $outputPath = storage_path('app/sdk-tests/typescript');

        File::ensureDirectoryExists(dirname($specPath));
        File::put($specPath, json_encode($this->minimalOpenApiSpec(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        $runner = Mockery::mock(SdkCommandRunnerInterface::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(function (array $command) use ($specPath, $outputPath): bool {
                $this->assertSame('npx', $command[0] ?? null);
                $this->assertSame('openapi-typescript', $command[1] ?? null);
                $this->assertSame($specPath, $command[2] ?? null);
                $this->assertSame('--output', $command[3] ?? null);
                $this->assertSame($outputPath.'/index.ts', $command[4] ?? null);

                return true;
            })
            ->andReturn(new SdkCommandResult(0, 'ok', ''));

        $this->app->instance(SdkCommandRunnerInterface::class, $runner);

        $this->artisan('spectrum:sdk', [
            'language' => 'typescript',
            '--generator' => 'openapi-typescript',
            '--spec' => $specPath,
            '--output' => $outputPath,
            '--package-name' => '@ignored/name',
        ])
            ->expectsOutputToContain('--package-name is ignored')
            ->assertSuccessful();
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalOpenApiSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/ping' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
