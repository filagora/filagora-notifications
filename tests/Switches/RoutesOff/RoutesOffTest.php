<?php

declare(strict_types=1);

use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Unsubscribe;
use Filagora\Notifications\Tests\Support\TestUser;
use Illuminate\Support\Facades\Route;

it('registers no unsubscribe routes by default', function () {
    expect(Route::has(Unsubscribe::PAGE_ROUTE))->toBeFalse()
        ->and(Route::has(Unsubscribe::ONE_CLICK_ROUTE))->toBeFalse();

    $this->get('/email-preferences/unsubscribe/1/forum.thread_updates_digest')->assertNotFound();
});

it('says why it cannot build a link', function () {
    expect(fn () => app(Unsubscribe::class)->url(TestUser::make(), 'forum.thread_updates_digest'))
        ->toThrow(LogicException::class, 'unsubscribe routes are off');
});

it('still answers suppression and preferences', function () {
    expect(app(Preferences::class)->canDeliver(TestUser::make(), 'forum.mention'))->toBeTrue();
});
