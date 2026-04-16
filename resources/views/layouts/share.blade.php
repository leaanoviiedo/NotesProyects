<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Shared Project — DevOS Pro' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-background text-on-background overflow-hidden">

@auth
{{-- ============================================================
     AUTHENTICATED: full app shell with sidebar
     ============================================================ --}}
<div class="h-screen flex overflow-hidden" x-data="{ sidebarOpen: false }">

    {{-- Mobile overlay --}}
    <div
        x-show="sidebarOpen"
        x-cloak
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="sidebarOpen = false"
        class="fixed inset-0 bg-slate-900/60 z-40 lg:hidden"
    ></div>

    @include('partials.sidebar')

    {{-- Main --}}
    <main class="flex-1 flex flex-col min-w-0 lg:ml-64">

        {{-- Slim header for share view --}}
        <header class="bg-white/90 backdrop-blur-xl h-14 sticky top-0 z-40 flex items-center gap-4 px-4 sm:px-6 border-b border-slate-100/80 shrink-0">
            <button @click="sidebarOpen = !sidebarOpen"
                class="lg:hidden p-2 text-slate-500 hover:bg-surface-container-low rounded-lg transition-colors shrink-0">
                <span class="material-symbols-outlined text-xl">menu</span>
            </button>
            <span class="flex items-center gap-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-100 shrink-0">
                <span class="material-symbols-outlined text-sm">share</span> Shared view
            </span>
            <a href="{{ route('dashboard') }}"
               class="ml-auto flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-900 transition-colors shrink-0">
                <span class="material-symbols-outlined text-sm">arrow_back</span> Back to app
            </a>
            <div class="h-7 w-7 rounded-full overflow-hidden bg-indigo-500 flex items-center justify-center shrink-0">
                <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-full h-full object-cover">
            </div>
        </header>

        {{-- Content --}}
        <div class="flex-1 bg-surface overflow-hidden">
            {{ $slot }}
        </div>
    </main>

</div>

@else
{{-- ============================================================
     GUEST: minimal layout — no sidebar
     ============================================================ --}}
<div class="h-screen flex flex-col overflow-hidden">

    {{-- Simple header --}}
    <header class="bg-white border-b border-slate-200 h-14 flex items-center justify-between px-6 shrink-0 z-40">
        <div class="flex items-center gap-2">
            <span class="text-indigo-600 font-bold text-base tracking-tight">DevOS Pro</span>
            <span class="text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-full ml-1">Shared view</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('login') }}"
               class="text-sm text-slate-600 hover:text-slate-900 font-medium transition-colors">Sign in</a>
            <a href="{{ route('register') }}"
               class="text-sm bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-1.5 rounded-full font-medium transition-colors shadow-sm shadow-indigo-600/20">
                Sign up free
            </a>
        </div>
    </header>

    {{-- Content --}}
    <div class="flex-1 bg-surface overflow-hidden">
        {{ $slot }}
    </div>

</div>
@endauth

@livewireScripts
</body>
</html>
