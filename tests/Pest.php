<?php

declare(strict_types=1);

use Filagora\Notifications\Tests\Bridges\BridgesTestCase;
use Filagora\Notifications\Tests\Switches\BridgesOffTestCase;
use Filagora\Notifications\Tests\Switches\HostBindingsTestCase;
use Filagora\Notifications\Tests\Switches\RoutesOffTestCase;
use Filagora\Notifications\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(RoutesOffTestCase::class)->use(RefreshDatabase::class)->in('Switches/RoutesOff');
pest()->extend(BridgesTestCase::class)->use(RefreshDatabase::class)->in('Bridges');
pest()->extend(BridgesOffTestCase::class)->use(RefreshDatabase::class)->in('Switches/BridgesOff');
pest()->extend(HostBindingsTestCase::class)->use(RefreshDatabase::class)->in('Switches/HostBindings');
