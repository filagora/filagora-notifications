<?php

declare(strict_types=1);

namespace Filagora\Notifications\Services;

use Filagora\Notifications\Category;
use Filagora\Notifications\Enums\NotificationChannel;
use Filagora\Notifications\Exceptions\InvalidPreference;
use Filagora\Notifications\NotificationCategories;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use LogicException;

/**
 * The links a notification mail carries to switch one category off, and the switching off.
 * Only categories registered as unsubscribable can be switched off this way, and only on the
 * email channel: push is revoked from the browser, never from a mailed link.
 */
class Unsubscribe
{
    public const PAGE_ROUTE = 'filagora-notifications.unsubscribe';

    public const ONE_CLICK_ROUTE = 'filagora-notifications.unsubscribe.one-click';

    public function __construct(
        private readonly NotificationCategories $categories,
        private readonly Preferences $preferences,
    ) {}

    /**
     * A signed link to the page that confirms the unsubscribe.
     */
    public function url(Authenticatable $user, Category|string $category): string
    {
        return $this->signed(self::PAGE_ROUTE, $user, $category);
    }

    /**
     * A signed link a mail client may POST to, for the `List-Unsubscribe-Post` header.
     */
    public function oneClickUrl(Authenticatable $user, Category|string $category): string
    {
        return $this->signed(self::ONE_CLICK_ROUTE, $user, $category);
    }

    /**
     * Adds `List-Unsubscribe` and `List-Unsubscribe-Post` to a mail, for an optional category.
     */
    public function decorate(MailMessage $message, Authenticatable $user, Category|string $category): MailMessage
    {
        $oneClick = $this->oneClickUrl($user, $category);

        return $message->withSymfonyMessage(function ($mail) use ($oneClick): void {
            $mail->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$oneClick.'>');
            $mail->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        });
    }

    /**
     * Switches the category off on the email channel.
     *
     * @throws InvalidPreference When the category is unknown or not unsubscribable.
     */
    public function apply(Authenticatable $user, string $categoryId): Category
    {
        $category = $this->unsubscribable($categoryId);

        $this->preferences->set($user, $category, NotificationChannel::EMAIL, false);

        return $category;
    }

    private function signed(string $route, Authenticatable $user, Category|string $category): string
    {
        $category = $this->unsubscribable($category instanceof Category ? $category->id : $category);

        if (! Route::has($route)) {
            throw new LogicException('The unsubscribe routes are off; set filagora-notifications.routes.enabled to build unsubscribe links.');
        }

        $parameters = ['user' => $user->getAuthIdentifier(), 'category' => $category->id];
        $minutes = config('filagora-notifications.unsubscribe.signed_ttl');

        return is_numeric($minutes)
            ? URL::temporarySignedRoute($route, now()->addMinutes((int) $minutes), $parameters)
            : URL::signedRoute($route, $parameters);
    }

    private function unsubscribable(string $id): Category
    {
        $category = $this->categories->get($id);

        if (! $category->unsubscribable || $category->isMode() || ! $category->supports(NotificationChannel::EMAIL)) {
            throw InvalidPreference::notUnsubscribable($id);
        }

        return $category;
    }
}
