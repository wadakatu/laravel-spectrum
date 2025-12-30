<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\TagGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TagGroupTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed(): void
    {
        $tagGroup = new TagGroup(
            name: 'User Management',
            tags: ['User', 'Profile', 'Auth'],
        );

        $this->assertEquals('User Management', $tagGroup->name);
        $this->assertEquals(['User', 'Profile', 'Auth'], $tagGroup->tags);
    }

    #[Test]
    public function it_can_be_constructed_with_empty_tags(): void
    {
        $tagGroup = new TagGroup(
            name: 'Empty Group',
            tags: [],
        );

        $this->assertEquals('Empty Group', $tagGroup->name);
        $this->assertEquals([], $tagGroup->tags);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $array = [
            'name' => 'Content',
            'tags' => ['Post', 'Comment'],
        ];

        $tagGroup = TagGroup::fromArray($array);

        $this->assertEquals('Content', $tagGroup->name);
        $this->assertEquals(['Post', 'Comment'], $tagGroup->tags);
    }

    #[Test]
    public function it_creates_from_array_with_defaults(): void
    {
        $array = [
            'name' => 'Minimal',
        ];

        $tagGroup = TagGroup::fromArray($array);

        $this->assertEquals('Minimal', $tagGroup->name);
        $this->assertEquals([], $tagGroup->tags);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $tagGroup = new TagGroup(
            name: 'API',
            tags: ['Endpoint', 'Resource'],
        );

        $array = $tagGroup->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('tags', $array);
        $this->assertEquals('API', $array['name']);
        $this->assertEquals(['Endpoint', 'Resource'], $array['tags']);
    }

    #[Test]
    public function it_checks_if_has_tags(): void
    {
        $withTags = new TagGroup('With Tags', ['User', 'Post']);
        $withoutTags = new TagGroup('Without Tags', []);

        $this->assertTrue($withTags->hasTags());
        $this->assertFalse($withoutTags->hasTags());
    }

    #[Test]
    public function it_returns_tag_count(): void
    {
        $tagGroup = new TagGroup('Group', ['A', 'B', 'C']);

        $this->assertEquals(3, $tagGroup->getTagCount());
    }

    #[Test]
    public function it_checks_if_contains_tag(): void
    {
        $tagGroup = new TagGroup('Group', ['User', 'Post', 'Comment']);

        $this->assertTrue($tagGroup->containsTag('User'));
        $this->assertTrue($tagGroup->containsTag('Post'));
        $this->assertFalse($tagGroup->containsTag('Admin'));
        $this->assertFalse($tagGroup->containsTag('user')); // Case sensitive
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = new TagGroup(
            name: 'Original Group',
            tags: ['Tag1', 'Tag2', 'Tag3'],
        );

        $restored = TagGroup::fromArray($original->toArray());

        $this->assertEquals($original->name, $restored->name);
        $this->assertEquals($original->tags, $restored->tags);
    }
}
