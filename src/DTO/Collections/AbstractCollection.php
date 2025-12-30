<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO\Collections;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Abstract base class for type-safe, immutable collections.
 *
 * Provides common collection operations while enforcing immutability
 * through PHP 8.2 readonly classes. Subclasses define the element type
 * via PHPDoc @template annotations.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
abstract readonly class AbstractCollection implements Countable, IteratorAggregate
{
    /**
     * @param  array<int, T>  $items
     */
    final public function __construct(
        protected array $items = [],
    ) {}

    /**
     * Create an empty collection.
     */
    abstract public static function empty(): static;

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Check if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the number of items in the collection.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get all items as an array.
     *
     * @return array<int, T>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the first item in the collection.
     *
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * Get the last item in the collection.
     *
     * @return T|null
     */
    public function last(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        return $this->items[count($this->items) - 1];
    }

    /**
     * Get an iterator for the collection.
     *
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Filter items using a callback.
     *
     * Returns a new collection containing only items that pass the predicate.
     *
     * @param  callable(T): bool  $predicate
     */
    public function filter(callable $predicate): static
    {
        return new static(array_values(array_filter($this->items, $predicate)));
    }

    /**
     * Transform items using a callback.
     *
     * @template TResult
     *
     * @param  callable(T): TResult  $callback
     * @return array<int, TResult>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    /**
     * Check if any item satisfies the predicate.
     *
     * @param  callable(T): bool  $predicate
     */
    public function any(callable $predicate): bool
    {
        foreach ($this->items as $item) {
            if ($predicate($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if all items satisfy the predicate.
     *
     * @param  callable(T): bool  $predicate
     */
    public function every(callable $predicate): bool
    {
        foreach ($this->items as $item) {
            if (! $predicate($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reduce the collection to a single value.
     *
     * @template TResult
     *
     * @param  callable(TResult, T): TResult  $callback
     * @param  TResult  $initial
     * @return TResult
     */
    public function reduce(callable $callback, mixed $initial): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }
}
