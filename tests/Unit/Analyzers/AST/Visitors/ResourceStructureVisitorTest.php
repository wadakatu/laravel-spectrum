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

        // array_merge is now properly parsed - all properties should be extracted
        $this->assertArrayHasKey('id', $structure['properties']);
        $this->assertArrayHasKey('name', $structure['properties']);
        $this->assertArrayHasKey('email', $structure['properties']);
        $this->assertArrayHasKey('phone', $structure['properties']);
        $this->assertEquals('integer', $structure['properties']['id']['type']);
        $this->assertEquals('string', $structure['properties']['name']['type']);
        $this->assertEquals('string', $structure['properties']['email']['type']);
        $this->assertEquals('string', $structure['properties']['phone']['type']);
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

        // Dynamic structures are flagged with a notice in the expression field
        $this->assertArrayHasKey('_notice', $structure['properties']);
        $this->assertArrayHasKey('expression', $structure['properties']['_notice']);
        $this->assertStringContainsString('Dynamic structure detected', $structure['properties']['_notice']['expression']);
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

    #[Test]
    public function it_handles_nullsafe_property_fetch_for_enum_values()
    {
        $code = <<<'PHP'
        <?php
        class PostResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'status' => $this->status?->value,
                    'category' => $this->category?->value,
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

        $this->assertArrayHasKey('status', $structure['properties']);
        $this->assertEquals('string', $structure['properties']['status']['type']);
        $this->assertEquals('enum', $structure['properties']['status']['source']);
        $this->assertTrue($structure['properties']['status']['nullable']);

        $this->assertArrayHasKey('category', $structure['properties']);
        $this->assertEquals('string', $structure['properties']['category']['type']);
        $this->assertEquals('enum', $structure['properties']['category']['source']);
        $this->assertTrue($structure['properties']['category']['nullable']);
    }

    #[Test]
    public function it_handles_nullsafe_method_call_for_date_formatting()
    {
        $code = <<<'PHP'
        <?php
        class PostResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'created_at' => $this->created_at?->toDateTimeString(),
                    'updated_at' => $this->updated_at?->toIso8601String(),
                    'published_at' => $this->published_at?->format('Y-m-d'),
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

        $this->assertArrayHasKey('created_at', $structure['properties']);
        $this->assertEquals('string', $structure['properties']['created_at']['type']);
        $this->assertEquals('date-time', $structure['properties']['created_at']['format']);
        $this->assertTrue($structure['properties']['created_at']['nullable']);

        $this->assertArrayHasKey('updated_at', $structure['properties']);
        $this->assertEquals('string', $structure['properties']['updated_at']['type']);
        $this->assertEquals('date-time', $structure['properties']['updated_at']['format']);
        $this->assertTrue($structure['properties']['updated_at']['nullable']);

        $this->assertArrayHasKey('published_at', $structure['properties']);
        $this->assertEquals('string', $structure['properties']['published_at']['type']);
        $this->assertTrue($structure['properties']['published_at']['nullable']);
    }

    #[Test]
    public function it_handles_nullsafe_property_fetch_for_relation_properties()
    {
        $code = <<<'PHP'
        <?php
        class UserResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'profile' => [
                        'bio' => $this->profile?->bio,
                        'avatar' => $this->profile?->avatar_url,
                        'website' => $this->profile?->website,
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

        $this->assertArrayHasKey('profile', $structure['properties']);
        $this->assertEquals('object', $structure['properties']['profile']['type']);
        $this->assertArrayHasKey('properties', $structure['properties']['profile']);

        $profileProps = $structure['properties']['profile']['properties'];
        $this->assertArrayHasKey('bio', $profileProps);
        $this->assertEquals('string', $profileProps['bio']['type']);
        $this->assertTrue($profileProps['bio']['nullable']);

        $this->assertArrayHasKey('avatar', $profileProps);
        $this->assertEquals('string', $profileProps['avatar']['type']);
        $this->assertTrue($profileProps['avatar']['nullable']);

        $this->assertArrayHasKey('website', $profileProps);
        $this->assertEquals('string', $profileProps['website']['type']);
        $this->assertTrue($profileProps['website']['nullable']);
    }

    #[Test]
    public function it_handles_nullsafe_boolean_methods()
    {
        $code = <<<'PHP'
        <?php
        class UserResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'has_profile' => $this->profile?->hasAvatar(),
                    'is_verified' => $this->account?->isVerified(),
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

        $this->assertArrayHasKey('has_profile', $structure['properties']);
        $this->assertEquals('boolean', $structure['properties']['has_profile']['type']);
        $this->assertTrue($structure['properties']['has_profile']['nullable']);

        $this->assertArrayHasKey('is_verified', $structure['properties']);
        $this->assertEquals('boolean', $structure['properties']['is_verified']['type']);
        $this->assertTrue($structure['properties']['is_verified']['nullable']);
    }

    #[Test]
    public function it_handles_deeply_nested_nullsafe_chains()
    {
        $code = <<<'PHP'
        <?php
        class UserResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'deep_value' => $this->user?->profile?->settings?->theme,
                    'deep_method' => $this->user?->profile?->getDisplayName(),
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

        // Deeply nested chains currently fall back to mixed (expected behavior)
        $this->assertArrayHasKey('deep_value', $structure['properties']);
        $this->assertTrue($structure['properties']['deep_value']['nullable']);

        $this->assertArrayHasKey('deep_method', $structure['properties']);
        $this->assertTrue($structure['properties']['deep_method']['nullable']);
    }

    #[Test]
    public function it_handles_dynamic_property_and_method_names_safely()
    {
        // This test verifies the critical fix: dynamic names don't cause exceptions
        $code = <<<'PHP'
        <?php
        class DynamicResource extends JsonResource {
            public function toArray($request)
            {
                $prop = 'dynamicProp';
                $method = 'dynamicMethod';
                return [
                    'id' => $this->id,
                    'dynamic_prop' => $this->obj?->$prop,
                    'dynamic_method' => $this->obj?->$method(),
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

        // Dynamic names should gracefully fall back to mixed without throwing exceptions
        $this->assertArrayHasKey('dynamic_prop', $structure['properties']);
        $this->assertEquals('mixed', $structure['properties']['dynamic_prop']['type']);
        $this->assertTrue($structure['properties']['dynamic_prop']['nullable']);

        $this->assertArrayHasKey('dynamic_method', $structure['properties']);
        $this->assertEquals('mixed', $structure['properties']['dynamic_method']['type']);
        $this->assertTrue($structure['properties']['dynamic_method']['nullable']);
    }

    #[Test]
    public function it_handles_additional_carbon_date_methods()
    {
        $code = <<<'PHP'
        <?php
        class EventResource extends JsonResource {
            public function toArray($request)
            {
                return [
                    'id' => $this->id,
                    'time_ago' => $this->created_at?->diffForHumans(),
                    'calendar' => $this->event_date?->calendar(),
                    'iso_formatted' => $this->updated_at?->isoFormat('LLLL'),
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

        $this->assertArrayHasKey('time_ago', $structure['properties']);
        $this->assertEquals('string', $structure['properties']['time_ago']['type']);
        $this->assertEquals('date-time', $structure['properties']['time_ago']['format']);
        $this->assertTrue($structure['properties']['time_ago']['nullable']);

        $this->assertArrayHasKey('calendar', $structure['properties']);
        $this->assertEquals('string', $structure['properties']['calendar']['type']);
        $this->assertTrue($structure['properties']['calendar']['nullable']);

        $this->assertArrayHasKey('iso_formatted', $structure['properties']);
        $this->assertEquals('string', $structure['properties']['iso_formatted']['type']);
        $this->assertTrue($structure['properties']['iso_formatted']['nullable']);
    }
}
