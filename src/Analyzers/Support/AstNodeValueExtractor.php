<?php

declare(strict_types=1);

namespace LaravelSpectrum\Analyzers\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;

/**
 * Extracts scalar values from PHP-Parser AST nodes.
 *
 * This class provides reusable methods for extracting values from
 * common AST node types (strings, numbers, booleans, arrays).
 */
class AstNodeValueExtractor
{
    /**
     * Extract a value from an AST node.
     *
     * Supports: String_, LNumber, DNumber, ConstFetch (true/false/null), Array_
     *
     * @param  Node|null  $node  The AST node to extract value from
     * @return string|int|float|bool|array<mixed>|null The extracted value, or null if not extractable
     */
    public function extractValue(?Node $node): string|int|float|bool|array|null
    {
        if ($node === null) {
            return null;
        }

        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof LNumber) {
            return $node->value;
        }

        if ($node instanceof DNumber) {
            return $node->value;
        }

        if ($node instanceof ConstFetch) {
            return $this->extractConstValue($node);
        }

        if ($node instanceof Array_) {
            return $this->extractArrayValues($node);
        }

        return null;
    }

    /**
     * Extract a string value from an AST node.
     *
     * @param  Node|null  $node  The AST node to extract string from
     * @return string|null The string value, or null if not a string node
     */
    public function extractStringValue(?Node $node): ?string
    {
        if ($node instanceof String_) {
            return $node->value;
        }

        return null;
    }

    /**
     * Extract an integer value from an AST node.
     *
     * @param  Node|null  $node  The AST node to extract integer from
     * @return int|null The integer value, or null if not an integer node
     */
    public function extractIntValue(?Node $node): ?int
    {
        if ($node instanceof LNumber) {
            return $node->value;
        }

        return null;
    }

    /**
     * Extract a float value from an AST node.
     *
     * @param  Node|null  $node  The AST node to extract float from
     * @return float|null The float value, or null if not a float node
     */
    public function extractFloatValue(?Node $node): ?float
    {
        if ($node instanceof DNumber) {
            return $node->value;
        }

        return null;
    }

    /**
     * Extract array values from an Array_ node.
     *
     * Returns only the values (ignores keys). For associative arrays,
     * use extractKeyValueArray().
     *
     * @param  Node  $node  The Array_ node to extract values from
     * @return array<int, mixed>|null Array of values, or null if not an Array_ node
     */
    public function extractArrayValues(Node $node): ?array
    {
        if (! $node instanceof Array_) {
            return null;
        }

        $values = [];

        /** @var array<int, ArrayItem|null> $items */
        $items = $node->items;

        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }

            $value = $this->extractValue($item->value);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Extract key-value pairs from an Array_ node.
     *
     * @param  Node  $node  The Array_ node to extract from
     * @return array<string, mixed> Associative array of key-value pairs
     */
    public function extractKeyValueArray(Node $node): array
    {
        if (! $node instanceof Array_) {
            return [];
        }

        $result = [];

        /** @var array<int, ArrayItem|null> $items */
        $items = $node->items;

        foreach ($items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $key = $this->extractStringValue($item->key);
            if ($key === null) {
                continue;
            }

            $result[$key] = $this->extractValue($item->value);
        }

        return $result;
    }

    /**
     * Extract boolean, null constants from ConstFetch node.
     *
     * @param  ConstFetch  $node  The ConstFetch node
     * @return bool|null The boolean value, or null for 'null' constant
     */
    private function extractConstValue(ConstFetch $node): ?bool
    {
        $name = strtolower($node->name->toString());

        return match ($name) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => null,
        };
    }
}
