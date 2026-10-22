<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Switches;

use Filagora\Notifications\Tests\Bridges\BridgesTestCase;
use Filagora\Notifications\Tests\Support\HostProvider;
use Filagora\Notifications\Tests\Support\HostPushPreferences;
use Filagora\Push\Contracts\PushPreferences;

/**
 * A host that binds its own answers: push before any provider registers, messages and forums in a
 * provider listed after this package's. Either way the host must win.
 */
abstract class HostBindingsTestCase extends BridgesTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), HostProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->bind(PushPreferences::class, HostPushPreferences::class);
    }
}
