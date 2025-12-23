<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\TagGroupGenerator;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TagGroupGeneratorTest extends TestCase
{
    protected TagGroupGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TagGroupGenerator;
    }

    protected function tearDown(): void
    {
        // Reset config after each test
        config(['spectrum.tag_groups' => []]);
        config(['spectrum.tag_descriptions' => []]);
        config(['spectrum.ungrouped_tags_group' => 'Other']);

        parent::tearDown();
    }

    #[Test]
    public function it_generates_tag_groups_from_config(): void
    {
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User', 'Profile'],
                'Content' => ['Post', 'Comment'],
            ],
        ]);

        $usedTags = ['User', 'Post', 'Comment', 'Profile'];

        $result = $this->generator->generateTagGroups($usedTags);

        $this->assertCount(2, $result);
        $this->assertEquals('User Management', $result[0]['name']);
        $this->assertEquals(['User', 'Profile'], $result[0]['tags']);
        $this->assertEquals('Content', $result[1]['name']);
        $this->assertEquals(['Post', 'Comment'], $result[1]['tags']);
    }

    #[Test]
    public function it_adds_ungrouped_tags_to_other_group(): void
    {
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User'],
            ],
            'spectrum.ungrouped_tags_group' => 'Other',
        ]);

        $usedTags = ['User', 'Post', 'Comment'];

        $result = $this->generator->generateTagGroups($usedTags);

        $this->assertCount(2, $result);
        $this->assertEquals('User Management', $result[0]['name']);
        $this->assertEquals(['User'], $result[0]['tags']);
        $this->assertEquals('Other', $result[1]['name']);
        $this->assertEqualsCanonicalizing(['Comment', 'Post'], $result[1]['tags']);
    }

    #[Test]
    public function it_excludes_unused_tags_from_groups(): void
    {
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User', 'Profile', 'Auth'],
                'Content' => ['Post', 'Comment'],
            ],
        ]);

        $usedTags = ['User', 'Post'];

        $result = $this->generator->generateTagGroups($usedTags);

        $this->assertCount(2, $result);
        $this->assertEquals(['User'], $result[0]['tags']);
        $this->assertEquals(['Post'], $result[1]['tags']);
    }

    #[Test]
    public function it_excludes_empty_groups(): void
    {
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User', 'Profile'],
                'Content' => ['Post', 'Comment'],
            ],
        ]);

        $usedTags = ['User', 'Profile'];

        $result = $this->generator->generateTagGroups($usedTags);

        $this->assertCount(1, $result);
        $this->assertEquals('User Management', $result[0]['name']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_groups_configured(): void
    {
        config(['spectrum.tag_groups' => []]);

        $usedTags = ['User', 'Post'];

        $result = $this->generator->generateTagGroups($usedTags);

        // Should still create "Other" group for ungrouped tags
        $this->assertCount(1, $result);
        $this->assertEquals('Other', $result[0]['name']);
        $this->assertEqualsCanonicalizing(['Post', 'User'], $result[0]['tags']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_used_tags(): void
    {
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User'],
            ],
        ]);

        $usedTags = [];

        $result = $this->generator->generateTagGroups($usedTags);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_preserves_group_order_from_config(): void
    {
        config([
            'spectrum.tag_groups' => [
                'Zebra' => ['Zebra'],
                'Alpha' => ['Alpha'],
                'Middle' => ['Middle'],
            ],
        ]);

        $usedTags = ['Alpha', 'Middle', 'Zebra'];

        $result = $this->generator->generateTagGroups($usedTags);

        $this->assertEquals('Zebra', $result[0]['name']);
        $this->assertEquals('Alpha', $result[1]['name']);
        $this->assertEquals('Middle', $result[2]['name']);
    }

    #[Test]
    public function it_disables_ungrouped_group_when_set_to_null(): void
    {
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User'],
            ],
            'spectrum.ungrouped_tags_group' => null,
        ]);

        $usedTags = ['User', 'Post', 'Comment'];

        $result = $this->generator->generateTagGroups($usedTags);

        $this->assertCount(1, $result);
        $this->assertEquals('User Management', $result[0]['name']);
    }

    #[Test]
    public function it_generates_tag_definitions_with_descriptions(): void
    {
        config([
            'spectrum.tag_descriptions' => [
                'User' => 'User management endpoints',
                'Post' => 'Blog post operations',
            ],
        ]);

        $usedTags = ['User', 'Post', 'Comment'];

        $result = $this->generator->generateTagDefinitions($usedTags);

        $this->assertCount(3, $result);
        $this->assertEquals(['name' => 'User', 'description' => 'User management endpoints'], $result[0]);
        $this->assertEquals(['name' => 'Post', 'description' => 'Blog post operations'], $result[1]);
        $this->assertEquals(['name' => 'Comment'], $result[2]);
    }

    #[Test]
    public function it_generates_tag_definitions_without_descriptions(): void
    {
        config(['spectrum.tag_descriptions' => []]);

        $usedTags = ['User', 'Post'];

        $result = $this->generator->generateTagDefinitions($usedTags);

        $this->assertCount(2, $result);
        $this->assertEquals(['name' => 'User'], $result[0]);
        $this->assertEquals(['name' => 'Post'], $result[1]);
    }

    #[Test]
    public function it_returns_empty_array_for_empty_used_tags(): void
    {
        $result = $this->generator->generateTagDefinitions([]);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_excludes_empty_descriptions(): void
    {
        config([
            'spectrum.tag_descriptions' => [
                'User' => 'User management endpoints',
                'Post' => '',
            ],
        ]);

        $usedTags = ['User', 'Post'];

        $result = $this->generator->generateTagDefinitions($usedTags);

        $this->assertEquals(['name' => 'User', 'description' => 'User management endpoints'], $result[0]);
        $this->assertEquals(['name' => 'Post'], $result[1]);
    }

    #[Test]
    public function it_reports_if_tag_groups_are_configured(): void
    {
        config(['spectrum.tag_groups' => []]);
        $this->assertFalse($this->generator->hasTagGroups());

        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User'],
            ],
        ]);
        $this->assertTrue($this->generator->hasTagGroups());
    }

    #[Test]
    public function it_handles_custom_ungrouped_group_name(): void
    {
        config([
            'spectrum.tag_groups' => [
                'Main' => ['User'],
            ],
            'spectrum.ungrouped_tags_group' => 'Miscellaneous',
        ]);

        $usedTags = ['User', 'Post'];

        $result = $this->generator->generateTagGroups($usedTags);

        $this->assertCount(2, $result);
        $this->assertEquals('Miscellaneous', $result[1]['name']);
    }
}
