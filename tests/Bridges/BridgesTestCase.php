<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Bridges;

use Filagora\Forums\ForumsServiceProvider;
use Filagora\Messages\MessagesServiceProvider;
use Filagora\Notifications\Tests\TestCase;
use Filagora\Push\PushServiceProvider;
use NotificationChannels\WebPush\WebPushServiceProvider;

/**
 * The package with the three sibling packages it bridges to installed.
 */
abstract class BridgesTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ForumsServiceProvider::class,
            MessagesServiceProvider::class,
            WebPushServiceProvider::class,
            PushServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webpush.database_connection', null);
    }
}
