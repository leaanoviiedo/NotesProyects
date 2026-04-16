<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-background text-on-background min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-indigo-600 font-label font-bold text-2xl tracking-tighter">DevOS Pro</h1>
            <p class="text-on-surface-variant text-sm mt-1">Programmer Dashboard</p>
        </div>
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
