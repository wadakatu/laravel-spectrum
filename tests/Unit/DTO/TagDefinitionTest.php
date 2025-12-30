<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\TagDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TagDefinitionTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_description(): void
    {
        $tagDefinition = new TagDefinition(
            name: 'User',
            description: 'User management endpoints',
        );

        $this->assertEquals('User', $tagDefinition->name);
        $this->assertEquals('User management endpoints', $tagDefinition->description);
    }

    #[Test]
    public function it_can_be_constructed_without_description(): void
    {
        $tagDefinition = new TagDefinition(
            name: 'Post',
        );

        $this->assertEquals('Post', $tagDefinition->name);
        $this->assertNull($tagDefinition->description);
    }

    #[Test]
    public function it_creates_from_array_with_description(): void
    {
        $array = [
            'name' => 'Comment',
            'description' => 'Comment operations',
        ];

        $tagDefinition = TagDefinition::fromArray($array);

        $this->assertEquals('Comment', $tagDefinition->name);
        $this->assertEquals('Comment operations', $tagDefinition->description);
    }

    #[Test]
    public function it_creates_from_array_without_description(): void
    {
        $array = [
            'name' => 'Auth',
        ];

        $tagDefinition = TagDefinition::fromArray($array);

        $this->assertEquals('Auth', $tagDefinition->name);
        $this->assertNull($tagDefinition->description);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [];

        $tagDefinition = TagDefinition::fromArray($array);

        $this->assertEquals('', $tagDefinition->name);
        $this->assertNull($tagDefinition->description);
    }

    #[Test]
    public function it_converts_to_array_with_description(): void
    {
        $tagDefinition = new TagDefinition(
            name: 'Profile',
            description: 'User profile endpoints',
        );

        $array = $tagDefinition->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertEquals('Profile', $array['name']);
        $this->assertEquals('User profile endpoints', $array['description']);
    }

    #[Test]
    public function it_converts_to_array_without_description(): void
    {
        $tagDefinition = new TagDefinition(
            name: 'Settings',
        );

        $array = $tagDefinition->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('description', $array);
        $this->assertEquals('Settings', $array['name']);
    }

    #[Test]
    public function it_checks_if_has_description(): void
    {
        $withDescription = new TagDefinition('Tag1', 'Some description');
        $withoutDescription = new TagDefinition('Tag2');
        $withEmptyDescription = new TagDefinition('Tag3', '');

        $this->assertTrue($withDescription->hasDescription());
        $this->assertFalse($withoutDescription->hasDescription());
        $this->assertFalse($withEmptyDescription->hasDescription());
    }

    #[Test]
    public function it_survives_serialization_round_trip_with_description(): void
    {
        $original = new TagDefinition(
            name: 'Original',
            description: 'Original description',
        );

        $restored = TagDefinition::fromArray($original->toArray());

        $this->assertEquals($original->name, $restored->name);
        $this->assertEquals($original->description, $restored->description);
    }

    #[Test]
    public function it_survives_serialization_round_trip_without_description(): void
    {
        $original = new TagDefinition(
            name: 'No Description',
        );

        $restored = TagDefinition::fromArray($original->toArray());

        $this->assertEquals($original->name, $restored->name);
        $this->assertNull($restored->description);
    }
}
