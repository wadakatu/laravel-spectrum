<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO\Collections;

use LaravelSpectrum\DTO\Collections\AbstractCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @extends AbstractCollection<string>
 */
final readonly class StringCollection extends AbstractCollection
{
    public static function empty(): static
    {
        return new self([]);
    }

    /**
     * @param  array<string>  $items
     */
    public static function of(array $items): self
    {
        return new self($items);
    }
}

class AbstractCollectionTest extends TestCase
{
    #[Test]
    public function it_can_be_created_empty(): void
    {
        $collection = StringCollection::empty();

        $this->assertTrue($collection->isEmpty());
        $this->assertFalse($collection->isNotEmpty());
        $this->assertCount(0, $collection);
    }

    #[Test]
    public function it_can_be_created_with_items(): void
    {
        $collection = StringCollection::of(['a', 'b', 'c']);

        $this->assertFalse($collection->isEmpty());
        $this->assertTrue($collection->isNotEmpty());
        $this->assertCount(3, $collection);
    }

    #[Test]
    public function it_returns_all_items(): void
    {
        $items = ['foo', 'bar', 'baz'];
        $collection = StringCollection::of($items);

        $this->assertEquals($items, $collection->all());
    }

    #[Test]
    public function it_returns_first_item(): void
    {
        $collection = StringCollection::of(['first', 'second', 'third']);

        $this->assertEquals('first', $collection->first());
    }

    #[Test]
    public function it_returns_null_for_first_on_empty_collection(): void
    {
        $collection = StringCollection::empty();

        $this->assertNull($collection->first());
    }

    #[Test]
    public function it_returns_last_item(): void
    {
        $collection = StringCollection::of(['first', 'second', 'third']);

        $this->assertEquals('third', $collection->last());
    }

    #[Test]
    public function it_returns_null_for_last_on_empty_collection(): void
    {
        $collection = StringCollection::empty();

        $this->assertNull($collection->last());
    }

    #[Test]
    public function it_is_iterable(): void
    {
        $items = ['a', 'b', 'c'];
        $collection = StringCollection::of($items);

        $result = [];
        foreach ($collection as $item) {
            $result[] = $item;
        }

        $this->assertEquals($items, $result);
    }

    #[Test]
    public function it_is_countable(): void
    {
        $collection = StringCollection::of(['a', 'b', 'c', 'd', 'e']);

        $this->assertCount(5, $collection);
        $this->assertEquals(5, $collection->count());
    }

    #[Test]
    public function it_filters_items(): void
    {
        $collection = StringCollection::of(['apple', 'banana', 'apricot', 'cherry']);

        $filtered = $collection->filter(fn (string $item): bool => str_starts_with($item, 'a'));

        $this->assertCount(2, $filtered);
        $this->assertEquals(['apple', 'apricot'], $filtered->all());
    }

    #[Test]
    public function it_returns_new_instance_on_filter(): void
    {
        $original = StringCollection::of(['a', 'b', 'c']);

        $filtered = $original->filter(fn (string $item): bool => $item !== 'b');

        $this->assertNotSame($original, $filtered);
        $this->assertCount(3, $original);
        $this->assertCount(2, $filtered);
    }

    #[Test]
    public function it_maps_items(): void
    {
        $collection = StringCollection::of(['a', 'b', 'c']);

        $result = $collection->map(fn (string $item): string => strtoupper($item));

        $this->assertEquals(['A', 'B', 'C'], $result);
    }

    #[Test]
    public function it_checks_if_any_item_matches(): void
    {
        $collection = StringCollection::of(['apple', 'banana', 'cherry']);

        $this->assertTrue($collection->any(fn (string $item): bool => $item === 'banana'));
        $this->assertFalse($collection->any(fn (string $item): bool => $item === 'grape'));
    }

    #[Test]
    public function it_returns_false_for_any_on_empty_collection(): void
    {
        $collection = StringCollection::empty();

        $this->assertFalse($collection->any(fn (string $item): bool => true));
    }

    #[Test]
    public function it_checks_if_all_items_match(): void
    {
        $collection = StringCollection::of(['apple', 'apricot', 'avocado']);

        $this->assertTrue($collection->every(fn (string $item): bool => str_starts_with($item, 'a')));
        $this->assertFalse($collection->every(fn (string $item): bool => strlen($item) === 5));
    }

    #[Test]
    public function it_returns_true_for_every_on_empty_collection(): void
    {
        $collection = StringCollection::empty();

        $this->assertTrue($collection->every(fn (string $item): bool => false));
    }

    #[Test]
    public function it_reduces_items(): void
    {
        $collection = StringCollection::of(['a', 'b', 'c']);

        $result = $collection->reduce(
            fn (string $carry, string $item): string => $carry.$item,
            ''
        );

        $this->assertEquals('abc', $result);
    }

    #[Test]
    public function it_reduces_to_different_type(): void
    {
        $collection = StringCollection::of(['apple', 'banana', 'cherry']);

        $result = $collection->reduce(
            fn (int $carry, string $item): int => $carry + strlen($item),
            0
        );

        $this->assertEquals(17, $result);
    }
}
