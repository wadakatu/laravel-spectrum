<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Documentation;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3).'/demo-app/tools/OpenApiRequirementValidator.php';

class OpenApiRequirementValidatorTest extends TestCase
{
    #[Test]
    public function it_passes_minimal_openapi_3_0_document(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Demo API',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => 'https://api.example.com'],
            ],
            'paths' => [
                '/ping' => [
                    'get' => [
                        'operationId' => 'ping',
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $report = $this->validator()->validate(
            spec: $spec,
            rawJson: json_encode($spec, JSON_THROW_ON_ERROR),
            expectedVersion: '3.0.0',
            schemaValid: true,
            schemaErrors: []
        );

        $this->assertSame(0, $report['summary']['failed']);
    }

    #[Test]
    public function it_passes_minimal_openapi_3_1_document(): void
    {
        $spec = [
            'openapi' => '3.1.0',
            'jsonSchemaDialect' => 'https://json-schema.org/draft/2020-12/schema',
            'info' => [
                'title' => 'Demo API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/ping' => [
                    'get' => [
                        'operationId' => 'ping',
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                            ],
                        ],
                    ],
                ],
            ],
            'webhooks' => [],
        ];

        $report = $this->validator()->validate(
            spec: $spec,
            rawJson: json_encode($spec, JSON_THROW_ON_ERROR),
            expectedVersion: '3.1.0',
            schemaValid: true,
            schemaErrors: []
        );

        $this->assertSame(0, $report['summary']['failed']);
    }

    #[Test]
    public function it_detects_nullable_keyword_in_openapi_3_1(): void
    {
        $spec = [
            'openapi' => '3.1.0',
            'jsonSchemaDialect' => 'https://json-schema.org/draft/2020-12/schema',
            'info' => [
                'title' => 'Demo API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/ping' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'value' => [
                                                    'type' => 'string',
                                                    'nullable' => true,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'webhooks' => [],
        ];

        $rawJson = json_encode($spec, JSON_THROW_ON_ERROR);

        $report = $this->validator()->validate(
            spec: $spec,
            rawJson: $rawJson,
            expectedVersion: '3.1.0',
            schemaValid: true,
            schemaErrors: []
        );

        $this->assertGreaterThan(0, $report['summary']['failed']);
        $this->assertStringContainsString('OAS31-002', implode("\n", $report['failures']));
    }

    #[Test]
    public function it_detects_missing_templated_path_parameter_declaration(): void
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Demo API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/users/{user}' => [
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

        $report = $this->validator()->validate(
            spec: $spec,
            rawJson: json_encode($spec, JSON_THROW_ON_ERROR),
            expectedVersion: '3.0.0',
            schemaValid: true,
            schemaErrors: []
        );

        $this->assertGreaterThan(0, $report['summary']['failed']);
        $this->assertStringContainsString('OAS-010', implode("\n", $report['failures']));
    }

    #[Test]
    public function it_exposes_requirement_matrix_definitions(): void
    {
        $definitions = $this->validator()->requirementDefinitions();
        $ids = array_column($definitions, 'id');

        $this->assertGreaterThanOrEqual(20, count($definitions));
        $this->assertCount(count(array_unique($ids)), $ids);
    }

    private function validator(): \OpenApiRequirementValidator
    {
        return new \OpenApiRequirementValidator;
    }
}
