<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Diff;

use LaravelSpectrum\Diff\OpenApiDiffAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpenApiDiffAnalyzerTest extends TestCase
{
    #[Test]
    public function it_detects_endpoints_auth_deprecations_and_description_changes(): void
    {
        $fromSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => [
                '/api/legacy' => [
                    'delete' => [
                        'responses' => ['204' => ['description' => 'No Content']],
                    ],
                ],
                '/api/users' => [
                    'get' => [
                        'description' => 'List users',
                        'deprecated' => false,
                        'security' => ['invalid'],
                        'responses' => ['200' => ['description' => 'OK']],
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
                        'description' => 'Search users',
                        'deprecated' => true,
                        'security' => [['bearerAuth' => []]],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/api/projects' => [
                    'post' => [
                        'responses' => ['201' => ['description' => 'Created']],
                    ],
                ],
            ],
        ];

        $report = (new OpenApiDiffAnalyzer)->analyze($fromSpec, $toSpec);

        $this->assertSame(
            ['endpoint_removed', 'auth_requirement_changed'],
            $this->types($report['breaking_changes'])
        );
        $this->assertSame(
            ['endpoint_newly_deprecated'],
            $this->types($report['deprecations'])
        );
        $this->assertSame(
            ['endpoint_added', 'description_changed'],
            $this->types($report['additions'])
        );
        $this->assertSame(
            ['breaking' => 2, 'deprecations' => 1, 'additions' => 2],
            $report['summary']
        );

        $this->assertContains('DELETE /api/legacy  [endpoint removed]', $this->messages($report['breaking_changes']));
        $this->assertContains('GET /api/users: authentication became required', $this->messages($report['breaking_changes']));
        $this->assertContains('GET /api/users  [newly deprecated]', $this->messages($report['deprecations']));
        $this->assertContains('POST /api/projects  [new endpoint]', $this->messages($report['additions']));
        $this->assertContains('GET /api/users: description changed', $this->messages($report['additions']));
    }

    #[Test]
    public function it_normalizes_and_compares_parameters_from_path_item_and_operation(): void
    {
        $fromSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => [
                '/api/projects/{id}' => [
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'schema' => ['type' => ' string '],
                        ],
                    ],
                    'get' => [
                        'parameters' => [
                            [
                                'name' => 'mode',
                                'in' => 'query',
                                'required' => false,
                                'schema' => [
                                    'type' => 'string',
                                    'enum' => ['simple', true],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ];

        $toSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.1.0'],
            'paths' => [
                '/api/projects/{id}' => [
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'schema' => ['type' => 'string'],
                        ],
                        [
                            'name' => 'tenantId',
                            'in' => 'path',
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                    'get' => [
                        'parameters' => [
                            [
                                'name' => 'mode',
                                'in' => 'query',
                                'required' => true,
                                'schema' => [
                                    'type' => 'integer',
                                    'enum' => ['simple', false],
                                ],
                            ],
                            [
                                'name' => 'filter',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string'],
                            ],
                            [
                                'name' => '   ',
                                'in' => 'query',
                                'schema' => ['type' => 'string'],
                            ],
                            [
                                'name' => 'ignored',
                                'in' => ' ',
                                'schema' => ['type' => 'string'],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ];

        $report = (new OpenApiDiffAnalyzer)->analyze($fromSpec, $toSpec);
        $breakingMessages = $this->messages($report['breaking_changes']);
        $additionMessages = $this->messages($report['additions']);

        $this->assertContains('required_parameter_added', $this->types($report['breaking_changes']));
        $this->assertContains('parameter_became_required', $this->types($report['breaking_changes']));
        $this->assertContains('parameter_type_changed', $this->types($report['breaking_changes']));
        $this->assertContains('enum_value_removed', $this->types($report['breaking_changes']));
        $this->assertContains('optional_parameter_added', $this->types($report['additions']));
        $this->assertContains('enum_value_added', $this->types($report['additions']));

        $this->assertContains(
            "GET /api/projects/{id}: required parameter 'tenantId (path)' added",
            $breakingMessages
        );
        $this->assertContains(
            "GET /api/projects/{id}: parameter 'mode (query)' became required",
            $breakingMessages
        );
        $this->assertContains(
            "GET /api/projects/{id}: parameter 'mode (query)' type changed: string -> integer",
            $breakingMessages
        );
        $this->assertContains(
            "GET /api/projects/{id}: optional parameter 'filter (query)' added",
            $additionMessages
        );
        $this->assertNotContains(
            "GET /api/projects/{id}: optional parameter 'ignored ( )' added",
            $additionMessages
        );
    }

    #[Test]
    public function it_detects_request_body_changes_with_schema_comparisons(): void
    {
        $fromSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => [
                '/api/accounts' => [
                    'post' => [
                        'responses' => ['201' => ['description' => 'Created']],
                    ],
                ],
                '/api/profile' => [
                    'put' => [
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'timezone' => ['type' => 'string'],
                                            'role' => ['type' => 'string', 'enum' => ['free', 'pro']],
                                        ],
                                        'required' => ['timezone'],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ];

        $toSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.1.0'],
            'paths' => [
                '/api/accounts' => [
                    'post' => [
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/*+json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['201' => ['description' => 'Created']],
                    ],
                ],
                '/api/profile' => [
                    'put' => [
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'timezone' => ['type' => 'integer'],
                                            'role' => ['type' => 'string', 'enum' => ['free', 'enterprise']],
                                            'theme' => ['type' => 'string'],
                                            'locale' => ['type' => 'string'],
                                        ],
                                        'required' => ['timezone', 'role', 'locale'],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ];

        $report = (new OpenApiDiffAnalyzer)->analyze($fromSpec, $toSpec);
        $breakingTypes = $this->types($report['breaking_changes']);
        $additionTypes = $this->types($report['additions']);

        $this->assertContains('required_request_body_added', $breakingTypes);
        $this->assertContains('field_type_changed', $breakingTypes);
        $this->assertContains('required_request_field_added', $breakingTypes);
        $this->assertContains('request_field_became_required', $breakingTypes);
        $this->assertContains('enum_value_removed', $breakingTypes);
        $this->assertContains('optional_request_field_added', $additionTypes);
        $this->assertContains('enum_value_added', $additionTypes);
    }

    #[Test]
    public function it_detects_response_changes_and_skips_null_schema_statuses(): void
    {
        $fromSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => [
                '/api/items' => [
                    'get' => [
                        'responses' => [
                            '200' => ['description' => 'No schema'],
                            '201' => [
                                'description' => 'Created',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'data' => ['type' => 'string'],
                                                'removed_me' => ['type' => 'string'],
                                            ],
                                            'required' => ['data', 'removed_me'],
                                        ],
                                    ],
                                ],
                            ],
                            '400' => [
                                'description' => 'Bad Request',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object'],
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
                '/api/items' => [
                    'get' => [
                        'responses' => [
                            '200' => ['description' => 'No schema'],
                            '201' => [
                                'description' => 'Created',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'data' => ['type' => 'integer'],
                                                'added_one' => ['type' => 'boolean'],
                                            ],
                                            'required' => ['data'],
                                        ],
                                    ],
                                ],
                            ],
                            '500' => [
                                'description' => 'Server Error',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['type' => 'object'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $report = (new OpenApiDiffAnalyzer)->analyze($fromSpec, $toSpec);

        $this->assertContains('response_removed', $this->types($report['breaking_changes']));
        $this->assertContains('field_type_changed', $this->types($report['breaking_changes']));
        $this->assertContains('response_field_removed', $this->types($report['breaking_changes']));
        $this->assertContains('error_response_added', $this->types($report['additions']));
        $this->assertContains('response_field_added', $this->types($report['additions']));
        $this->assertContains(
            "GET /api/items: response 201 field 'data' type changed: string -> integer",
            $this->messages($report['breaking_changes'])
        );
    }

    #[Test]
    public function it_ignores_invalid_path_entries_and_does_not_create_false_endpoint_diffs(): void
    {
        $fromSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => [
                0 => ['get' => ['responses' => ['200' => ['description' => 'ignored']]]],
                '/api/health' => [
                    'get' => ['responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ];

        $toSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.1'],
            'paths' => [
                '/api/health' => [
                    'get' => ['responses' => ['200' => ['description' => 'OK']]],
                ],
            ],
        ];

        $report = (new OpenApiDiffAnalyzer)->analyze($fromSpec, $toSpec);

        $this->assertSame([], $report['breaking_changes']);
        $this->assertSame([], $report['deprecations']);
        $this->assertSame([], $report['additions']);
        $this->assertSame(['breaking' => 0, 'deprecations' => 0, 'additions' => 0], $report['summary']);
    }

    #[Test]
    public function it_treats_trimmed_descriptions_as_equal_and_detects_actual_changes(): void
    {
        $baseSpec = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'API', 'version' => '1.0.0'],
            'paths' => [
                '/api/users' => [
                    'get' => [
                        'description' => 'List users',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ];

        $trimmedSpec = $baseSpec;
        $trimmedSpec['paths']['/api/users']['get']['description'] = '  List users  ';

        $changedSpec = $baseSpec;
        $changedSpec['paths']['/api/users']['get']['description'] = 'Search users';

        $trimmedReport = (new OpenApiDiffAnalyzer)->analyze($baseSpec, $trimmedSpec);
        $changedReport = (new OpenApiDiffAnalyzer)->analyze($baseSpec, $changedSpec);

        $this->assertSame([], $trimmedReport['additions']);
        $this->assertSame(['description_changed'], $this->types($changedReport['additions']));
        $this->assertContains('GET /api/users: description changed', $this->messages($changedReport['additions']));
    }

    /**
     * @param  array<int, array{type: string, operation: string, message: string}>  $findings
     * @return array<int, string>
     */
    private function types(array $findings): array
    {
        return array_values(array_map(static fn (array $finding): string => $finding['type'], $findings));
    }

    /**
     * @param  array<int, array{type: string, operation: string, message: string}>  $findings
     * @return array<int, string>
     */
    private function messages(array $findings): array
    {
        return array_values(array_map(static fn (array $finding): string => $finding['message'], $findings));
    }
}
