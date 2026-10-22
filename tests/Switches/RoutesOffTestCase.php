<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Switches;

use Filagora\Notifications\Tests\TestCase;

/**
 * The package as most installs run it: the unsubscribe routes left at their default, off.
 */
abstract class RoutesOffTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('filagora-notifications.routes.enabled', false);
    }
}
