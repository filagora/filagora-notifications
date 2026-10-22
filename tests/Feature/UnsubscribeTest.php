<?php

declare(strict_types=1);

use Filagora\Notifications\Category;
use Filagora\Notifications\Enums\NotificationChannel as Channel;
use Filagora\Notifications\Events\PreferenceChanged;
use Filagora\Notifications\Exceptions\InvalidPreference;
use Filagora\Notifications\Models\NotificationPreference;
use Filagora\Notifications\NotificationCategories;
use Filagora\Notifications\Services\Preferences;
use Filagora\Notifications\Services\Unsubscribe;
use Filagora\Notifications\Tests\Support\TestUser;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

function unsubscribe(): Unsubscribe
{
    return app(Unsubscribe::class);
}

it('builds a signed link on the path an existing site has already mailed', function () {
    $user = TestUser::make();

    $url = unsubscribe()->url($user, 'forum.thread_updates_digest');

    expect(parse_url($url, PHP_URL_PATH))->toBe("/email-preferences/unsubscribe/{$user->id}/forum.thread_updates_digest")
        ->and($url)->toContain('signature=')
        ->and($url)->not->toContain('expires=')
        ->and(parse_url(unsubscribe()->oneClickUrl($user, 'forum.thread_updates_digest'), PHP_URL_PATH))
        ->toBe("/email-preferences/unsubscribe/{$user->id}/forum.thread_updates_digest/one-click");
});

it('gives a link an expiry when a lifetime is configured', function () {
    config(['filagora-notifications.unsubscribe.signed_ttl' => 60]);
    $user = TestUser::make();
    $url = unsubscribe()->url($user, 'forum.thread_updates_digest');

    expect($url)->toContain('expires=');

    $this->travel(61)->minutes();
    $this->get($url)->assertForbidden();
    expect(NotificationPreference::count())->toBe(0);
});

it('switches the category off for email when the link is followed, and shows the page', function () {
    $user = TestUser::make();
    config(['filagora-notifications.unsubscribe.manage_url' => 'https://example.test/settings']);

    $this->get(unsubscribe()->url($user, 'forum.thread_updates_instant'))
        ->assertOk()
        ->assertSee('New posts in threads you follow')
        ->assertSee('https://example.test/settings');

    $preferences = app(Preferences::class);
    $preferences->flush();

    expect($preferences->allows($user, 'forum.thread_updates_instant'))->toBeFalse()
        ->and($preferences->allows($user, 'forum.thread_updates_instant', Channel::PUSH))->toBeTrue()
        ->and($preferences->allows($user, 'forum.thread_updates_digest'))->toBeTrue();
});

it('leaves out the manage link when none is configured', function () {
    $this->get(unsubscribe()->url(TestUser::make(), 'forum.thread_updates_digest'))
        ->assertOk()
        ->assertDontSee('Manage your notification preferences');
});

it('answers a one-click POST with no content, and needs no session or CSRF token', function () {
    $user = TestUser::make();

    $this->post(unsubscribe()->oneClickUrl($user, 'forum.thread_updates_digest'))->assertNoContent();

    app(Preferences::class)->flush();

    expect(app(Preferences::class)->allows($user, 'forum.thread_updates_digest'))->toBeFalse();
});

it('fires the change event once, and a second follow changes nothing more', function () {
    Event::fake([PreferenceChanged::class]);
    $user = TestUser::make();
    $url = unsubscribe()->oneClickUrl($user, 'forum.thread_updates_digest');

    $this->post($url)->assertNoContent();
    app(Preferences::class)->flush();
    $this->post($url)->assertNoContent();

    Event::assertDispatchedTimes(PreferenceChanged::class, 1);
    expect(NotificationPreference::count())->toBe(1);
});

it('refuses a link that is unsigned, altered, or for someone else', function () {
    $user = TestUser::make();
    $other = TestUser::make('Other');
    $url = unsubscribe()->url($user, 'forum.thread_updates_digest');

    $this->get("/email-preferences/unsubscribe/{$user->id}/forum.thread_updates_digest")->assertForbidden();
    $this->get(str_replace((string) $user->id, (string) $other->id, $url))->assertForbidden();
    $this->post(str_replace('forum.thread_updates_digest', 'forum.thread_updates_instant', unsubscribe()->oneClickUrl($user, 'forum.thread_updates_digest')))->assertForbidden();

    expect(NotificationPreference::count())->toBe(0);
});

it('answers 404 for a signed link to a person or category that cannot be unsubscribed', function (string $category, bool $realUser) {
    $user = TestUser::make();
    $id = $realUser ? $user->id : 99999;

    $url = URL::signedRoute(Unsubscribe::PAGE_ROUTE, ['user' => $id, 'category' => $category]);
    $this->get($url)->assertNotFound();
    $this->post(URL::signedRoute(Unsubscribe::ONE_CLICK_ROUTE, ['user' => $id, 'category' => $category]))->assertNotFound();

    expect(NotificationPreference::count())->toBe(0);
})->with([
    'a switch that is not unsubscribable' => ['forum.mention', true],
    'a mode' => ['forum.thread_updates_digest_frequency', true],
    'an unknown category' => ['no.such', true],
    'an unknown person' => ['forum.thread_updates_digest', false],
]);

it('will not build a link for a category that cannot be unsubscribed', function (string $category) {
    expect(fn () => unsubscribe()->url(TestUser::make(), $category))->toThrow(InvalidPreference::class);
})->with(['forum.mention', 'forum.thread_updates_digest_frequency', 'no.such']);

it('will not build a link for a category that is not delivered by email', function () {
    app(NotificationCategories::class)->register(new Category('x.pushonly', 'Push only', [Channel::PUSH], unsubscribable: true));

    expect(fn () => unsubscribe()->url(TestUser::make(), 'x.pushonly'))->toThrow(InvalidPreference::class);
});

it('accepts a category object as well as its id', function () {
    $user = TestUser::make();
    $category = app(NotificationCategories::class)->get('forum.thread_updates_digest');

    expect(unsubscribe()->url($user, $category))->toBe(unsubscribe()->url($user, 'forum.thread_updates_digest'));
});

it('adds the one-click headers to a mail', function () {
    $user = TestUser::make();
    $message = unsubscribe()->decorate(new MailMessage, $user, 'forum.thread_updates_digest');

    $email = new Email;
    foreach ($message->callbacks as $callback) {
        $callback($email);
    }

    expect($email->getHeaders()->getHeaderBody('List-Unsubscribe'))->toBe('<'.unsubscribe()->oneClickUrl($user, 'forum.thread_updates_digest').'>')
        ->and($email->getHeaders()->getHeaderBody('List-Unsubscribe-Post'))->toBe('List-Unsubscribe=One-Click');
});

it('throttles the unsubscribe routes', function () {
    expect(app('router')->getRoutes()->getByName(Unsubscribe::ONE_CLICK_ROUTE)->gatherMiddleware())->toContain('throttle:60,1', 'signed');
});

it('uses the host\'s own view when one is configured', function () {
    view()->addNamespace('host', sys_get_temp_dir());
    file_put_contents(sys_get_temp_dir().'/gone.blade.php', 'Gone: {{ $label }}');
    config(['filagora-notifications.unsubscribe.view' => 'host::gone']);

    $this->get(unsubscribe()->url(TestUser::make(), 'forum.thread_updates_digest'))->assertOk()->assertSee('Gone: Digest of new posts in threads you follow');

    unlink(sys_get_temp_dir().'/gone.blade.php');
});
