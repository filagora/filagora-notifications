<?php

declare(strict_types=1);

use Filagora\Notifications\Http\Controllers\UnsubscribeController;
use Filagora\Notifications\Services\Unsubscribe;
use Illuminate\Support\Facades\Route;

Route::get('unsubscribe/{user}/{category}', [UnsubscribeController::class, 'page'])
    ->middleware('signed')
    ->where('category', '[A-Za-z0-9_.-]+')
    ->name(Unsubscribe::PAGE_ROUTE);

Route::post('unsubscribe/{user}/{category}/one-click', [UnsubscribeController::class, 'oneClick'])
    ->middleware('signed')
    ->where('category', '[A-Za-z0-9_.-]+')
    ->name(Unsubscribe::ONE_CLICK_ROUTE);
