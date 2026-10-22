<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('filagora-notifications::messages.unsubscribed.title') }}</title>
</head>
<body style="font-family: system-ui, sans-serif; max-width: 40rem; margin: 3rem auto; padding: 0 1rem;">
    <h1>{{ __('filagora-notifications::messages.unsubscribed.title') }}</h1>
    <p>{{ __('filagora-notifications::messages.unsubscribed.message', ['label' => $label]) }}</p>
    @if ($manageUrl)
        <p><a href="{{ $manageUrl }}">{{ __('filagora-notifications::messages.unsubscribed.manage') }}</a></p>
    @endif
</body>
</html>
