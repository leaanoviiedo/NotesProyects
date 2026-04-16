{{-- ======================== SIDEBAR ======================== --}}
{{-- Reusable sidebar partial — requires x-data="{ sidebarOpen: false }" on a parent element --}}
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

        @auth
        @php
            $sidebarPersonalProject = auth()->user()->getOrCreatePersonalProject();

            // Non-personal, non-archived projects the user owns or is a member of
            $sidebarProjects = \App\Models\Project::where(function ($q) {
                $q->where('owner_id', auth()->id())
                  ->orWhereHas('members', fn ($sq) => $sq->where('user_id', auth()->id()));
            })->where('is_archived', false)
              ->where('is_personal', false)
              ->orderByDesc('is_favorite')
              ->orderBy('name')
              ->get(['id','name','color','is_personal','is_favorite']);

            // Saved projects (via share link, not a full member)
            $sidebarSavedProjects = \App\Models\SavedProject::where('user_id', auth()->id())
                ->with([
                    'project' => fn($q) => $q->select('id','name','color','is_archived')
                                            ->where('is_archived', false),
                    'shareLink:token,can_kanban,can_notes',
                ])
                ->get()
                ->filter(fn($s) => $s->project !== null);

            $sidebarActiveProjectId = (int) request()->query('projectId', 0);
            $sidebarPersonalMode = (request()->routeIs('kanban') || request()->routeIs('notes'))
                && ($sidebarActiveProjectId === 0 || $sidebarActiveProjectId === $sidebarPersonalProject->id);
            $sidebarProjectActive = $sidebarActiveProjectId > 0
                && $sidebarActiveProjectId !== $sidebarPersonalProject->id;
            $currentShareToken = request()->query('token', '');
        @endphp

        {{-- ---- PROJECTS accordion ---- --}}
        <div x-data="{ projectsOpen: {{ request()->routeIs('projects*') || $sidebarProjectActive ? 'true' : 'false' }} }">
            <button @click="projectsOpen = !projectsOpen"
                class="w-full flex items-center justify-between gap-3 px-3 py-2 rounded-xl {{ request()->routeIs('projects*') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-xl shrink-0 {{ request()->routeIs('projects*') ? 'text-indigo-400' : 'text-slate-500' }}">folder_open</span>
                    <span class="text-sm">Projects</span>
                </div>
                <span class="material-symbols-outlined text-base text-slate-600 transition-transform duration-200"
                      :class="projectsOpen ? 'rotate-90' : ''">chevron_right</span>
            </button>
            <div x-show="projectsOpen" x-cloak class="mt-1 ml-2 space-y-0.5">
                @forelse($sidebarProjects as $proj)
                @php
                    $projIsActive = $sidebarProjectActive && $sidebarActiveProjectId === $proj->id;
                    $projKanbanActive = $projIsActive && request()->routeIs('kanban');
                    $projNotesActive  = $projIsActive && request()->routeIs('notes');
                @endphp
                <div x-data="{ subOpen: {{ $projIsActive ? 'true' : 'false' }} }">
                    <button @click="subOpen = !subOpen"
                        class="w-full flex items-center justify-between gap-2 pl-4 pr-2 py-1.5 rounded-xl {{ $projIsActive ? 'text-white bg-indigo-600/20' : 'text-slate-400' }} hover:bg-slate-800 hover:text-slate-200 transition-colors">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $proj->color ?? '#6366f1' }}"></span>
                            <span class="text-xs truncate {{ $projIsActive ? 'font-semibold' : '' }}">{{ $proj->name }}</span>
                            @if($proj->is_favorite)<span class="text-[9px] text-amber-400 shrink-0">★</span>@endif
                        </div>
                        <span class="material-symbols-outlined text-xs text-slate-600 transition-transform duration-200 shrink-0"
                              :class="subOpen ? 'rotate-90' : ''">chevron_right</span>
                    </button>
                    <div x-show="subOpen" x-cloak class="ml-6 mt-0.5 space-y-0.5">
                        <a href="{{ route('kanban', ['projectId' => $proj->id]) }}"
                           @click="sidebarOpen = false"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg {{ $projKanbanActive ? 'text-indigo-400 bg-indigo-600/10 font-medium' : 'text-slate-500 hover:bg-slate-800 hover:text-slate-300' }} transition-colors text-[11px]">
                            <span class="material-symbols-outlined text-sm">view_kanban</span> Kanban
                        </a>
                        <a href="{{ route('notes', ['projectId' => $proj->id]) }}"
                           @click="sidebarOpen = false"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg {{ $projNotesActive ? 'text-indigo-400 bg-indigo-600/10 font-medium' : 'text-slate-500 hover:bg-slate-800 hover:text-slate-300' }} transition-colors text-[11px]">
                            <span class="material-symbols-outlined text-sm">description</span> Notes
                        </a>
                    </div>
                </div>
                @empty
                <p class="pl-4 py-1 text-[11px] text-slate-600 italic">No projects yet.</p>
                @endforelse
                <a href="{{ route('projects') }}" @click="sidebarOpen = false"
                    class="flex items-center gap-2 pl-4 pr-2 py-1.5 rounded-xl text-indigo-400/80 hover:bg-slate-800 hover:text-indigo-300 transition-colors text-xs font-medium">
                    <span class="material-symbols-outlined text-sm">apps</span> All Projects
                </a>
            </div>
        </div>

        {{-- ---- SAVED PROJECTS ---- --}}
        @if($sidebarSavedProjects->isNotEmpty())
        <div x-data="{ savedOpen: {{ $sidebarSavedProjects->contains(fn($s) => request()->routeIs('share.public') && $currentShareToken === $s->share_token) ? 'true' : 'false' }} }">
            <button @click="savedOpen = !savedOpen"
                class="w-full flex items-center justify-between gap-3 px-3 py-2 rounded-xl {{ request()->routeIs('share.public') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-xl shrink-0 {{ request()->routeIs('share.public') ? 'text-indigo-400' : 'text-slate-500' }}">bookmarks</span>
                    <span class="text-sm">Saved</span>
                </div>
                <span class="material-symbols-outlined text-base text-slate-600 transition-transform duration-200"
                      :class="savedOpen ? 'rotate-90' : ''">chevron_right</span>
            </button>
            <div x-show="savedOpen" x-cloak class="mt-1 ml-2 space-y-0.5">
                @foreach($sidebarSavedProjects as $saved)
                @php
                    $sp = $saved->project;
                    $spActive = request()->routeIs('share.public') && $currentShareToken === $saved->share_token;
                    $spCanKanban = $saved->shareLink?->can_kanban ?? true;
                    $spCanNotes  = $saved->shareLink?->can_notes  ?? true;
                    $currentTab  = request()->query('tab', 'kanban');
                @endphp
                <div x-data="{ subOpen: {{ $spActive ? 'true' : 'false' }} }">
                    <button @click="subOpen = !subOpen"
                        class="w-full flex items-center justify-between gap-2 pl-4 pr-2 py-1.5 rounded-xl {{ $spActive ? 'text-white bg-indigo-600/20' : 'text-slate-400' }} hover:bg-slate-800 hover:text-slate-200 transition-colors">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $sp->color ?? '#6366f1' }}"></span>
                            <span class="text-xs truncate {{ $spActive ? 'font-semibold' : '' }}">{{ $sp->name }}</span>
                            <span class="material-symbols-outlined text-[11px] text-slate-600 shrink-0">bookmark</span>
                        </div>
                        <span class="material-symbols-outlined text-xs text-slate-600 transition-transform duration-200 shrink-0"
                              :class="subOpen ? 'rotate-90' : ''">chevron_right</span>
                    </button>
                    @if($saved->share_token)
                    <div x-show="subOpen" x-cloak class="ml-6 mt-0.5 space-y-0.5">
                        @if($spCanKanban)
                        <a href="{{ route('share.public', ['token' => $saved->share_token, 'tab' => 'kanban']) }}"
                           @click="sidebarOpen = false"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg {{ $spActive && $currentTab !== 'notes' ? 'text-indigo-400 bg-indigo-600/10 font-medium' : 'text-slate-500 hover:bg-slate-800 hover:text-slate-300' }} transition-colors text-[11px]">
                            <span class="material-symbols-outlined text-sm">view_kanban</span> Kanban
                        </a>
                        @endif
                        @if($spCanNotes)
                        <a href="{{ route('share.public', ['token' => $saved->share_token, 'tab' => 'notes']) }}"
                           @click="sidebarOpen = false"
                           class="flex items-center gap-2 px-3 py-1 rounded-lg {{ $spActive && $currentTab === 'notes' ? 'text-indigo-400 bg-indigo-600/10 font-medium' : 'text-slate-500 hover:bg-slate-800 hover:text-slate-300' }} transition-colors text-[11px]">
                            <span class="material-symbols-outlined text-sm">description</span> Notes
                        </a>
                        @endif
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ---- PERSONAL section ---- --}}
        <div class="pt-4 pb-1.5 px-3">
            <span class="text-slate-600 font-label text-[10px] uppercase tracking-widest">Personal</span>
        </div>
        <a href="{{ route('kanban', ['projectId' => $sidebarPersonalProject->id]) }}"
           @click="sidebarOpen = false"
           class="flex items-center gap-3 px-3 py-2 rounded-xl {{ $sidebarPersonalMode && request()->routeIs('kanban') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
            <span class="material-symbols-outlined text-xl shrink-0 {{ $sidebarPersonalMode && request()->routeIs('kanban') ? 'text-indigo-400' : 'text-slate-500' }}">view_kanban</span>
            <span class="text-sm">My Kanban</span>
        </a>
        <a href="{{ route('notes', ['projectId' => $sidebarPersonalProject->id]) }}"
           @click="sidebarOpen = false"
           class="flex items-center gap-3 px-3 py-2 rounded-xl {{ $sidebarPersonalMode && request()->routeIs('notes') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
            <span class="material-symbols-outlined text-xl shrink-0 {{ $sidebarPersonalMode && request()->routeIs('notes') ? 'text-indigo-400' : 'text-slate-500' }}">description</span>
            <span class="text-sm">My Notes</span>
        </a>
        @endauth

        {{-- Calendar (personal, no auth needed for the link itself) --}}
        <a href="{{ route('calendar') }}"
           @click="sidebarOpen = false"
           class="flex items-center gap-3 px-3 py-2 rounded-xl {{ request()->routeIs('calendar') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
            <span class="material-symbols-outlined text-xl shrink-0 {{ request()->routeIs('calendar') ? 'text-indigo-400' : 'text-slate-500' }}">calendar_month</span>
            <span class="text-sm">Calendar</span>
        </a>

        {{-- ---- TOOLS section ---- --}}
        <div class="pt-4 pb-1.5 px-3">
            <span class="text-slate-600 font-label text-[10px] uppercase tracking-widest">Tools</span>
        </div>
        <a href="{{ route('api-tester') }}"
           @click="sidebarOpen = false"
           class="flex items-center gap-3 px-3 py-2 rounded-xl {{ request()->routeIs('api-tester') ? 'text-white font-semibold bg-indigo-600/20' : 'text-slate-400 font-medium' }} hover:bg-slate-800 transition-colors">
            <span class="material-symbols-outlined text-xl shrink-0 {{ request()->routeIs('api-tester') ? 'text-indigo-400' : 'text-slate-500' }}">api</span>
            <span class="text-sm">API Tester</span>
        </a>
    </nav>

    {{-- Footer --}}
    <div class="mt-4 space-y-1 shrink-0">
        @auth
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
        @else
        <a href="{{ route('login') }}" class="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 px-4 rounded-xl transition-all active:scale-95 mb-2 shadow-lg shadow-indigo-600/20">
            <span class="material-symbols-outlined text-xl">login</span>
            <span class="text-sm">Sign in</span>
        </a>
        <a href="{{ route('register') }}" class="w-full flex items-center justify-center gap-2 border border-slate-700 text-slate-400 font-medium py-2.5 px-4 rounded-xl hover:bg-slate-800 transition-all">
            <span class="material-symbols-outlined text-xl">person_add</span>
            <span class="text-sm">Create account</span>
        </a>
        @endauth
    </div>
</aside>
