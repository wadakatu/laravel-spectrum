<?php

declare(strict_types=1);

namespace Tests\Unit\Performance\Support;

use LaravelSpectrum\Performance\Support\DefaultParallelSupportChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DefaultParallelSupportCheckerTest extends TestCase
{
    #[Test]
    public function is_supported_returns_boolean(): void
    {
        $checker = new DefaultParallelSupportChecker;

        $result = $checker->isSupported();

        $this->assertIsBool($result);
    }

    #[Test]
    public function is_supported_returns_false_when_pcntl_not_loaded(): void
    {
        $checker = new class extends DefaultParallelSupportChecker
        {
            protected function isPcntlLoaded(): bool
            {
                return false;
            }
        };

        $this->assertFalse($checker->isSupported());
    }

    #[Test]
    public function is_supported_returns_false_on_windows(): void
    {
        $checker = new class extends DefaultParallelSupportChecker
        {
            protected function isPcntlLoaded(): bool
            {
                return true;
            }

            protected function isWindows(): bool
            {
                return true;
            }
        };

        $this->assertFalse($checker->isSupported());
    }

    #[Test]
    public function is_supported_returns_false_when_disabled_by_config(): void
    {
        $checker = new class extends DefaultParallelSupportChecker
        {
            protected function isPcntlLoaded(): bool
            {
                return true;
            }

            protected function isWindows(): bool
            {
                return false;
            }

            protected function isDisabledByConfig(): bool
            {
                return true;
            }
        };

        $this->assertFalse($checker->isSupported());
    }

    #[Test]
    public function is_supported_returns_true_when_all_conditions_met(): void
    {
        $checker = new class extends DefaultParallelSupportChecker
        {
            protected function isPcntlLoaded(): bool
            {
                return true;
            }

            protected function isWindows(): bool
            {
                return false;
            }

            protected function isDisabledByConfig(): bool
            {
                return false;
            }
        };

        $this->assertTrue($checker->isSupported());
    }

    #[Test]
    public function is_pcntl_loaded_checks_extension(): void
    {
        $checker = new class extends DefaultParallelSupportChecker
        {
            public function test_is_pcntl_loaded(): bool
            {
                return $this->isPcntlLoaded();
            }
        };

        // This test verifies the method works - actual result depends on environment
        $result = $checker->test_is_pcntl_loaded();
        $this->assertIsBool($result);
        $this->assertEquals(extension_loaded('pcntl'), $result);
    }

    #[Test]
    public function is_windows_checks_os_family(): void
    {
        $checker = new class extends DefaultParallelSupportChecker
        {
            public function test_is_windows(): bool
            {
                return $this->isWindows();
            }
        };

        $result = $checker->test_is_windows();
        $this->assertIsBool($result);
        $this->assertEquals(PHP_OS_FAMILY === 'Windows', $result);
    }

    #[Test]
    public function is_disabled_by_config_returns_false_when_config_not_available(): void
    {
        // When config() function is not available, should return false
        $checker = new class extends DefaultParallelSupportChecker
        {
            public function test_is_disabled_by_config(): bool
            {
                return $this->isDisabledByConfig();
            }
        };

        // In PHPUnit environment without Laravel, config() is not available
        // so this should return false
        if (! function_exists('config')) {
            $this->assertFalse($checker->test_is_disabled_by_config());
        } else {
            // If Laravel is available, test the actual behavior
            $this->assertIsBool($checker->test_is_disabled_by_config());
        }
    }

    #[Test]
    public function priority_order_pcntl_checked_first(): void
    {
        $checkOrder = [];

        $checker = new class($checkOrder) extends DefaultParallelSupportChecker
        {
            /** @var array<string> */
            private array $order;

            /**
             * @param  array<string>  $order
             */
            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            protected function isPcntlLoaded(): bool
            {
                $this->order[] = 'pcntl';

                return false;  // Return false to stop here
            }

            protected function isWindows(): bool
            {
                $this->order[] = 'windows';

                return false;
            }

            protected function isDisabledByConfig(): bool
            {
                $this->order[] = 'config';

                return false;
            }
        };

        $checker->isSupported();

        // Only pcntl should be checked when it returns false
        $this->assertEquals(['pcntl'], $checkOrder);
    }

    #[Test]
    public function priority_order_windows_checked_second(): void
    {
        $checkOrder = [];

        $checker = new class($checkOrder) extends DefaultParallelSupportChecker
        {
            /** @var array<string> */
            private array $order;

            /**
             * @param  array<string>  $order
             */
            public function __construct(array &$order)
            {
                $this->order = &$order;
            }

            protected function isPcntlLoaded(): bool
            {
                $this->order[] = 'pcntl';

                return true;
            }

            protected function isWindows(): bool
            {
                $this->order[] = 'windows';

                return true;  // Return true to stop here
            }

            protected function isDisabledByConfig(): bool
            {
                $this->order[] = 'config';

                return false;
            }
        };

        $checker->isSupported();

        // pcntl and windows should be checked
        $this->assertEquals(['pcntl', 'windows'], $checkOrder);
    }
}
