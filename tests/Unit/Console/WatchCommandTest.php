<?php

namespace LaravelPrism\Tests\Unit\Console;

use Orchestra\Testbench\TestCase;

class WatchCommandTest extends TestCase
{
    public function test_watch_command_is_registered(): void
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();
        $this->assertArrayHasKey('prism:watch', $commands);
    }

    public function test_watch_command_has_correct_signature(): void
    {
        $this->assertTrue(class_exists(\LaravelPrism\Console\WatchCommand::class));
    }

    protected function getPackageProviders($app): array
    {
        return ['LaravelPrism\\PrismServiceProvider'];
    }
}
