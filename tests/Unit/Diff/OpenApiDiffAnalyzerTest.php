<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Diff;

use LaravelSpectrum\Diff\OpenApiDiffAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiDiffAnalyzerTest extends TestCase
{
    #[Test]
    public function it_detects_endpoint_additions_removals_and_new_deprecations(): void
    {
        $fromSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => [
                '/api/legacy' => [
                    'delete' => [
                        'responses' => [
                            '204' => ['description' => 'No Content'],
                        ],
                    ],
                ],
                '/api/users' => [
                    'get' => [
                        'deprecated' => false,
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ];

        $toSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.1.0'],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'deprecated' => true,
                        'responses' => [
                            '200' => ['description' => 'OK'],
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

        $report = (new OpenApiDiffAnalyzer)->analyze($fromSpec, $toSpec);

        $this->assertContains('endpoint_removed', array_column($report['breaking_changes'], 'type'));
        $this->assertContains('endpoint_added', array_column($report['additions'], 'type'));
        $this->assertContains('endpoint_newly_deprecated', array_column($report['deprecations'], 'type'));
    }

    #[Test]
    public function it_detects_field_type_changes_required_request_fields_and_enum_changes(): void
    {
        $fromSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => [
                '/api/account' => [
                    'put' => [
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'timezone' => ['type' => 'string'],
                                            'theme' => ['type' => 'string'],
                                        ],
                                        'required' => ['timezone'],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'timezone' => ['type' => 'string'],
                                                'plan' => ['type' => 'string', 'enum' => ['free', 'pro']],
                                            ],
                                            'required' => ['timezone', 'plan'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $toSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.1.0'],
            'paths' => [
                '/api/account' => [
                    'put' => [
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'timezone' => ['type' => 'integer'],
                                            'theme' => ['type' => 'string'],
                                        ],
                                        'required' => ['timezone', 'theme'],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'timezone' => ['type' => 'integer'],
                                                'plan' => ['type' => 'string', 'enum' => ['free', 'enterprise']],
                                                'ai_enabled' => ['type' => 'boolean'],
                                            ],
                                            'required' => ['timezone', 'plan'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $report = (new OpenApiDiffAnalyzer)->analyze($fromSpec, $toSpec);
        $breakingTypes = array_column($report['breaking_changes'], 'type');
        $additionTypes = array_column($report['additions'], 'type');

        $this->assertContains('field_type_changed', $breakingTypes);
        $this->assertContains('request_field_became_required', $breakingTypes);
        $this->assertContains('enum_value_removed', $breakingTypes);
        $this->assertContains('enum_value_added', $additionTypes);
        $this->assertContains('response_field_added', $additionTypes);
    }

    #[Test]
    public function it_detects_authentication_and_parameter_breaking_changes(): void
    {
        $fromSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => [
                '/api/projects' => [
                    'get' => [
                        'security' => [],
                        'parameters' => [
                            [
                                'name' => 'mode',
                                'in' => 'query',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string',
                                    'enum' => ['simple'],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ];

        $toSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.1.0'],
            'paths' => [
                '/api/projects' => [
                    'get' => [
                        'security' => [
                            ['bearerAuth' => []],
                        ],
                        'parameters' => [
                            [
                                'name' => 'mode',
                                'in' => 'query',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string',
                                    'enum' => ['simple', 'advanced'],
                                ],
                            ],
                            [
                                'name' => 'limit',
                                'in' => 'query',
                                'required' => true,
                                'schema' => [
                                    'type' => 'integer',
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'OK'],
                        ],
                    ],
                ],
            ],
        ];

        $report = (new OpenApiDiffAnalyzer)->analyze($fromSpec, $toSpec);

        $this->assertContains('auth_requirement_changed', array_column($report['breaking_changes'], 'type'));
        $this->assertContains('required_parameter_added', array_column($report['breaking_changes'], 'type'));
        $this->assertContains('enum_value_added', array_column($report['additions'], 'type'));
    }
}
