<?php

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\Contracts\HasCustomExamples;
use LaravelSpectrum\Contracts\HasExamples;
use LaravelSpectrum\DTO\ResourceInfo;

/**
 * Generates example values for OpenAPI documentation.
 *
 * @phpstan-type OpenApiSchemaType array{
 *     type?: string,
 *     format?: string,
 *     properties?: array<string, array<string, mixed>>,
 *     items?: array<string, mixed>,
 *     enum?: array<int, mixed>,
 *     nullable?: bool,
 *     example?: mixed,
 *     default?: mixed
 * }
 * @phpstan-type TransformerSchema array{default?: OpenApiSchemaType}
 * @phpstan-type ValidationRules array<string, string|array<int, mixed>>
 * @phpstan-type ValidationErrorExample array{message: string, errors: array<string, array<int, string>>}
 * @phpstan-type PaginatedCollectionExample array{data: array<int, array<string, mixed>>, links: array<string, string|null>, meta: array<string, int|string>}
 * @phpstan-type ErrorExample array{message: string, errors?: array<string, array<int, string>>}
 */
class ExampleGenerator
{
    public function __construct(
        private ExampleValueFactory $valueFactory
    ) {}

    /**
     * Generate example from a resource.
     *
     * @param  class-string  $resourceClass
     * @return array<string, mixed>
     */
    public function generateFromResource(ResourceInfo $resourceInfo, string $resourceClass): array
    {
        // Use custom example from ResourceInfo if available
        if ($resourceInfo->hasCustomExample()) {
            return $resourceInfo->customExample;
        }

        // Check if the resource implements HasExamples interface (existing)
        if (is_subclass_of($resourceClass, HasExamples::class)) {
            try {
                $resource = new $resourceClass(null);

                return $resource->getExample();
            } catch (\Exception $e) {
                // Fall through to schema-based generation if getExample() fails
            }
        }

        // Check if the resource implements HasCustomExamples interface (new)
        $customMapping = [];
        if (is_subclass_of($resourceClass, HasCustomExamples::class)) {
            try {
                $customMapping = $resourceClass::getExampleMapping();
            } catch (\Exception $e) {
                // Continue with empty custom mapping if getExampleMapping() fails
            }
        }

        // Generate example from properties with custom mappings
        $schema = ['properties' => $resourceInfo->properties];

        return $this->generateFromSchema($schema, $customMapping);
    }

    /**
     * Generate example from a transformer schema.
     *
     * @param  TransformerSchema  $transformerSchema
     * @return array<string, mixed>
     */
    public function generateFromTransformer(array $transformerSchema): array
    {
        // Generate example from default transformer fields
        if (isset($transformerSchema['default'])) {
            return $this->generateFromSchema($transformerSchema['default']);
        }

        return [];
    }

    /**
     * Generate a collection example from an item example.
     *
     * @param  array<string, mixed>  $itemExample
     * @return array<int|string, mixed>
     */
    public function generateCollectionExample(array $itemExample, bool $isPaginated = false): array
    {
        if ($isPaginated) {
            return $this->generatePaginatedCollection($itemExample);
        }

        // Generate simple array with 3 items
        $collection = [];
        for ($i = 1; $i <= 3; $i++) {
            $item = $itemExample;
            if (isset($item['id'])) {
                $item['id'] = $i;
            }
            $collection[] = $item;
        }

        return $collection;
    }

    /**
     * Generate an error example based on status code.
     *
     * @param  ValidationRules  $validationRules
     * @return ErrorExample
     */
    public function generateErrorExample(int $statusCode, array $validationRules = []): array
    {
        return match ($statusCode) {
            422 => $this->generateValidationErrorExample($validationRules),
            404 => ['message' => 'Not found'],
            401 => ['message' => 'Unauthenticated.'],
            403 => ['message' => 'Forbidden.'],
            500 => ['message' => 'Server Error'],
            default => ['message' => 'Error occurred'],
        };
    }

    /**
     * Generate from schema with custom field mappings.
     *
     * @param  OpenApiSchemaType  $schema
     * @param  array<string, callable>  $customMapping
     * @return array<string, mixed>
     */
    private function generateFromSchema(array $schema, array $customMapping = []): array
    {
        $example = [];

        if (! isset($schema['properties'])) {
            return $example;
        }

        foreach ($schema['properties'] as $fieldName => $fieldSchema) {
            $customGenerator = $customMapping[$fieldName] ?? null;
            $example[$fieldName] = $this->generateFieldValue($fieldName, $fieldSchema, $customGenerator);
        }

        return $example;
    }

    /**
     * Generate field value with custom generator support.
     *
     * @param  OpenApiSchemaType  $fieldSchema
     */
    private function generateFieldValue(string $fieldName, array $fieldSchema, ?callable $customGenerator = null): mixed
    {
        // Check if nullable and field looks like it should be null
        if (isset($fieldSchema['nullable']) && $fieldSchema['nullable'] && str_ends_with($fieldName, '_at') && str_contains($fieldName, 'deleted')) {
            return null;
        }

        // Handle enum values
        if (isset($fieldSchema['enum']) && is_array($fieldSchema['enum']) && count($fieldSchema['enum']) > 0) {
            return $fieldSchema['enum'][0];
        }

        // Handle nested objects
        if ($fieldSchema['type'] === 'object' && isset($fieldSchema['properties'])) {
            return $this->generateFromSchema($fieldSchema);
        }

        // Handle arrays
        if ($fieldSchema['type'] === 'array' && isset($fieldSchema['items'])) {
            if ($fieldSchema['items']['type'] === 'object' && isset($fieldSchema['items']['properties'])) {
                $itemExample = $this->generateFromSchema($fieldSchema['items']);

                return $this->generateCollectionExample($itemExample, false);
            }

            return [];
        }

        // Use value factory with custom generator
        return $this->valueFactory->create($fieldName, $fieldSchema, $customGenerator);
    }

    /**
     * Generate a paginated collection example.
     *
     * @param  array<string, mixed>  $itemExample
     * @return PaginatedCollectionExample
     */
    private function generatePaginatedCollection(array $itemExample): array
    {
        return [
            'data' => $this->generateCollectionExample($itemExample, false),
            'links' => [
                'first' => 'https://api.example.com/items?page=1',
                'last' => 'https://api.example.com/items?page=10',
                'prev' => null,
                'next' => 'https://api.example.com/items?page=2',
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 10,
                'path' => 'https://api.example.com/items',
                'per_page' => 15,
                'to' => 15,
                'total' => 150,
            ],
        ];
    }

    /**
     * @param  ValidationRules  $validationRules
     * @return ValidationErrorExample
     */
    private function generateValidationErrorExample(array $validationRules): array
    {
        $errors = [];

        foreach ($validationRules as $field => $rules) {
            $rulesList = is_string($rules) ? explode('|', $rules) : $rules;
            $errorMessages = [];

            foreach ($rulesList as $rule) {
                if (is_string($rule)) {
                    $ruleName = explode(':', $rule)[0];
                    $errorMessages[] = $this->getValidationErrorMessage($field, $ruleName);
                }
            }

            if (! empty($errorMessages)) {
                $errors[$field] = array_slice($errorMessages, 0, 2); // Limit to 2 error messages per field
            }
        }

        return [
            'message' => 'The given data was invalid.',
            'errors' => $errors,
        ];
    }

    private function getValidationErrorMessage(string $field, string $rule): string
    {
        $fieldLabel = str_replace('_', ' ', $field);

        return match ($rule) {
            'required' => "The {$fieldLabel} field is required.",
            'email' => "The {$fieldLabel} must be a valid email address.",
            'string' => "The {$fieldLabel} must be a string.",
            'numeric' => "The {$fieldLabel} must be a number.",
            'integer' => "The {$fieldLabel} must be an integer.",
            'max' => "The {$fieldLabel} may not be greater than :max.",
            'min' => "The {$fieldLabel} must be at least :min.",
            'unique' => "The {$fieldLabel} has already been taken.",
            'confirmed' => "The {$fieldLabel} confirmation does not match.",
            'date' => "The {$fieldLabel} is not a valid date.",
            'url' => "The {$fieldLabel} format is invalid.",
            default => "The {$fieldLabel} is invalid.",
        };
    }
}
