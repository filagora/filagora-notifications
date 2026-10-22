<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Switches;

use Filagora\Notifications\Tests\Bridges\BridgesTestCase;

/**
 * The siblings installed, every bridge switched off.
 */
abstract class BridgesOffTestCase extends BridgesTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('filagora-notifications.bridges', ['push' => false, 'forums' => false, 'messages' => false]);
    }
}
