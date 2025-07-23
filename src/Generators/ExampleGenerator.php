<?php

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\Contracts\HasExamples;

class ExampleGenerator
{
    public function __construct(
        private ExampleValueFactory $valueFactory
    ) {}

    public function generateFromResource(array $resourceSchema, string $resourceClass): array
    {
        // Check if the resource implements HasExamples interface
        if (is_subclass_of($resourceClass, HasExamples::class)) {
            $resource = new $resourceClass(null);

            return $resource->getExample();
        }

        // If resourceSchema doesn't have 'properties' key, it's likely a flat structure
        // from legacy ResourceAnalyzer format - wrap it in properties
        if (! isset($resourceSchema['properties']) && ! empty($resourceSchema)) {
            // Check if it looks like a flat resource structure by looking for common fields
            $hasResourceFields = false;
            foreach ($resourceSchema as $key => $value) {
                if (is_array($value) && isset($value['type'])) {
                    $hasResourceFields = true;
                    break;
                }
            }

            if ($hasResourceFields) {
                $resourceSchema = ['properties' => $resourceSchema];
            }
        }

        // Generate example from schema
        return $this->generateFromSchema($resourceSchema);
    }

    public function generateFromTransformer(array $transformerSchema): array
    {
        // Generate example from default transformer fields
        if (isset($transformerSchema['default'])) {
            return $this->generateFromSchema($transformerSchema['default']);
        }

        return [];
    }

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

    private function generateFromSchema(array $schema): array
    {
        $example = [];

        if (! isset($schema['properties'])) {
            return $example;
        }

        foreach ($schema['properties'] as $fieldName => $fieldSchema) {
            $example[$fieldName] = $this->generateFieldValue($fieldName, $fieldSchema);
        }

        return $example;
    }

    private function generateFieldValue(string $fieldName, array $fieldSchema): mixed
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

        // Use value factory for simple types
        return $this->valueFactory->create($fieldName, $fieldSchema);
    }

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
