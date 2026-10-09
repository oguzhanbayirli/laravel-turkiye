<?php

namespace OguzhanBayirli\Turkiye\Tests;

use OguzhanBayirli\Turkiye\TurkiyeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TurkiyeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('turkiye.tcmb.retries', 0);
    }
}
