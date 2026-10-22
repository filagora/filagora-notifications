<?php

declare(strict_types=1);

namespace Filagora\Notifications\Http\Controllers;

use Filagora\Core\Support\UserModel;
use Filagora\Notifications\Category;
use Filagora\Notifications\Exceptions\InvalidPreference;
use Filagora\Notifications\Services\Unsubscribe;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Follows a signed unsubscribe link. The `signed` middleware has already checked the link is
 * genuine and unexpired; an unknown person or a category that cannot be switched off is a 404.
 */
class UnsubscribeController extends Controller
{
    public function __construct(private readonly Unsubscribe $unsubscribe) {}

    public function page(string $user, string $category): View
    {
        $applied = $this->apply($user, $category);

        return view((string) config('filagora-notifications.unsubscribe.view', 'filagora-notifications::unsubscribed'), [
            'label' => $applied->label(),
            'manageUrl' => config('filagora-notifications.unsubscribe.manage_url'),
        ]);
    }

    public function oneClick(string $user, string $category): Response
    {
        $this->apply($user, $category);

        return response()->noContent();
    }

    private function apply(string $user, string $category): Category
    {
        $person = UserModel::class()::query()->find($user);

        abort_unless($person instanceof Authenticatable, 404);

        try {
            return $this->unsubscribe->apply($person, $category);
        } catch (InvalidPreference) {
            abort(404);
        }
    }
}
