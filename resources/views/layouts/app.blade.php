<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="bg-background text-on-background overflow-hidden">

<div class="h-screen flex overflow-hidden" x-data="{ sidebarOpen: false }">

    {{-- Mobile sidebar overlay --}}
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

    {{-- ======================== MAIN AREA ======================== --}}
    <main class="flex-1 flex flex-col min-w-0 lg:ml-64">

        {{-- ---- TOP HEADER ---- --}}
        <header class="bg-white/90 backdrop-blur-xl h-16 sticky top-0 z-40 flex items-center justify-between px-4 sm:px-6 lg:px-8 border-b border-slate-100/80 shrink-0">
            <div class="flex items-center gap-3 sm:gap-4 flex-1 min-w-0">
                {{-- Hamburger (mobile/tablet) --}}
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-2 text-slate-500 hover:bg-surface-container-low rounded-lg transition-colors shrink-0"
                >
                    <span class="material-symbols-outlined text-xl">menu</span>
                </button>

                {{-- Search bar --}}
                <div class="relative hidden sm:block w-full max-w-xs">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
                    <input
                        class="w-full bg-surface-container-low border-none rounded-full pl-10 pr-4 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all"
                        placeholder="Global search commands..."
                        type="text"
                    />
                </div>

                {{-- Tab navigation (md+) --}}
                <nav class="hidden md:flex items-center gap-1 shrink-0">
                    <a href="{{ route('dashboard') }}"
                       class="px-3 lg:px-4 h-16 flex items-center text-sm font-medium hover:text-slate-900 transition-all whitespace-nowrap {{ request()->routeIs('dashboard') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500' }}">
                        Home
                    </a>
                    <a href="{{ route('kanban') }}"
                       class="px-3 lg:px-4 h-16 flex items-center text-sm font-medium hover:text-slate-900 transition-all whitespace-nowrap {{ request()->routeIs('kanban') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500' }}">
                        Kanban
                    </a>
                    <a href="{{ route('notes') }}"
                       class="px-3 lg:px-4 h-16 flex items-center text-sm font-medium hover:text-slate-900 transition-all {{ request()->routeIs('notes') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500' }}">
                        Notes
                    </a>
                    <a href="{{ route('calendar') }}"
                       class="px-3 lg:px-4 h-16 flex items-center text-sm font-medium hover:text-slate-900 transition-all {{ request()->routeIs('calendar') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500' }}">
                        Calendar
                    </a>
                    <a href="{{ route('api-tester') }}"
                       class="px-3 lg:px-4 h-16 flex items-center gap-1 text-sm font-medium hover:text-slate-900 transition-all whitespace-nowrap {{ request()->routeIs('api-tester') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500' }}">
                        <span class="material-symbols-outlined text-base">api</span> API
                    </a>
                    <a href="{{ route('logs') }}"
                       class="px-3 lg:px-4 h-16 flex items-center gap-1 text-sm font-medium hover:text-slate-900 transition-all whitespace-nowrap {{ request()->routeIs('logs') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500' }}">
                        <span class="material-symbols-outlined text-base">terminal</span> Logs
                    </a>
                    <a href="{{ route('snippets') }}"
                       class="px-3 lg:px-4 h-16 flex items-center gap-1 text-sm font-medium hover:text-slate-900 transition-all whitespace-nowrap {{ request()->routeIs('snippets') ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500' }}">
                        <span class="material-symbols-outlined text-base">code_blocks</span> Snippets
                    </a>
                </nav>

                {{-- Current page label (mobile) --}}
                <span class="md:hidden font-semibold text-sm text-on-background truncate">
                    @if(request()->routeIs('kanban')) Kanban
                    @elseif(request()->routeIs('notes')) Notes
                    @elseif(request()->routeIs('calendar')) Calendar
                    @elseif(request()->routeIs('api-tester')) API Tester
                    @elseif(request()->routeIs('logs')) Log Console
                    @elseif(request()->routeIs('snippets')) Snippets
                    @else DevOS Pro
                    @endif
                </span>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <div class="hidden sm:flex items-center gap-1 pr-3 border-r border-slate-100">
                    <button class="p-2 text-slate-400 hover:bg-surface-container-low rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-xl">history</span>
                    </button>
                    <button class="p-2 text-slate-400 hover:bg-surface-container-low rounded-lg transition-colors relative">
                        <span class="material-symbols-outlined text-xl">notifications</span>
                        <span class="absolute top-2 right-2 w-2 h-2 bg-indigo-500 rounded-full border-2 border-white"></span>
                    </button>
                </div>
                <button class="hidden sm:block bg-primary text-on-primary px-4 lg:px-5 py-1.5 rounded-full text-sm font-semibold shadow-md shadow-indigo-600/20 hover:scale-105 transition-transform active:scale-95">
                    Deploy
                </button>
                <div class="h-8 w-8 rounded-full overflow-hidden bg-indigo-500 flex items-center justify-center shrink-0">
                    @auth
                    <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-full h-full object-cover">
                    @else
                    <span class="text-white text-sm font-bold">?</span>
                    @endauth
                </div>
            </div>
        </header>

        {{-- ---- CONTENT AREA ---- --}}
        <div class="flex-1 bg-surface overflow-hidden pb-14 md:pb-0">
            {{ $slot }}
        </div>

        {{-- ---- MOBILE BOTTOM NAV ---- --}}
        <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur border-t border-slate-200 z-40 flex items-stretch">
            <a href="{{ route('kanban') }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ request()->routeIs('kanban') ? 'text-indigo-600' : 'text-slate-400' }}">
                <span class="material-symbols-outlined text-xl">view_kanban</span>
                <span class="text-[10px] font-medium">Kanban</span>
            </a>
            <a href="{{ route('notes') }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ request()->routeIs('notes') ? 'text-indigo-600' : 'text-slate-400' }}">
                <span class="material-symbols-outlined text-xl">description</span>
                <span class="text-[10px] font-medium">Notes</span>
            </a>
            <a href="{{ route('calendar') }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ request()->routeIs('calendar') ? 'text-indigo-600' : 'text-slate-400' }}">
                <span class="material-symbols-outlined text-xl">calendar_month</span>
                <span class="text-[10px] font-medium">Calendar</span>
            </a>
            <a href="{{ route('api-tester') }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ request()->routeIs('api-tester') ? 'text-indigo-600' : 'text-slate-400' }}">
                <span class="material-symbols-outlined text-xl">api</span>
                <span class="text-[10px] font-medium">API</span>
            </a>
            <a href="{{ route('logs') }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ request()->routeIs('logs') ? 'text-indigo-600' : 'text-slate-400' }}">
                <span class="material-symbols-outlined text-xl">terminal</span>
                <span class="text-[10px] font-medium">Logs</span>
            </a>
            <a href="{{ route('snippets') }}"
               class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 {{ request()->routeIs('snippets') ? 'text-indigo-600' : 'text-slate-400' }}">
                <span class="material-symbols-outlined text-xl">code_blocks</span>
                <span class="text-[10px] font-medium">Snippets</span>
            </a>
        </nav>

    </main>

</div>

@livewireScripts
</body>
</html>
