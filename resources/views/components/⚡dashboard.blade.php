<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Project;
use App\Models\ActivityLog;
use App\Models\KanbanCard;
use App\Models\Note;

new #[Layout('layouts.app')] class extends Component {
    public $projects;
    public $recentActivity;
    public int $totalCards = 0;
    public int $totalNotes = 0;

    public function mount(): void
    {
        $user = auth()->user();
        $projectIds = Project::where('owner_id', $user->id)
            ->orWhereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        // Favorites first, then personal, then by name
        $this->projects = Project::whereIn('id', $projectIds)
            ->withCount(['kanbanCards', 'notes'])
            ->orderByDesc('is_favorite')
            ->orderByDesc('is_personal')
            ->orderBy('name')
            ->take(6)
            ->get();

        $this->totalCards = KanbanCard::whereIn('project_id', $projectIds)->count();
        $this->totalNotes = Note::whereIn('project_id', $projectIds)->count();
        $this->recentActivity = ActivityLog::whereIn('project_id', $projectIds)
            ->with('user')->latest()->take(10)->get();
    }
};
?>
<div class="p-4 md:p-6 space-y-6">
    {{-- Welcome Header --}}
    <div class="flex items-center gap-4">
        <img src="{{ auth()->user()->avatar_url }}" class="w-12 h-12 rounded-full object-cover ring-2 ring-primary/30" alt="Avatar">
        <div>
            <h1 class="text-xl font-bold text-on-background">Welcome back, {{ auth()->user()->name }} 👋</h1>
            <p class="text-sm text-on-surface-variant">{{ now()->format('l, F j, Y') }}</p>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-primary-container rounded-2xl p-4 flex flex-col gap-1">
            <span class="material-symbols-outlined text-primary text-2xl">folder_open</span>
            <span class="text-2xl font-bold text-on-primary-container">{{ $projects->count() }}</span>
            <span class="text-xs text-on-primary-container/70">Projects</span>
        </div>
        <div class="bg-secondary-container rounded-2xl p-4 flex flex-col gap-1">
            <span class="material-symbols-outlined text-secondary text-2xl">view_kanban</span>
            <span class="text-2xl font-bold text-on-secondary-container">{{ $totalCards }}</span>
            <span class="text-xs text-on-secondary-container/70">Kanban Cards</span>
        </div>
        <div class="bg-tertiary-container rounded-2xl p-4 flex flex-col gap-1">
            <span class="material-symbols-outlined text-tertiary text-2xl">notes</span>
            <span class="text-2xl font-bold text-on-tertiary-container">{{ $totalNotes }}</span>
            <span class="text-xs text-on-tertiary-container/70">Notes</span>
        </div>
        <div class="bg-surface-container rounded-2xl p-4 flex flex-col gap-1">
            <span class="material-symbols-outlined text-on-surface-variant text-2xl">calendar_month</span>
            <span class="text-sm font-semibold text-on-surface">
                {{ auth()->user()->google_token ? 'Connected' : 'Not connected' }}
            </span>
            <span class="text-xs text-on-surface-variant">Google Calendar</span>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        {{-- Recent Projects --}}
        <div class="bg-surface-container rounded-2xl p-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-on-background">Recent Projects</h2>
                <a href="{{ route('projects') }}" wire:navigate class="text-xs text-primary hover:underline">View all</a>
            </div>
            @forelse($projects as $project)
            <a href="{{ route('kanban') }}" wire:navigate
                class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface-container-high transition mb-1">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center text-base font-bold text-white shrink-0"
                    style="background-color: {{ $project->color ?? '#3525cd' }}">
                    <span class="material-symbols-outlined text-lg">{{ $project->icon ?? 'folder' }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-sm text-on-surface truncate flex items-center gap-1">
                        {{ $project->name }}
                        @if($project->is_favorite)<span class="text-amber-400 text-xs">★</span>@endif
                        @if($project->is_personal)<span class="text-[10px] text-on-surface-variant bg-surface-container-high px-1.5 rounded-full">Personal</span>@endif
                    </p>
                    <p class="text-xs text-on-surface-variant">{{ $project->kanban_cards_count }} cards · {{ $project->notes_count }} notes</p>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant text-lg">chevron_right</span>
            </a>
            @empty
            <div class="text-center py-8 text-on-surface-variant">
                <span class="material-symbols-outlined text-4xl block mb-2">folder_open</span>
                <p class="text-sm">No projects yet.</p>
                <a href="{{ route('projects') }}" wire:navigate class="text-primary text-sm hover:underline">Create one</a>
            </div>
            @endforelse
        </div>

        {{-- Recent Activity --}}
        <div class="bg-surface-container rounded-2xl p-4">
            <h2 class="font-semibold text-on-background mb-4">Recent Activity</h2>
            @forelse($recentActivity as $log)
            <div class="flex items-start gap-3 mb-3">
                <div class="w-7 h-7 rounded-full bg-primary/20 flex items-center justify-center text-xs font-bold text-primary shrink-0 mt-0.5">
                    {{ $log->user ? $log->user->initials : '?' }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-on-surface">{{ $log->description }}</p>
                    <p class="text-xs text-on-surface-variant">{{ $log->created_at->diffForHumans() }}</p>
                </div>
            </div>
            @empty
            <div class="text-center py-8 text-on-surface-variant">
                <span class="material-symbols-outlined text-4xl block mb-2">history</span>
                <p class="text-sm">No activity yet.</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Quick Actions --}}
    <div>
        <h2 class="font-semibold text-on-background mb-3">Quick Actions</h2>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('kanban') }}" wire:navigate
                class="flex items-center gap-2 px-4 py-2.5 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90 transition">
                <span class="material-symbols-outlined text-base">view_kanban</span> Open Kanban
            </a>
            <a href="{{ route('notes') }}" wire:navigate
                class="flex items-center gap-2 px-4 py-2.5 bg-secondary-container text-on-secondary-container rounded-xl text-sm font-medium hover:bg-secondary-container/80 transition">
                <span class="material-symbols-outlined text-base">notes</span> Open Notes
            </a>
            <a href="{{ route('projects') }}" wire:navigate
                class="flex items-center gap-2 px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl text-sm font-medium hover:bg-surface-container-highest transition">
                <span class="material-symbols-outlined text-base">add</span> New Project
            </a>
        </div>
    </div>
</div>
