<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

use LaravelSpectrum\DTO\TypeInfo;
use PhpParser\Node;

/**
 * Unified AST-based type inference engine.
 *
 * Consolidates type inference logic from AST nodes, providing consistent
 * type detection across all analyzers (Response, Collection, Fractal, etc.).
 */
final class AstTypeInferenceEngine
{
    private FieldNameInference $fieldNameInference;

    public function __construct(?FieldNameInference $fieldNameInference = null)
    {
        $this->fieldNameInference = $fieldNameInference ?? new FieldNameInference;
    }

    /**
     * Infer type information from an AST node as a TypeInfo DTO.
     *
     * Returns a TypeInfo with OpenAPI-compatible type information:
     * - type: The OpenAPI type (string, integer, number, boolean, array, object, null)
     * - properties: For objects, a map of property names to their TypeInfo (recursive structure)
     * - format: Optional format hint (date-time, email, uri, uuid, etc.)
     *
     * @param  Node  $node  The AST node to analyze
     */
    public function infer(Node $node): TypeInfo
    {
        // Scalar types
        if ($node instanceof Node\Scalar\String_) {
            return TypeInfo::string();
        }

        if ($node instanceof Node\Scalar\Int_) {
            return TypeInfo::integer();
        }

        if ($node instanceof Node\Scalar\Float_) {
            return TypeInfo::number();
        }

        // Cast expressions
        if ($node instanceof Node\Expr\Cast\Int_) {
            return TypeInfo::integer();
        }

        if ($node instanceof Node\Expr\Cast\String_) {
            return TypeInfo::string();
        }

        if ($node instanceof Node\Expr\Cast\Bool_) {
            return TypeInfo::boolean();
        }

        if ($node instanceof Node\Expr\Cast\Double) {
            return TypeInfo::number();
        }

        if ($node instanceof Node\Expr\Cast\Array_) {
            return TypeInfo::array();
        }

        // Boolean/null constants
        if ($node instanceof Node\Expr\ConstFetch) {
            return $this->inferFromConstFetchToDto($node);
        }

        // Arrays
        if ($node instanceof Node\Expr\Array_) {
            return $this->inferFromArrayToDto($node);
        }

        // Property access
        if ($node instanceof Node\Expr\PropertyFetch) {
            return $this->inferFromPropertyFetchToDto($node);
        }

        // Method calls
        if ($node instanceof Node\Expr\MethodCall) {
            return $this->inferFromMethodCallToDto($node);
        }

        // Function calls
        if ($node instanceof Node\Expr\FuncCall) {
            return $this->inferFromFuncCallToDto($node);
        }

        // Ternary expressions
        if ($node instanceof Node\Expr\Ternary) {
            return $this->inferFromTernaryToDto($node);
        }

        // Null coalesce
        if ($node instanceof Node\Expr\BinaryOp\Coalesce) {
            return $this->infer($node->left);
        }

        // Default to string
        return TypeInfo::string();
    }

    /**
     * Infer type information from an AST node (backward compatible).
     *
     * Returns an array with OpenAPI-compatible type information:
     * - 'type': The OpenAPI type (string, integer, number, boolean, array, object, null)
     * - 'properties': For objects, a map of property names to their type arrays (recursive structure)
     * - 'format': Optional format hint (date-time, email, uri, uuid, etc.)
     *
     * @param  Node  $node  The AST node to analyze
     * @return array{type: string, properties?: array<string, array<string, mixed>>, format?: string}
     */
    public function inferFromNode(Node $node): array
    {
        return $this->infer($node)->toArray();
    }

    /**
     * Get just the type string from an AST node.
     *
     * @param  Node  $node  The AST node to analyze
     */
    public function inferTypeString(Node $node): string
    {
        return $this->infer($node)->type;
    }

    /**
     * Infer type from a constant fetch (true, false, null) - returns TypeInfo.
     */
    private function inferFromConstFetchToDto(Node\Expr\ConstFetch $node): TypeInfo
    {
        $name = $node->name->toLowerString();

        if ($name === 'true' || $name === 'false') {
            return TypeInfo::boolean();
        }

        if ($name === 'null') {
            return TypeInfo::null();
        }

        return TypeInfo::string();
    }

    /**
     * Infer type from an array expression - returns TypeInfo.
     */
    private function inferFromArrayToDto(Node\Expr\Array_ $node): TypeInfo
    {
        // Check if it's an associative array (object) or sequential array
        $hasStringKeys = false;
        $properties = [];

        foreach ($node->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->key instanceof Node\Scalar\String_) {
                $hasStringKeys = true;
                $key = $item->key->value;
                $properties[$key] = $this->infer($item->value);
            } elseif ($item->key instanceof Node\Scalar\Int_) {
                // Numeric key - could still be associative
                $key = (string) $item->key->value;
                $properties[$key] = $this->infer($item->value);
            }
        }

        if ($hasStringKeys && ! empty($properties)) {
            return TypeInfo::object($properties);
        }

        return TypeInfo::array();
    }

    /**
     * Infer type from a property fetch expression - returns TypeInfo.
     */
    private function inferFromPropertyFetchToDto(Node\Expr\PropertyFetch $node): TypeInfo
    {
        if (! $node->name instanceof Node\Identifier) {
            return TypeInfo::string();
        }

        $propertyName = $node->name->toString();

        // Use field name inference for common patterns
        $inference = $this->fieldNameInference->inferFieldType($propertyName);

        return $this->convertFieldInferenceToTypeInfo($inference);
    }

    /**
     * Infer type from a method call expression - returns TypeInfo.
     */
    private function inferFromMethodCallToDto(Node\Expr\MethodCall $node): TypeInfo
    {
        if (! $node->name instanceof Node\Identifier) {
            return TypeInfo::string();
        }

        $methodName = $node->name->toString();

        // Special handling for only() method
        if ($methodName === 'only') {
            return $this->inferFromOnlyMethodToDto($node);
        }

        // Date/time methods
        if (in_array($methodName, ['toIso8601String', 'toString', 'toDateTimeString', 'toDateString', 'toTimeString', 'format'], true)) {
            return TypeInfo::stringWithFormat('date-time');
        }

        // Array conversion methods
        if (in_array($methodName, ['toArray', 'all', 'values', 'keys'], true)) {
            return TypeInfo::array();
        }

        // Count methods
        if (in_array($methodName, ['count', 'sum', 'avg', 'average', 'max', 'min'], true)) {
            return TypeInfo::integer();
        }

        // Boolean methods
        if (str_starts_with($methodName, 'is') || str_starts_with($methodName, 'has') || str_starts_with($methodName, 'can')) {
            return TypeInfo::boolean();
        }

        return TypeInfo::string();
    }

    /**
     * Infer type from only() method call - returns TypeInfo.
     */
    private function inferFromOnlyMethodToDto(Node\Expr\MethodCall $node): TypeInfo
    {
        $properties = [];

        if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Expr\Array_) {
            foreach ($node->args[0]->value->items as $item) {
                if ($item && $item->value instanceof Node\Scalar\String_) {
                    $fieldName = $item->value->value;
                    $inference = $this->fieldNameInference->inferFieldType($fieldName);
                    $properties[$fieldName] = $this->convertFieldInferenceToTypeInfo($inference);
                }
            }
        }

        return TypeInfo::object($properties);
    }

    /**
     * Infer type from a function call expression - returns TypeInfo.
     */
    private function inferFromFuncCallToDto(Node\Expr\FuncCall $node): TypeInfo
    {
        if (! $node->name instanceof Node\Name) {
            return TypeInfo::string();
        }

        $funcName = $node->name->toString();

        // JSON functions
        if ($funcName === 'json_decode') {
            return TypeInfo::array();
        }

        if ($funcName === 'json_encode') {
            return TypeInfo::string();
        }

        // Array functions
        if (in_array($funcName, ['array_values', 'array_keys', 'array_merge', 'array_filter', 'array_map'], true)) {
            return TypeInfo::array();
        }

        // Count functions
        if (in_array($funcName, ['count', 'sizeof'], true)) {
            return TypeInfo::integer();
        }

        // String functions
        if (in_array($funcName, ['strtolower', 'strtoupper', 'trim', 'substr', 'sprintf'], true)) {
            return TypeInfo::string();
        }

        return TypeInfo::string();
    }

    /**
     * Infer type from a ternary expression - returns TypeInfo.
     */
    private function inferFromTernaryToDto(Node\Expr\Ternary $node): TypeInfo
    {
        // If the 'if' part is present (normal ternary), use it
        if ($node->if !== null) {
            return $this->infer($node->if);
        }

        // Short ternary (Elvis operator) - use the condition
        return $this->infer($node->cond);
    }

    /**
     * Convert field name inference result to TypeInfo.
     *
     * Maps semantic types from FieldNameInference to OpenAPI-compatible types.
     *
     * @param  array{type?: string, format?: string|null}  $inference
     */
    private function convertFieldInferenceToTypeInfo(array $inference): TypeInfo
    {
        $type = $inference['type'] ?? 'string';
        $format = $inference['format'] ?? null;

        // Map semantic types to OpenAPI types.
        // NOTE: Keep in sync with FieldNameInference::getFieldPatterns().
        // Any new semantic types added there need corresponding mappings here.
        $typeMapping = [
            'id' => 'integer',
            'uuid' => 'string',
            'email' => 'string',
            'password' => 'string',
            'username' => 'string',
            'name' => 'string',
            'phone' => 'string',
            'address' => 'string',
            'text' => 'string',
            'url' => 'string',
            'token' => 'string',
            'color' => 'string',
            'gender' => 'string',
            'timezone' => 'string',
            'locale' => 'string',
            'currency' => 'string',
            'language' => 'string',
            'status' => 'string',
            'role' => 'string',
            'type' => 'string',
            'timestamp' => 'string',
            'date' => 'string',
            'time' => 'string',
            'age' => 'integer',
            'quantity' => 'integer',
            'score' => 'integer',
            'money' => 'number',
            'rating' => 'number',
            'location' => 'number',
            'boolean' => 'boolean',
        ];

        $openApiType = $typeMapping[$type] ?? 'string';

        // Determine format if relevant
        $openApiFormat = null;
        if ($format !== null && $format !== 'text') {
            $formatMapping = [
                'datetime' => 'date-time',
                'date' => 'date',
                'time' => 'time',
                'email' => 'email',
                'url' => 'uri',
                'image_url' => 'uri',
                'avatar_url' => 'uri',
                'uuid' => 'uuid',
                'integer' => 'int64',
                'decimal' => 'double',
            ];

            $openApiFormat = $formatMapping[$format] ?? null;
        }

        // Only apply format when base type is 'string'
        if ($openApiFormat !== null && $openApiType === 'string') {
            return TypeInfo::stringWithFormat($openApiFormat);
        }

        return match ($openApiType) {
            'integer' => TypeInfo::integer(),
            'number' => TypeInfo::number(),
            'boolean' => TypeInfo::boolean(),
            default => TypeInfo::string(),
        };
    }
}
