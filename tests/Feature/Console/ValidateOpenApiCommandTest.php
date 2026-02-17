<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ValidateOpenApiCommandTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $createdPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->createdPaths as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function it_fails_when_spec_file_does_not_exist(): void
    {
        $missingPath = storage_path('app/spectrum/missing-openapi.json');

        $this->artisan('spectrum:validate', ['path' => $missingPath])
            ->expectsOutputToContain('OpenAPI specification file not found:')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_passes_for_a_valid_spec_file(): void
    {
        $specPath = $this->writeSpec('valid-openapi.json', $this->validSpec());

        $this->artisan('spectrum:validate', ['path' => $specPath])
            ->expectsOutput('✅ OpenAPI structural validation passed')
            ->expectsOutputToContain('Summary: PASS (0 errors, 0 warnings)')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_fails_in_strict_mode_when_warnings_are_found(): void
    {
        $specPath = $this->writeSpec('warning-openapi.json', $this->warningSpec());

        $this->artisan('spectrum:validate', [
            'path' => $specPath,
            '--strict' => true,
        ])
            ->expectsOutputToContain('warning(s) found')
            ->expectsOutputToContain('Summary: FAIL')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_outputs_json_report_format(): void
    {
        $specPath = $this->writeSpec('json-output-openapi.json', $this->validSpec());

        $exitCode = Artisan::call('spectrum:validate', [
            'path' => $specPath,
            '--format' => 'json',
        ]);

        $output = Artisan::output();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('PASS', $report['status']);
        $this->assertSame(0, $report['summary']['errors']);
        $this->assertSame(0, $report['summary']['warnings']);
    }

    #[Test]
    public function it_outputs_junit_report_format(): void
    {
        $specPath = $this->writeSpec('junit-output-openapi.json', $this->validSpec());

        $exitCode = Artisan::call('spectrum:validate', [
            'path' => $specPath,
            '--format' => 'junit',
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $output);
        $this->assertStringContainsString('testsuite name="spectrum:validate"', $output);
    }

    #[Test]
    public function it_can_generate_and_validate_in_one_step(): void
    {
        Route::get('api/users', [UserController::class, 'index']);

        $generatedSpecPath = storage_path('app/spectrum/generated-and-validated.json');
        $this->createdPaths[] = $generatedSpecPath;

        $this->artisan('spectrum:validate', [
            'path' => $generatedSpecPath,
            '--generate' => true,
        ])
            ->expectsOutputToContain('Generating OpenAPI specification before validation')
            ->expectsOutputToContain('Summary: PASS')
            ->assertExitCode(0);

        $this->assertFileExists($generatedSpecPath);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function writeSpec(string $filename, array $spec): string
    {
        $path = storage_path('app/spectrum/'.$filename);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->createdPaths[] = $path;

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function validSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Validation Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'operationId' => 'users.index',
                        'description' => 'Get user collection',
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'example' => [
                                            'data' => [
                                                ['id' => 1, 'name' => 'Alice'],
                                            ],
                                        ],
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'id' => ['type' => 'integer', 'example' => 1],
                                                    'name' => ['type' => 'string', 'example' => 'Alice'],
                                                ],
                                                'required' => ['id', 'name'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function warningSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Validation Warning API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/warnings' => [
                    'get' => [
                        'operationId' => 'warnings.index',
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
