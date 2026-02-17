<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Console;

use LaravelSpectrum\Console\Commands\DiffOpenApiCommand;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DiffOpenApiCommandTest extends TestCase
{
    #[Test]
    public function command_has_expected_name_and_description(): void
    {
        $command = app(DiffOpenApiCommand::class);

        $this->assertSame('spectrum:diff', $command->getName());
        $this->assertStringContainsString('Compare two OpenAPI specifications', $command->getDescription());
    }

    #[Test]
    public function command_signature_includes_expected_arguments_and_options(): void
    {
        $command = app(DiffOpenApiCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('from'));
        $this->assertTrue($definition->hasArgument('to'));
        $this->assertTrue($definition->hasOption('against'));
        $this->assertTrue($definition->hasOption('breaking-only'));
        $this->assertTrue($definition->hasOption('format'));

        $this->assertSame('text', $definition->getOption('format')->getDefault());
    }
}
