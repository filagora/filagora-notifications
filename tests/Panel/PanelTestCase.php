<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Panel;

use Filagora\Notifications\Tests\Support\TestPanelProvider;
use Filagora\Notifications\Tests\TestCase;

/**
 * The package inside a Filament panel, with the entitlements package (installed in every test case) deciding who may do what.
 */
abstract class PanelTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), TestPanelProvider::class];
    }
}
