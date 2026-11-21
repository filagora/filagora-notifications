<?php

declare(strict_types=1);

namespace Filagora\Notifications\Enums;

/**
 * Who a category is for and whether a person may switch it off.
 */
enum CategoryKind: string
{
    /** Offered to everyone, and the person chooses. */
    case USER = 'user';

    /** Offered only to people who hold the category's access key (moderators, for example). */
    case STAFF = 'staff';

    /** Always delivered (verification, security notices); the person cannot switch it off. */
    case REQUIRED = 'required';
}
