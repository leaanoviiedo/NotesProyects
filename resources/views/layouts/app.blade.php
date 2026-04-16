<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
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

    {{-- ======================== SIDEBAR ======================== --}}
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="bg-slate-900 w-64 fixed left-0 top-0 h-full flex flex-col py-6 px-4 shadow-2xl z-50 transition-transform duration-300 ease-in-out"
    >
        {{-- Brand --}}
        <div class="mb-10 px-2 shrink-0">
            <h1 class="text-indigo-400 font-bold tracking-tighter text-lg font-label">DevOS Pro</h1>
            <p class="text-slate-500 font-label text-[10px] uppercase tracking-widest mt-1">v2.4.0-stable</p>
        </div>

        {{-- Nav links --}}
        <nav class="flex-1 space-y-0.5 overflow-y-auto min-h-0">
            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}"
               @click="sidebarOpen = false"
               class="flex items-center gap-3 px-3 py-2 rounded-xl {{ request()->routeIs('dashboard') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
                <span class="material-symbols-outlined text-xl shrink-0 {{ request()->routeIs('dashboard') ? 'text-indigo-400' : 'text-slate-500' }}">home</span>
                <span class="text-sm">Dashboard</span>
            </a>

            {{-- Projects accordion --}}
            @auth
            @php
                $sidebarProjects = \App\Models\Project::where(function ($q) {
                    $q->where('owner_id', auth()->id())
                      ->orWhereHas('members', fn ($sq) => $sq->where('user_id', auth()->id()));
                })->where('is_archived', false)
                  ->orderByDesc('is_personal')
                  ->orderByDesc('is_favorite')
                  ->orderBy('name')
                  ->get(['id','name','color','is_personal','is_favorite']);
            @endphp
            <div x-data="{ projectsOpen: {{ request()->routeIs('projects*') || request()->routeIs('kanban') || request()->routeIs('notes') ? 'true' : 'true' }} }">
                {{-- Projects section header --}}
                <button @click="projectsOpen = !projectsOpen"
                    class="w-full flex items-center justify-between gap-3 px-3 py-2 rounded-xl {{ request()->routeIs('projects*') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-xl shrink-0 {{ request()->routeIs('projects*') ? 'text-indigo-400' : 'text-slate-500' }}">folder_open</span>
                        <span class="text-sm">Projects</span>
                    </div>
                    <span class="material-symbols-outlined text-base text-slate-600 transition-transform duration-200"
                          :class="projectsOpen ? 'rotate-90' : ''">chevron_right</span>
                </button>

                {{-- Project list --}}
                <div x-show="projectsOpen" x-cloak class="mt-1 ml-2 space-y-0.5">
                    @foreach($sidebarProjects as $proj)
                    <div x-data="{ subOpen: false }">
                        <button @click="subOpen = !subOpen"
                            class="w-full flex items-center justify-between gap-2 pl-4 pr-2 py-1.5 rounded-xl text-slate-400 hover:bg-slate-800 hover:text-slate-200 transition-colors">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $proj->color ?? '#6366f1' }}"></span>
                                <span class="text-xs truncate">{{ $proj->name }}</span>
                                @if($proj->is_personal)<span class="text-[9px] opacity-60 shrink-0">👤</span>@endif
                                @if($proj->is_favorite)<span class="text-[9px] text-amber-400 shrink-0">★</span>@endif
                            </div>
                            <span class="material-symbols-outlined text-xs text-slate-600 transition-transform duration-200 shrink-0"
                                  :class="subOpen ? 'rotate-90' : ''">chevron_right</span>
                        </button>
                        <div x-show="subOpen" x-cloak class="ml-6 mt-0.5 space-y-0.5">
                            <a href="{{ route('kanban', ['projectId' => $proj->id]) }}"
                               @click="sidebarOpen = false"
                               class="flex items-center gap-2 px-3 py-1 rounded-lg text-slate-500 hover:bg-slate-800 hover:text-slate-300 transition-colors text-[11px]">
                                <span class="material-symbols-outlined text-sm">view_kanban</span> Kanban
                            </a>
                            <a href="{{ route('notes', ['projectId' => $proj->id]) }}"
                               @click="sidebarOpen = false"
                               class="flex items-center gap-2 px-3 py-1 rounded-lg text-slate-500 hover:bg-slate-800 hover:text-slate-300 transition-colors text-[11px]">
                                <span class="material-symbols-outlined text-sm">description</span> Notes
                            </a>
                        </div>
                    </div>
                    @endforeach

                    {{-- All projects link --}}
                    <a href="{{ route('projects') }}" @click="sidebarOpen = false"
                        class="flex items-center gap-2 pl-4 pr-2 py-1.5 rounded-xl text-indigo-400/80 hover:bg-slate-800 hover:text-indigo-300 transition-colors text-xs font-medium">
                        <span class="material-symbols-outlined text-sm">apps</span> All Projects
                    </a>
                </div>
            </div>
            @endauth

            <div class="pt-4 pb-1.5 px-3">
                <span class="text-slate-600 font-label text-[10px] uppercase tracking-widest">Workspace</span>
            </div>

            <a href="{{ route('kanban') }}"
               @click="sidebarOpen = false"
               class="flex items-center gap-3 px-3 py-2 rounded-xl {{ request()->routeIs('kanban') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
                <span class="material-symbols-outlined text-xl shrink-0 {{ request()->routeIs('kanban') ? 'text-indigo-400' : 'text-slate-500' }}">view_kanban</span>
                <span class="text-sm">Kanban</span>
            </a>
            <a href="{{ route('notes') }}"
               @click="sidebarOpen = false"
               class="flex items-center gap-3 px-3 py-2 rounded-xl {{ request()->routeIs('notes') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
                <span class="material-symbols-outlined text-xl shrink-0 {{ request()->routeIs('notes') ? 'text-indigo-400' : 'text-slate-500' }}">description</span>
                <span class="text-sm">Notes</span>
            </a>
            <a href="{{ route('calendar') }}"
               @click="sidebarOpen = false"
               class="flex items-center gap-3 px-3 py-2 rounded-xl {{ request()->routeIs('calendar') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
                <span class="material-symbols-outlined text-xl shrink-0 {{ request()->routeIs('calendar') ? 'text-indigo-400' : 'text-slate-500' }}">calendar_month</span>
                <span class="text-sm">Calendar</span>
            </a>
        </nav>

        {{-- Footer actions --}}
        <div class="mt-4 space-y-1 shrink-0">
            <a href="{{ route('projects') }}" wire:navigate class="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 px-4 rounded-xl transition-all active:scale-95 mb-4 shadow-lg shadow-indigo-600/20">
                <span class="material-symbols-outlined text-xl">add</span>
                <span class="text-sm">New Project</span>
            </a>
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-3 py-2 rounded-xl text-slate-400 font-medium hover:bg-slate-800 transition-colors">
                <span class="material-symbols-outlined text-xl">settings</span>
                <span class="text-sm">Settings</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-slate-400 font-medium hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined text-slate-500 text-xl">logout</span>
                    <span class="text-sm">Logout</span>
                </button>
            </form>
        </div>
    </aside>

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
                </nav>

                {{-- Current page label (mobile) --}}
                <span class="md:hidden font-semibold text-sm text-on-background truncate">
                    @if(request()->routeIs('kanban')) Kanban
                    @elseif(request()->routeIs('notes')) Notes
                    @elseif(request()->routeIs('calendar')) Calendar
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
        </nav>

    </main>

</div>

@livewireScripts
</body>
</html>
