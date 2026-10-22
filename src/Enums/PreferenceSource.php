<?php

declare(strict_types=1);

namespace Filagora\Notifications\Enums;

/**
 * Who or what set a stored preference. The column is free text so that a host can add its own
 * value; these are the ones the package itself writes and understands.
 */
enum PreferenceSource: string
{
    case USER = 'user';
    case SYSTEM_DEFAULT = 'system_default';
    case MIGRATION = 'migration';
    case ADMIN = 'admin';
}
