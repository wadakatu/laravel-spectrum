<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\AST\Visitors;

use LaravelSpectrum\Analyzers\AST\Visitors\ResourceStructureVisitor;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\Attributes\Test;

class ResourceStructureVisitorTest extends TestCase
{
    private $parser;

    private $printer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->printer = new Standard;
    }

    #[Test]
    public function it_extracts_simple_resource_structure()
    {
        $code = <<<'PHP'
        <?php
        class UserResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'email' => $this->email,
                    'created_at' => $this->created_at,
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('properties', $structure);
        $this->assertArrayHasKey('id', $structure['properties']);
        $this->assertEquals('integer', $structure['properties']['id']['type']);
        $this->assertEquals('string', $structure['properties']['name']['type']);
        $this->assertEquals('string', $structure['properties']['email']['type']);
        $this->assertEquals('string', $structure['properties']['created_at']['type']);
    }

    #[Test]
    public function it_extracts_conditional_fields_with_when()
    {
        $code = <<<'PHP'
        <?php
        class UserResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'email' => $this->when($request->user()->isAdmin(), $this->email),
                    'phone' => $this->when($this->hasPhone(), $this->phone),
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('email', $structure['properties']);
        $this->assertTrue($structure['properties']['email']['conditional']);
        $this->assertEquals('when', $structure['properties']['email']['condition']);

        $this->assertArrayHasKey('phone', $structure['properties']);
        $this->assertTrue($structure['properties']['phone']['conditional']);

        $this->assertNotEmpty($structure['conditionalFields']);
    }

    #[Test]
    public function it_extracts_when_loaded_relationships()
    {
        $code = <<<'PHP'
        <?php
        class PostResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'title' => $this->title,
                    'author' => new UserResource($this->whenLoaded('author')),
                    'comments' => CommentResource::collection($this->whenLoaded('comments')),
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('author', $structure['properties']);
        $this->assertEquals('object', $structure['properties']['author']['type']);
        $this->assertEquals('UserResource', $structure['properties']['author']['resource']);
        $this->assertTrue($structure['properties']['author']['conditional']);
        $this->assertEquals('whenLoaded', $structure['properties']['author']['condition']);
        $this->assertEquals('author', $structure['properties']['author']['relation']);

        $this->assertArrayHasKey('comments', $structure['properties']);
        $this->assertEquals('array', $structure['properties']['comments']['type']);
        $this->assertTrue($structure['properties']['comments']['conditional']);
        $this->assertEquals('comments', $structure['properties']['comments']['relation']);

        $this->assertContains('UserResource', $structure['nestedResources']);
        $this->assertContains('CommentResource', $structure['nestedResources']);
    }

    #[Test]
    public function it_extracts_nested_arrays_and_objects()
    {
        $code = <<<'PHP'
        <?php
        class ProductResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'pricing' => [
                        'amount' => $this->price,
                        'currency' => $this->currency,
                        'formatted' => '$' . number_format($this->price, 2),
                    ],
                    'metadata' => [
                        'created_at' => $this->created_at->format('Y-m-d'),
                        'updated_at' => $this->updated_at->format('Y-m-d'),
                    ],
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('pricing', $structure['properties']);
        $this->assertEquals('object', $structure['properties']['pricing']['type']);
        $this->assertArrayHasKey('properties', $structure['properties']['pricing']);
        $this->assertEquals('number', $structure['properties']['pricing']['properties']['amount']['type']);
        $this->assertEquals('string', $structure['properties']['pricing']['properties']['currency']['type']);
        $this->assertEquals('string', $structure['properties']['pricing']['properties']['formatted']['type']);

        $this->assertArrayHasKey('metadata', $structure['properties']);
        $this->assertEquals('object', $structure['properties']['metadata']['type']);
        $this->assertEquals('string', $structure['properties']['metadata']['properties']['created_at']['type']);
        $this->assertEquals('date-time', $structure['properties']['metadata']['properties']['created_at']['format']);
    }

    #[Test]
    public function it_extracts_type_casts()
    {
        $code = <<<'PHP'
        <?php
        class OrderResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'quantity' => (int) $this->quantity,
                    'price' => (float) $this->price,
                    'is_paid' => (bool) $this->is_paid,
                    'tags' => (array) $this->tags,
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertEquals('integer', $structure['properties']['quantity']['type']);
        $this->assertEquals('number', $structure['properties']['price']['type']);
        $this->assertEquals('boolean', $structure['properties']['is_paid']['type']);
        $this->assertEquals('array', $structure['properties']['tags']['type']);
    }

    #[Test]
    public function it_extracts_method_chains()
    {
        $code = <<<'PHP'
        <?php
        class EventResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'title' => $this->title,
                    'starts_at' => $this->starts_at->toDateTimeString(),
                    'attendee_count' => $this->attendees()->count(),
                    'has_tickets' => $this->hasTickets(),
                    'tags' => $this->tags->pluck('name'),
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertEquals('string', $structure['properties']['starts_at']['type']);
        $this->assertEquals('date-time', $structure['properties']['starts_at']['format']);
        $this->assertEquals('integer', $structure['properties']['attendee_count']['type']);
        $this->assertEquals('boolean', $structure['properties']['has_tickets']['type']);
        $this->assertEquals('array', $structure['properties']['tags']['type']);
    }

    #[Test]
    public function it_extracts_enum_values()
    {
        $code = <<<'PHP'
        <?php
        class OrderResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'status' => $this->status->value,
                    'priority' => $this->priority->value,
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertEquals('string', $structure['properties']['status']['type']);
        $this->assertEquals('enum', $structure['properties']['status']['source']);
        $this->assertEquals('string', $structure['properties']['priority']['type']);
        $this->assertEquals('enum', $structure['properties']['priority']['source']);
    }

    #[Test]
    public function it_extracts_array_merge_structures()
    {
        $code = <<<'PHP'
        <?php
        class UserResource extends JsonResource {
            public function toArray($request)
            {
                return array_merge([
                    'id' => $this->id,
                    'name' => $this->name,
                ], [
                    'email' => $this->email,
                    'phone' => $this->phone,
                ]);
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertEquals('object', $structure['properties']['type']);
        $this->assertTrue($structure['properties']['merged']);
    }

    #[Test]
    public function it_infers_types_from_property_names()
    {
        $code = <<<'PHP'
        <?php
        class ProfileResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'user_id' => $this->user_id,
                    'avatar_url' => $this->avatar_url,
                    'is_active' => $this->is_active,
                    'has_verified_email' => $this->has_verified_email,
                    'total_amount' => $this->total_amount,
                    'item_count' => $this->item_count,
                    'settings' => $this->settings,
                    'tags' => $this->tags,
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertEquals('integer', $structure['properties']['user_id']['type']);
        $this->assertEquals('string', $structure['properties']['avatar_url']['type']);
        $this->assertEquals('boolean', $structure['properties']['is_active']['type']);
        $this->assertEquals('string', $structure['properties']['has_verified_email']['type']);
        $this->assertEquals('number', $structure['properties']['total_amount']['type']);
        $this->assertEquals('integer', $structure['properties']['item_count']['type']);
        $this->assertEquals('object', $structure['properties']['settings']['type']);
        $this->assertEquals('array', $structure['properties']['tags']['type']);
    }

    #[Test]
    public function it_handles_string_concatenation()
    {
        $code = <<<'PHP'
        <?php
        class ProductResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'display_name' => $this->name . ' (' . $this->sku . ')',
                    'price_formatted' => '$' . $this->price,
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertEquals('string', $structure['properties']['display_name']['type']);
        $this->assertEquals('string', $structure['properties']['price_formatted']['type']);
    }

    #[Test]
    public function it_handles_dynamic_structures()
    {
        $code = <<<'PHP'
        <?php
        class DynamicResource extends JsonResource {
            public function toArray($request)
            {
                $data = ['id' => $this->id];
                
                if ($request->includeDetails) {
                    $data['details'] = $this->details;
                }
                
                return $data;
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('_notice', $structure['properties']);
        $this->assertStringContainsString('Dynamic structure detected', $structure['properties']['_notice']);
    }

    #[Test]
    public function it_handles_closure_transformations()
    {
        $code = <<<'PHP'
        <?php
        class UserResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'posts' => $this->whenLoaded('posts', function() {
                        return $this->posts->map(function($post) {
                            return [
                                'id' => $post->id,
                                'title' => $post->title,
                            ];
                        });
                    }),
                ];
            }
        }
        PHP;

        $visitor = new ResourceStructureVisitor($this->printer);
        $ast = $this->parser->parse($code);

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $structure = $visitor->getStructure();

        $this->assertArrayHasKey('posts', $structure['properties']);
        $this->assertTrue($structure['properties']['posts']['conditional']);
        $this->assertTrue($structure['properties']['posts']['hasTransformation']);
    }
}
