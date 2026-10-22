<?php

declare(strict_types=1);

namespace Filagora\Notifications\Tests\Support;

use Filagora\Entitlements\Concerns\HasEntitlements;
use Filagora\Notifications\Concerns\HasNotificationPreferences;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TestUser extends Authenticatable implements MustVerifyEmail
{
    use HasEntitlements;
    use HasNotificationPreferences;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public static function make(string $name = 'User'): self
    {
        return self::create([
            'name' => $name,
            'email' => strtolower($name).uniqid().'@example.test',
            'password' => 'x',
            'email_verified_at' => now(),
        ]);
    }
}
