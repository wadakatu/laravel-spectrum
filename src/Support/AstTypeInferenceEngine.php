<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

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
     * Infer type information from an AST node.
     *
     * @param  Node  $node  The AST node to analyze
     * @return array{type: string, properties?: array<string, array>, format?: string}
     */
    public function inferFromNode(Node $node): array
    {
        // Scalar types
        if ($node instanceof Node\Scalar\String_) {
            return ['type' => 'string'];
        }

        if ($node instanceof Node\Scalar\Int_) {
            return ['type' => 'integer'];
        }

        if ($node instanceof Node\Scalar\Float_) {
            return ['type' => 'number'];
        }

        // Cast expressions
        if ($node instanceof Node\Expr\Cast\Int_) {
            return ['type' => 'integer'];
        }

        if ($node instanceof Node\Expr\Cast\String_) {
            return ['type' => 'string'];
        }

        if ($node instanceof Node\Expr\Cast\Bool_) {
            return ['type' => 'boolean'];
        }

        if ($node instanceof Node\Expr\Cast\Double) {
            return ['type' => 'number'];
        }

        if ($node instanceof Node\Expr\Cast\Array_) {
            return ['type' => 'array'];
        }

        // Boolean/null constants
        if ($node instanceof Node\Expr\ConstFetch) {
            return $this->inferFromConstFetch($node);
        }

        // Arrays
        if ($node instanceof Node\Expr\Array_) {
            return $this->inferFromArray($node);
        }

        // Property access
        if ($node instanceof Node\Expr\PropertyFetch) {
            return $this->inferFromPropertyFetch($node);
        }

        // Method calls
        if ($node instanceof Node\Expr\MethodCall) {
            return $this->inferFromMethodCall($node);
        }

        // Function calls
        if ($node instanceof Node\Expr\FuncCall) {
            return $this->inferFromFuncCall($node);
        }

        // Ternary expressions
        if ($node instanceof Node\Expr\Ternary) {
            return $this->inferFromTernary($node);
        }

        // Null coalesce
        if ($node instanceof Node\Expr\BinaryOp\Coalesce) {
            return $this->inferFromNode($node->left);
        }

        // Default to string
        return ['type' => 'string'];
    }

    /**
     * Get just the type string from an AST node.
     *
     * @param  Node  $node  The AST node to analyze
     */
    public function inferTypeString(Node $node): string
    {
        return $this->inferFromNode($node)['type'];
    }

    /**
     * Infer type from a constant fetch (true, false, null).
     */
    private function inferFromConstFetch(Node\Expr\ConstFetch $node): array
    {
        $name = $node->name->toLowerString();

        if ($name === 'true' || $name === 'false') {
            return ['type' => 'boolean'];
        }

        if ($name === 'null') {
            return ['type' => 'null'];
        }

        return ['type' => 'string'];
    }

    /**
     * Infer type from an array expression.
     */
    private function inferFromArray(Node\Expr\Array_ $node): array
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
                $properties[$key] = $this->inferFromNode($item->value);
            } elseif ($item->key instanceof Node\Scalar\Int_) {
                // Numeric key - could still be associative
                $key = (string) $item->key->value;
                $properties[$key] = $this->inferFromNode($item->value);
            }
        }

        if ($hasStringKeys && ! empty($properties)) {
            return ['type' => 'object', 'properties' => $properties];
        }

        return ['type' => 'array'];
    }

    /**
     * Infer type from a property fetch expression.
     */
    private function inferFromPropertyFetch(Node\Expr\PropertyFetch $node): array
    {
        if (! $node->name instanceof Node\Identifier) {
            return ['type' => 'string'];
        }

        $propertyName = $node->name->toString();

        // Use field name inference for common patterns
        $inference = $this->fieldNameInference->inferFieldType($propertyName);

        return $this->convertFieldInferenceToType($inference);
    }

    /**
     * Infer type from a method call expression.
     */
    private function inferFromMethodCall(Node\Expr\MethodCall $node): array
    {
        if (! $node->name instanceof Node\Identifier) {
            return ['type' => 'string'];
        }

        $methodName = $node->name->toString();

        // Special handling for only() method
        if ($methodName === 'only') {
            return $this->inferFromOnlyMethod($node);
        }

        // Date/time methods
        if (in_array($methodName, ['toIso8601String', 'toString', 'toDateTimeString', 'toDateString', 'toTimeString', 'format'], true)) {
            return ['type' => 'string', 'format' => 'date-time'];
        }

        // Array conversion methods
        if (in_array($methodName, ['toArray', 'all', 'values', 'keys'], true)) {
            return ['type' => 'array'];
        }

        // Count methods
        if (in_array($methodName, ['count', 'sum', 'avg', 'average', 'max', 'min'], true)) {
            return ['type' => 'integer'];
        }

        // Boolean methods
        if (str_starts_with($methodName, 'is') || str_starts_with($methodName, 'has') || str_starts_with($methodName, 'can')) {
            return ['type' => 'boolean'];
        }

        return ['type' => 'string'];
    }

    /**
     * Infer type from only() method call.
     */
    private function inferFromOnlyMethod(Node\Expr\MethodCall $node): array
    {
        $properties = [];

        if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Expr\Array_) {
            foreach ($node->args[0]->value->items as $item) {
                if ($item && $item->value instanceof Node\Scalar\String_) {
                    $fieldName = $item->value->value;
                    $inference = $this->fieldNameInference->inferFieldType($fieldName);
                    $properties[$fieldName] = $this->convertFieldInferenceToType($inference);
                }
            }
        }

        if (! empty($properties)) {
            return ['type' => 'object', 'properties' => $properties];
        }

        return ['type' => 'object', 'properties' => []];
    }

    /**
     * Infer type from a function call expression.
     */
    private function inferFromFuncCall(Node\Expr\FuncCall $node): array
    {
        if (! $node->name instanceof Node\Name) {
            return ['type' => 'string'];
        }

        $funcName = $node->name->toString();

        // JSON functions
        if ($funcName === 'json_decode') {
            return ['type' => 'array'];
        }

        if ($funcName === 'json_encode') {
            return ['type' => 'string'];
        }

        // Array functions
        if (in_array($funcName, ['array_values', 'array_keys', 'array_merge', 'array_filter', 'array_map'], true)) {
            return ['type' => 'array'];
        }

        // Count functions
        if (in_array($funcName, ['count', 'sizeof'], true)) {
            return ['type' => 'integer'];
        }

        // String functions
        if (in_array($funcName, ['strtolower', 'strtoupper', 'trim', 'substr', 'sprintf'], true)) {
            return ['type' => 'string'];
        }

        return ['type' => 'string'];
    }

    /**
     * Infer type from a ternary expression.
     */
    private function inferFromTernary(Node\Expr\Ternary $node): array
    {
        // If the 'if' part is present (normal ternary), use it
        if ($node->if !== null) {
            return $this->inferFromNode($node->if);
        }

        // Short ternary (Elvis operator) - use the condition
        return $this->inferFromNode($node->cond);
    }

    /**
     * Convert field name inference result to standard type array.
     */
    private function convertFieldInferenceToType(array $inference): array
    {
        $type = $inference['type'] ?? 'string';
        $format = $inference['format'] ?? null;

        // Map semantic types to OpenAPI types
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

        $result = ['type' => $openApiType];

        // Add format if relevant
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

            if (isset($formatMapping[$format])) {
                $result['format'] = $formatMapping[$format];
            }
        }

        return $result;
    }
}
