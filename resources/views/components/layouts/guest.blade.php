@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @fonts
</head>

<body class="min-h-screen elevation-1">
    <main class="flex min-h-screen items-center justify-center px-4">
        {{ $slot }}
    </main>
</body>

</html>
