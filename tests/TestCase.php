<?php

declare(strict_types=1);

namespace Cbox\Risk\Tests;

use Cbox\Risk\Contracts\MailDomainResolver;
use Cbox\Risk\Contracts\RiskScorer;
use Cbox\Risk\Contracts\TorExitNodes;
use Cbox\Risk\RiskServiceProvider;
use Cbox\Risk\Testing\FakeMailDomainResolver;
use Cbox\Risk\Testing\FakeTorExitNodes;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keep DNS/Tor lookups deterministic and offline for the whole suite;
        // individual tests can rebind these with entries and forget the scorer.
        $this->app->instance(MailDomainResolver::class, new FakeMailDomainResolver);
        $this->app->instance(TorExitNodes::class, new FakeTorExitNodes);
        $this->app->forgetInstance(RiskScorer::class);
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RiskServiceProvider::class];
    }
}
