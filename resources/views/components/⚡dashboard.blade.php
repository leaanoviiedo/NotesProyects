<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Project;
use App\Models\ActivityLog;
use App\Models\KanbanCard;
use App\Models\Note;
use App\Models\KanbanColumn;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {
    public $projects;
    public $recentActivity;
    public int $totalCards   = 0;
    public int $totalNotes   = 0;
    public int $dueThisWeek  = 0;
    public int $totalProjects = 0;

    public function mount(): void
    {
        $user = auth()->user();
        $projectIds = Project::where('owner_id', $user->id)
            ->orWhereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $this->projects = Project::whereIn('id', $projectIds)
            ->withCount(['kanbanCards', 'notes', 'members'])
            ->with('owner')
            ->where('is_archived', false)
            ->orderByDesc('is_favorite')
            ->orderByDesc('is_personal')
            ->orderBy('name')
            ->take(8)
            ->get();

        $this->totalProjects = $projectIds->count();
        $this->totalCards    = KanbanCard::whereIn('project_id', $projectIds)->count();
        $this->totalNotes    = Note::whereIn('project_id', $projectIds)->count();
        $this->dueThisWeek   = KanbanCard::whereIn('project_id', $projectIds)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->startOfDay(), now()->endOfWeek()])
            ->count();
        $this->recentActivity = ActivityLog::whereIn('project_id', $projectIds)
            ->with(['user', 'project'])->latest()->take(12)->get();
    }
};
?>
<div class="min-h-full bg-surface">

    {{-- ═══════════════════ HERO HEADER ═══════════════════ --}}
    <div class="bg-gradient-to-br from-primary/10 via-surface to-surface border-b border-outline-variant/20 px-4 md:px-8 py-6">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <img src="{{ auth()->user()->avatar_url }}"
                     class="w-14 h-14 rounded-2xl object-cover ring-2 ring-primary/40 shadow-lg" alt="Avatar">
                <div>
                    <p class="text-xs font-medium text-primary uppercase tracking-widest">{{ now()->format('l') }}</p>
                    <h1 class="text-2xl font-bold text-on-background leading-tight">Welcome back, {{ auth()->user()->name }}</h1>
                    <p class="text-sm text-on-surface-variant">{{ now()->format('F j, Y') }}</p>
                </div>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('kanban') }}" wire:navigate
                   class="flex items-center gap-2 px-4 py-2.5 bg-primary text-on-primary rounded-xl text-sm font-semibold hover:bg-primary/90 transition shadow-md shadow-primary/20">
                    <span class="material-symbols-outlined text-base">view_kanban</span> Kanban
                </a>
                <a href="{{ route('notes') }}" wire:navigate
                   class="flex items-center gap-2 px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-container-highest transition">
                    <span class="material-symbols-outlined text-base">edit_note</span> Notes
                </a>
                <a href="{{ route('calendar') }}" wire:navigate
                   class="flex items-center gap-2 px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-container-highest transition">
                    <span class="material-symbols-outlined text-base">calendar_month</span> Calendar
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 md:px-8 py-6 space-y-8">

        {{-- ═══════════════════ STATS ═══════════════════ --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-surface-container rounded-2xl p-5 flex items-center gap-4 border border-outline-variant/20 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-primary text-xl">folder_open</span>
                </div>
                <div>
                    <p class="text-2xl font-bold text-on-background">{{ $totalProjects }}</p>
                    <p class="text-xs text-on-surface-variant">Projects</p>
                </div>
            </div>
            <div class="bg-surface-container rounded-2xl p-5 flex items-center gap-4 border border-outline-variant/20 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-xl bg-secondary/10 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-secondary text-xl">view_kanban</span>
                </div>
                <div>
                    <p class="text-2xl font-bold text-on-background">{{ $totalCards }}</p>
                    <p class="text-xs text-on-surface-variant">Kanban Cards</p>
                </div>
            </div>
            <div class="bg-surface-container rounded-2xl p-5 flex items-center gap-4 border border-outline-variant/20 hover:shadow-md transition">
                <div class="w-11 h-11 rounded-xl bg-tertiary/10 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-tertiary text-xl">description</span>
                </div>
                <div>
                    <p class="text-2xl font-bold text-on-background">{{ $totalNotes }}</p>
                    <p class="text-xs text-on-surface-variant">Notes</p>
                </div>
            </div>
            <div class="bg-surface-container rounded-2xl p-5 flex items-center gap-4 border border-outline-variant/20 hover:shadow-md transition
                {{ $dueThisWeek > 0 ? 'border-amber-500/30 bg-amber-500/5' : '' }}">
                <div class="w-11 h-11 rounded-xl {{ $dueThisWeek > 0 ? 'bg-amber-500/15' : 'bg-surface-container-high' }} flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined {{ $dueThisWeek > 0 ? 'text-amber-500' : 'text-on-surface-variant' }} text-xl">event_upcoming</span>
                </div>
                <div>
                    <p class="text-2xl font-bold {{ $dueThisWeek > 0 ? 'text-amber-600' : 'text-on-background' }}">{{ $dueThisWeek }}</p>
                    <p class="text-xs text-on-surface-variant">Due this week</p>
                </div>
            </div>
        </div>

        {{-- ═══════════════════ TOOLS GRID ═══════════════════ --}}
        <div>
            <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-widest mb-3">Tools</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <a href="{{ route('kanban') }}" wire:navigate
                   class="group bg-surface-container rounded-2xl p-4 flex flex-col items-center gap-2 border border-outline-variant/20 hover:border-primary/40 hover:bg-primary/5 transition">
                    <span class="material-symbols-outlined text-3xl text-primary group-hover:scale-110 transition-transform">view_kanban</span>
                    <span class="text-xs font-semibold text-on-surface">Kanban</span>
                    <span class="text-[10px] text-on-surface-variant text-center leading-tight">Task boards &amp; cards</span>
                </a>
                <a href="{{ route('notes') }}" wire:navigate
                   class="group bg-surface-container rounded-2xl p-4 flex flex-col items-center gap-2 border border-outline-variant/20 hover:border-secondary/40 hover:bg-secondary/5 transition">
                    <span class="material-symbols-outlined text-3xl text-secondary group-hover:scale-110 transition-transform">edit_note</span>
                    <span class="text-xs font-semibold text-on-surface">Notes</span>
                    <span class="text-[10px] text-on-surface-variant text-center leading-tight">Rich text notes</span>
                </a>
                <a href="{{ route('calendar') }}" wire:navigate
                   class="group bg-surface-container rounded-2xl p-4 flex flex-col items-center gap-2 border border-outline-variant/20 hover:border-tertiary/40 hover:bg-tertiary/5 transition">
                    <span class="material-symbols-outlined text-3xl text-tertiary group-hover:scale-110 transition-transform">calendar_month</span>
                    <span class="text-xs font-semibold text-on-surface">Calendar</span>
                    <span class="text-[10px] text-on-surface-variant text-center leading-tight">Events &amp; schedule</span>
                </a>
                <a href="{{ route('projects') }}" wire:navigate
                   class="group bg-surface-container rounded-2xl p-4 flex flex-col items-center gap-2 border border-outline-variant/20 hover:border-primary/40 hover:bg-primary/5 transition">
                    <span class="material-symbols-outlined text-3xl text-on-surface-variant group-hover:text-primary group-hover:scale-110 transition-transform transition-colors">folder_special</span>
                    <span class="text-xs font-semibold text-on-surface">Projects</span>
                    <span class="text-[10px] text-on-surface-variant text-center leading-tight">Manage workspaces</span>
                </a>
                {{-- Coming soon: placeholder tools --}}
                <div class="relative group bg-surface-container rounded-2xl p-4 flex flex-col items-center gap-2 border border-dashed border-outline-variant/40 opacity-60 cursor-not-allowed">
                    <span class="material-symbols-outlined text-3xl text-on-surface-variant">query_stats</span>
                    <span class="text-xs font-semibold text-on-surface">Analytics</span>
                    <span class="text-[10px] text-on-surface-variant text-center leading-tight">Coming soon</span>
                    <span class="absolute -top-1.5 -right-1.5 text-[9px] bg-primary text-on-primary px-1.5 py-0.5 rounded-full font-bold">SOON</span>
                </div>
                <div class="relative group bg-surface-container rounded-2xl p-4 flex flex-col items-center gap-2 border border-dashed border-outline-variant/40 opacity-60 cursor-not-allowed">
                    <span class="material-symbols-outlined text-3xl text-on-surface-variant">smart_toy</span>
                    <span class="text-xs font-semibold text-on-surface">AI Assistant</span>
                    <span class="text-[10px] text-on-surface-variant text-center leading-tight">Coming soon</span>
                    <span class="absolute -top-1.5 -right-1.5 text-[9px] bg-primary text-on-primary px-1.5 py-0.5 rounded-full font-bold">SOON</span>
                </div>
            </div>
        </div>

        {{-- ═══════════════════ PROJECTS + ACTIVITY ═══════════════════ --}}
        <div class="grid lg:grid-cols-5 gap-6">

            {{-- Left: Projects (3/5 width) --}}
            <div class="lg:col-span-3 flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-widest">Active Projects</h2>
                    <a href="{{ route('projects') }}" wire:navigate class="text-xs text-primary hover:underline flex items-center gap-1">
                        View all <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </a>
                </div>
                @forelse($projects as $project)
                <div class="bg-surface-container rounded-2xl border border-outline-variant/20 p-4 hover:shadow-md transition group">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white shrink-0 shadow-sm"
                             style="background-color: {{ $project->color ?? '#3525cd' }}">
                            <span class="material-symbols-outlined text-lg">{{ $project->icon ?? 'folder' }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold text-sm text-on-surface truncate">{{ $project->name }}</p>
                                @if($project->is_favorite)<span class="text-amber-400 text-xs">★</span>@endif
                                @if($project->is_personal)
                                    <span class="text-[10px] text-on-surface-variant bg-surface-container-high px-1.5 py-0.5 rounded-full">Personal</span>
                                @endif
                            </div>
                            @if($project->description)
                            <p class="text-xs text-on-surface-variant mt-0.5 line-clamp-1">{{ $project->description }}</p>
                            @endif
                            {{-- Stats row --}}
                            <div class="flex items-center gap-3 mt-2 text-xs text-on-surface-variant">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">view_kanban</span>{{ $project->kanban_cards_count }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">description</span>{{ $project->notes_count }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">group</span>{{ $project->members_count }}
                                </span>
                            </div>
                            {{-- External links --}}
                            @if(!empty($project->links))
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                @foreach($project->links as $link)
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant hover:text-primary hover:bg-primary/10 transition border border-outline-variant/30">
                                    @php
                                        $icon = match(true) {
                                            str_contains($link['url'], 'github.com')  => 'code',
                                            str_contains($link['url'], 'figma.com')   => 'design_services',
                                            str_contains($link['url'], 'notion.so')   => 'article',
                                            str_contains($link['url'], 'jira')        => 'bug_report',
                                            str_contains($link['url'], 'linear.app')  => 'linear_scale',
                                            str_contains($link['url'], 'slack.com')   => 'forum',
                                            default                                    => $link['icon'] ?? 'link',
                                        };
                                    @endphp
                                    <span class="material-symbols-outlined text-[11px]">{{ $icon }}</span>
                                    {{ $link['label'] }}
                                </a>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        {{-- Actions --}}
                        <div class="flex gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition">
                            <a href="{{ route('kanban', ['projectId' => $project->id]) }}" wire:navigate
                               class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-primary/10 text-on-surface-variant hover:text-primary transition" title="Open Kanban">
                                <span class="material-symbols-outlined text-sm">view_kanban</span>
                            </a>
                            <a href="{{ route('notes', ['projectId' => $project->id]) }}" wire:navigate
                               class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-secondary/10 text-on-surface-variant hover:text-secondary transition" title="Open Notes">
                                <span class="material-symbols-outlined text-sm">description</span>
                            </a>
                            <a href="{{ route('projects.members', $project) }}" wire:navigate
                               class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-container-high text-on-surface-variant transition" title="Members">
                                <span class="material-symbols-outlined text-sm">group</span>
                            </a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="bg-surface-container rounded-2xl border border-dashed border-outline-variant/40 p-10 text-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-5xl block mb-3">folder_open</span>
                    <p class="text-sm mb-3">No projects yet.</p>
                    <a href="{{ route('projects') }}" wire:navigate
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">
                        <span class="material-symbols-outlined text-base">add</span> Create your first project
                    </a>
                </div>
                @endforelse
            </div>

            {{-- Right: Activity feed (2/5 width) --}}
            <div class="lg:col-span-2">
                <h2 class="text-sm font-semibold text-on-surface-variant uppercase tracking-widest mb-4">Recent Activity</h2>
                <div class="bg-surface-container rounded-2xl border border-outline-variant/20 overflow-hidden">
                    @forelse($recentActivity as $log)
                    <div class="flex items-start gap-3 px-4 py-3 border-b border-outline-variant/10 last:border-0 hover:bg-surface-container-high/50 transition">
                        <div class="w-7 h-7 rounded-full bg-primary/15 flex items-center justify-center text-[10px] font-bold text-primary shrink-0 mt-0.5">
                            {{ $log->user ? $log->user->initials : '?' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-on-surface leading-snug">{{ $log->description }}</p>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                @if($log->project)
                                <span class="text-[10px] text-on-surface-variant">{{ $log->project->name }}</span>
                                <span class="text-[10px] text-outline-variant">·</span>
                                @endif
                                <span class="text-[10px] text-on-surface-variant">{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-10 text-on-surface-variant">
                        <span class="material-symbols-outlined text-4xl block mb-2">history</span>
                        <p class="text-sm">No activity yet.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
