<?php

declare(strict_types=1);

namespace Cbox\Risk\Tests;

use Cbox\Risk\RiskServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RiskServiceProvider::class];
    }
}
