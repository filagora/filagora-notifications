<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests;

use Filagora\Notifications\NotificationsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            NotificationsServiceProvider::class,
        ];
    }
}
