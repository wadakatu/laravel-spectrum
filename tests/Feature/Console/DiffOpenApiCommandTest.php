<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DiffOpenApiCommandTest extends TestCase
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
    public function it_reports_breaking_changes_when_comparing_two_files(): void
    {
        $fromPath = $this->writeSpec('diff-from.json', $this->baselineSpec());
        $toPath = $this->writeSpec('diff-to.json', $this->breakingSpec());

        $this->artisan('spectrum:diff', [
            'from' => $fromPath,
            'to' => $toPath,
        ])
            ->expectsOutputToContain('BREAKING CHANGES')
            ->expectsOutputToContain('[endpoint removed]')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_outputs_json_report_for_ci_usage(): void
    {
        $fromPath = $this->writeSpec('diff-json-from.json', $this->baselineSpec());
        $toPath = $this->writeSpec('diff-json-to.json', $this->nonBreakingSpec());

        $exitCode = Artisan::call('spectrum:diff', [
            'from' => $fromPath,
            'to' => $toPath,
            '--format' => 'json',
        ]);

        $output = Artisan::output();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame($fromPath, $report['from']);
        $this->assertSame($toPath, $report['to']);
        $this->assertSame(0, $report['summary']['breaking']);
        $this->assertGreaterThanOrEqual(1, $report['summary']['additions']);
    }

    #[Test]
    public function it_can_compare_against_last_snapshot(): void
    {
        $lastPath = storage_path('app/spectrum/openapi-last.json');
        $currentPath = storage_path('app/spectrum/openapi.json');

        $this->writeSpecAtPath($lastPath, $this->baselineSpec());
        $this->writeSpecAtPath($currentPath, $this->nonBreakingSpec());

        $this->artisan('spectrum:diff', [
            '--against' => 'last',
        ])
            ->expectsOutputToContain('API Diff')
            ->expectsOutputToContain('ADDITIONS')
            ->assertExitCode(0);

        $updatedSnapshot = File::get($lastPath);
        $currentSpec = File::get($currentPath);

        $this->assertSame($currentSpec, $updatedSnapshot);
    }

    #[Test]
    public function it_hides_non_breaking_sections_when_breaking_only_option_is_used(): void
    {
        $fromPath = $this->writeSpec('diff-breaking-only-from.json', $this->baselineSpec());
        $toPath = $this->writeSpec('diff-breaking-only-to.json', $this->breakingAndAdditionSpec());

        Artisan::call('spectrum:diff', [
            'from' => $fromPath,
            'to' => $toPath,
            '--breaking-only' => true,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('BREAKING CHANGES', $output);
        $this->assertStringNotContainsString('ADDITIONS', $output);
        $this->assertStringNotContainsString('DEPRECATIONS', $output);
    }

    #[Test]
    public function it_supports_version_compare_alias_with_migration_guide_output(): void
    {
        $fromPath = $this->writeSpec('version-compare-from.json', $this->versionOneSpec());
        $toPath = $this->writeSpec('version-compare-to.json', $this->versionTwoSpec());

        Artisan::call('spectrum:version-compare', [
            'from' => $fromPath,
            'to' => $toPath,
            '--migration-guide' => true,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('API Version Comparison', $output);
        $this->assertStringContainsString('ENDPOINT MAPPING', $output);
        $this->assertStringContainsString('GET /api/v1/users -> GET /api/v2/users', $output);
        $this->assertStringContainsString('GET /api/v1/legacy -> [no equivalent endpoint found]', $output);
        $this->assertStringContainsString('MIGRATION COVERAGE', $output);
        $this->assertStringContainsString('v1 endpoints with v2 equivalent: 1/2 (50.0%)', $output);
    }

    #[Test]
    public function it_includes_migration_guide_in_json_output(): void
    {
        $fromPath = $this->writeSpec('version-compare-json-from.json', $this->versionOneSpec());
        $toPath = $this->writeSpec('version-compare-json-to.json', $this->versionTwoSpec());

        Artisan::call('spectrum:version-compare', [
            'from' => $fromPath,
            'to' => $toPath,
            '--migration-guide' => true,
            '--format' => 'json',
        ]);

        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('migration_guide', $report);
        $this->assertSame(1, $report['migration_guide']['summary']['changed']);
        $this->assertSame(1, $report['migration_guide']['summary']['removed']);
        $this->assertSame(1, $report['migration_guide']['summary']['new_in_target']);
        $this->assertEquals(50.0, $report['migration_guide']['coverage']['percentage']);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function writeSpec(string $filename, array $spec): string
    {
        $path = storage_path('app/spectrum/'.$filename);
        $this->writeSpecAtPath($path, $spec);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function writeSpecAtPath(string $path, array $spec): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->createdPaths[] = $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function baselineSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Diff Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'timezone' => ['type' => 'string'],
                                            ],
                                            'required' => ['timezone'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/api/legacy' => [
                    'delete' => [
                        'responses' => [
                            '204' => ['description' => 'No Content'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function breakingSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Diff Test API',
                'version' => '1.1.0',
            ],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'timezone' => ['type' => 'integer'],
                                            ],
                                            'required' => ['timezone'],
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
    private function nonBreakingSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Diff Test API',
                'version' => '1.1.0',
            ],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'timezone' => ['type' => 'string'],
                                                'nickname' => ['type' => 'string'],
                                            ],
                                            'required' => ['timezone'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/api/legacy' => [
                    'delete' => [
                        'responses' => [
                            '204' => ['description' => 'No Content'],
                        ],
                    ],
                ],
                '/api/projects' => [
                    'post' => [
                        'responses' => [
                            '201' => ['description' => 'Created'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function breakingAndAdditionSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Diff Test API',
                'version' => '1.1.0',
            ],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'timezone' => ['type' => 'integer'],
                                                'nickname' => ['type' => 'string'],
                                            ],
                                            'required' => ['timezone'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/api/projects' => [
                    'post' => [
                        'responses' => [
                            '201' => ['description' => 'Created'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionOneSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Version Compare API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/v1/users' => [
                    'get' => [
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
                '/api/v1/legacy' => [
                    'get' => [
                        'responses' => [
                            '200' => ['description' => 'Legacy'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionTwoSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Version Compare API',
                'version' => '2.0.0',
            ],
            'paths' => [
                '/api/v2/users' => [
                    'get' => [
                        'responses' => [
                            '200' => ['description' => 'Success'],
                        ],
                    ],
                ],
                '/api/v2/reports' => [
                    'get' => [
                        'responses' => [
                            '200' => ['description' => 'Reports'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
