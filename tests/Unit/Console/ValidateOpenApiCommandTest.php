<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Console;

use LaravelSpectrum\Console\Commands\ValidateOpenApiCommand;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ValidateOpenApiCommandTest extends TestCase
{
    #[Test]
    public function command_has_expected_name_and_description(): void
    {
        $command = app(ValidateOpenApiCommand::class);

        $this->assertSame('spectrum:validate', $command->getName());
        $this->assertStringContainsString('Validate an OpenAPI specification', $command->getDescription());
    }

    #[Test]
    public function command_signature_includes_expected_argument_and_options(): void
    {
        $command = app(ValidateOpenApiCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('path'));
        $this->assertTrue($definition->hasOption('generate'));
        $this->assertTrue($definition->hasOption('strict'));
        $this->assertTrue($definition->hasOption('format'));

        $this->assertSame('text', $definition->getOption('format')->getDefault());
    }
}
