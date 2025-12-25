<?php

namespace LaravelSpectrum\MockServer;

class ValidationSimulator
{
    public function validate(
        array $operation,
        ?array $requestBody,
        array $queryParams,
        array $pathParams
    ): array {
        $errors = [];

        // リクエストボディのバリデーション
        if (isset($operation['requestBody']) && isset($operation['requestBody']['required']) && $operation['requestBody']['required']) {
            if (empty($requestBody)) {
                $errors['_body'] = ['The request body is required.'];
            } else {
                $bodyErrors = $this->validateRequestBody($operation['requestBody'], $requestBody);
                $errors = array_merge($errors, $bodyErrors);
            }
        } elseif (isset($operation['requestBody']) && ! empty($requestBody)) {
            // Optional request body but provided
            $bodyErrors = $this->validateRequestBody($operation['requestBody'], $requestBody);
            $errors = array_merge($errors, $bodyErrors);
        }

        // パラメータのバリデーション
        if (isset($operation['parameters'])) {
            foreach ($operation['parameters'] as $parameter) {
                $paramErrors = $this->validateParameter($parameter, $queryParams, $pathParams);
                if (! empty($paramErrors)) {
                    $errors = array_merge($errors, $paramErrors);
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function validateRequestBody(array $requestBodySpec, array $data): array
    {
        $errors = [];

        if (! isset($requestBodySpec['content']['application/json']['schema'])) {
            return $errors;
        }

        $schema = $requestBodySpec['content']['application/json']['schema'];

        // 必須フィールドのチェック
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (! isset($data[$field])) {
                    $errors[$field][] = "The {$field} field is required.";
                }
            }
        }

        // プロパティのバリデーション
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $field => $spec) {
                if (isset($data[$field])) {
                    $fieldErrors = $this->validateField($field, $data[$field], $spec);
                    if (! empty($fieldErrors)) {
                        $errors[$field] = $fieldErrors;
                    }
                }
            }
        }

        return $errors;
    }

    private function validateField(string $field, string|int|float|bool|array|null $value, array $spec): array
    {
        $errors = [];

        // 型チェック
        // Note: JSON objects are decoded as PHP arrays (json_decode with associative=true),
        // so 'object' type validation checks for arrays
        if (isset($spec['type'])) {
            $valid = match ($spec['type']) {
                'string' => is_string($value),
                'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
                'number' => is_numeric($value),
                'boolean' => is_bool($value) || in_array($value, ['true', 'false', '0', '1']),
                'array' => is_array($value),
                'object' => is_array($value),
                default => true,
            };

            if (! $valid) {
                $errors[] = "The {$field} must be a {$spec['type']}.";
            }
        }

        // 文字列の制約
        if ($spec['type'] === 'string' && is_string($value)) {
            if (isset($spec['minLength']) && strlen($value) < $spec['minLength']) {
                $errors[] = "The {$field} must be at least {$spec['minLength']} characters.";
            }

            if (isset($spec['maxLength']) && strlen($value) > $spec['maxLength']) {
                $errors[] = "The {$field} may not be greater than {$spec['maxLength']} characters.";
            }

            if (isset($spec['pattern']) && ! preg_match('/'.$spec['pattern'].'/', $value)) {
                $errors[] = "The {$field} format is invalid.";
            }

            if (isset($spec['format'])) {
                $formatValid = match ($spec['format']) {
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'uri' => filter_var($value, FILTER_VALIDATE_URL) !== false,
                    'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value),
                    'date' => strtotime($value) !== false,
                    'date-time' => strtotime($value) !== false,
                    default => true,
                };

                if (! $formatValid) {
                    $errors[] = "The {$field} must be a valid {$spec['format']}.";
                }
            }
        }

        // 数値の制約
        if (in_array($spec['type'], ['integer', 'number']) && is_numeric($value)) {
            if (isset($spec['minimum']) && $value < $spec['minimum']) {
                $errors[] = "The {$field} must be at least {$spec['minimum']}.";
            }

            if (isset($spec['maximum']) && $value > $spec['maximum']) {
                $errors[] = "The {$field} may not be greater than {$spec['maximum']}.";
            }
        }

        // Enum制約
        if (isset($spec['enum']) && ! in_array($value, $spec['enum'])) {
            $errors[] = "The selected {$field} is invalid. Valid options are: ".implode(', ', $spec['enum']);
        }

        return $errors;
    }

    private function validateParameter(array $parameter, array $queryParams, array $pathParams): array
    {
        $errors = [];
        $name = $parameter['name'];
        $value = null;

        // パラメータの値を取得
        if ($parameter['in'] === 'query') {
            $value = $queryParams[$name] ?? null;
        } elseif ($parameter['in'] === 'path') {
            $value = $pathParams[$name] ?? null;
        }

        // 必須チェック
        if ($parameter['required'] ?? false) {
            if ($value === null || $value === '') {
                $errors[$name][] = "The {$name} parameter is required.";

                return $errors;
            }
        }

        // 値が存在する場合のバリデーション
        if ($value !== null && isset($parameter['schema'])) {
            $fieldErrors = $this->validateField($name, $value, $parameter['schema']);
            if (! empty($fieldErrors)) {
                $errors[$name] = $fieldErrors;
            }
        }

        return $errors;
    }
}
