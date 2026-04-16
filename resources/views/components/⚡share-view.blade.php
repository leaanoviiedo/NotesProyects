<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\ShareLink;
use App\Models\SavedProject;
use App\Models\KanbanColumn;
use App\Models\Note;

new #[Layout('layouts.share')] class extends Component {

    #[Url]
    public string $token = '';

    #[Url]
    public string $activeTab = 'kanban';

    // Share link meta
    public bool $canKanban = false;
    public bool $canNotes  = false;

    // Project displayed
    public array $project = [];

    // Content
    public array $columns   = [];
    public array $notes     = [];
    public ?int  $activeNoteId = null;

    // Save state (only relevant when auth'd)
    public bool $isSaved  = false;
    public bool $canSave  = false;

    public function mount(): void
    {
        if (empty($this->token)) {
            abort(404);
        }

        $share = ShareLink::where('token', $this->token)
            ->with('project')
            ->firstOrFail();

        if (!$share->isValid()) {
            abort(410, 'This share link has expired or been revoked.');
        }

        $this->canKanban = $share->can_kanban;
        $this->canNotes  = $share->can_notes;
        $this->project   = $share->project->only(['id','name','description','color','icon']);

        // Validate tab against what's enabled
        if ($this->activeTab === 'notes' && !$this->canNotes) {
            $this->activeTab = 'kanban';
        }
        if ($this->activeTab === 'kanban' && !$this->canKanban) {
            $this->activeTab = $this->canNotes ? 'notes' : 'kanban';
        }

        // Save state for authenticated users
        if (auth()->check()) {
            $user = auth()->user();
            $this->canSave = !$share->project->isMember($user);
            if ($this->canSave) {
                $this->isSaved = SavedProject::where('user_id', $user->id)
                    ->where('project_id', $share->project->id)
                    ->exists();
            }
        }

        $this->loadContent();
    }

    public function loadContent(): void
    {
        $projectId = $this->project['id'];

        if ($this->activeTab === 'kanban' && $this->canKanban) {
            $this->columns = KanbanColumn::where('project_id', $projectId)
                ->with(['cards' => fn($q) => $q->orderBy('position')])
                ->orderBy('position')
                ->get()
                ->toArray();
            $this->notes = [];
        } elseif ($this->activeTab === 'notes' && $this->canNotes) {
            $this->notes = Note::where('project_id', $projectId)
                ->orderByDesc('is_pinned')
                ->orderByDesc('updated_at')
                ->get()
                ->toArray();
            $this->columns = [];
        }
    }

    public function switchTab(string $tab): void
    {
        if ($tab === 'kanban' && !$this->canKanban) return;
        if ($tab === 'notes'  && !$this->canNotes)  return;
        $this->activeTab = $tab;
        $this->activeNoteId = null;
        $this->loadContent();
    }

    public function viewNote(int $id): void
    {
        $this->activeNoteId = $id;
    }

    public function saveProject(): void
    {
        if (!auth()->check() || !$this->canSave) return;
        SavedProject::firstOrCreate(
            ['user_id' => auth()->id(), 'project_id' => $this->project['id']],
            ['share_token' => $this->token]
        );
        $this->isSaved = true;
    }

    public function unsaveProject(): void
    {
        if (!auth()->check()) return;
        SavedProject::where('user_id', auth()->id())
            ->where('project_id', $this->project['id'])
            ->delete();
        $this->isSaved = false;
    }

    public function getListeners(): array
    {
        $projectId = $this->project['id'] ?? 0;
        return [
            "echo:project.{$projectId}.public,.kanban.updated" => 'loadContent',
            "echo:project.{$projectId}.public,.note.updated"   => 'loadContent',
        ];
    }
};
?>
<div class="h-full flex flex-col bg-surface">

    {{-- Project header + tab switcher + save button --}}
    <div class="shrink-0 border-b border-outline-variant/30 bg-surface-container-low px-4 sm:px-6 py-3 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3 min-w-0">
            <span class="w-4 h-4 rounded-full shrink-0" style="background-color: {{ $project['color'] ?? '#6366f1' }}"></span>
            <div class="min-w-0">
                <h1 class="font-bold text-on-background text-base truncate">{{ $project['name'] }}</h1>
                @if(!empty($project['description']))
                <p class="text-xs text-on-surface-variant truncate">{{ $project['description'] }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0 flex-wrap">
            {{-- Tab switcher (only when both tabs are available) --}}
            @if($canKanban && $canNotes)
            <div class="flex rounded-xl overflow-hidden border border-outline-variant text-sm">
                <button wire:click="switchTab('kanban')"
                    class="px-3 py-1.5 {{ $activeTab === 'kanban' ? 'bg-primary text-on-primary font-medium' : 'text-on-surface-variant hover:bg-surface-container-high' }} transition">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">view_kanban</span> Kanban</span>
                </button>
                <button wire:click="switchTab('notes')"
                    class="px-3 py-1.5 {{ $activeTab === 'notes' ? 'bg-primary text-on-primary font-medium' : 'text-on-surface-variant hover:bg-surface-container-high' }} transition">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">description</span> Notes</span>
                </button>
            </div>
            @endif

            {{-- Save / Saved button (only for auth'd non-members) --}}
            @auth
            @if($canSave)
                @if($isSaved)
                <button wire:click="unsaveProject"
                    class="group flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600/10 text-indigo-600 text-sm font-medium hover:bg-error-container/30 hover:text-error transition">
                    <span class="material-symbols-outlined text-base group-hover:hidden">bookmark</span>
                    <span class="material-symbols-outlined text-base hidden group-hover:inline">bookmark_remove</span>
                    <span class="group-hover:hidden">Saved</span>
                    <span class="hidden group-hover:inline">Remove</span>
                </button>
                @else
                <button wire:click="saveProject"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-container-high text-on-surface text-sm font-medium hover:bg-indigo-600/10 hover:text-indigo-600 transition border border-outline-variant/50">
                    <span class="material-symbols-outlined text-base">bookmark_add</span> Save project
                </button>
                @endif
            @endif
            @else
            {{-- Guest: prompt to sign in to save --}}
            <a href="{{ route('login') }}"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 transition shadow-sm shadow-indigo-600/20">
                <span class="material-symbols-outlined text-base">bookmark_add</span> Sign in to save
            </a>
            @endauth
        </div>
    </div>

    {{-- Read-only content area --}}
    <div class="flex-1 overflow-hidden min-h-0">

        @if($activeTab === 'kanban' && $canKanban)
        {{-- ======== READ-ONLY KANBAN ======== --}}
        <div class="h-full overflow-auto p-4 sm:p-6">
            @if(empty($columns))
            <div class="flex items-center justify-center h-full text-on-surface-variant">
                <div class="text-center">
                    <span class="material-symbols-outlined text-5xl block mb-3">view_kanban</span>
                    <p>No columns yet.</p>
                </div>
            </div>
            @else
            <div class="flex gap-4 h-full items-start" style="min-width: max-content;">
                @foreach($columns as $column)
                <div class="flex flex-col w-72 shrink-0">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-3 h-3 rounded-full" style="background-color: {{ $column['color'] }}"></span>
                        <span class="font-semibold text-sm text-on-surface">{{ $column['name'] }}</span>
                        <span class="bg-surface-container-high px-2 py-0.5 rounded text-[10px] font-bold text-on-surface-variant ml-auto">
                            {{ count($column['cards']) }}
                        </span>
                    </div>
                    <div class="flex-1 bg-surface-container-low rounded-xl p-2.5 space-y-2.5 min-h-24">
                        @forelse($column['cards'] as $card)
                        <div class="bg-surface-container-lowest rounded-xl p-3 shadow-sm border-l-4"
                            style="border-color: {{ $card['label_color'] ?? $column['color'] }}">
                            @if($card['label'])
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wide inline-block mb-1.5"
                                style="background-color: {{ $card['label_color'] }}22; color: {{ $card['label_color'] }}">
                                {{ $card['label'] }}
                            </span>
                            @endif
                            <p class="text-sm font-semibold text-on-background leading-snug">{{ $card['title'] }}</p>
                            @if($card['description'])
                            <p class="text-xs text-on-surface-variant mt-1 line-clamp-2">{{ $card['description'] }}</p>
                            @endif
                            <div class="flex items-center gap-2 mt-2 flex-wrap">
                                @if($card['due_date'])
                                <span class="flex items-center gap-0.5 text-[10px] text-on-surface-variant">
                                    <span class="material-symbols-outlined text-xs">calendar_today</span>
                                    {{ \Carbon\Carbon::parse($card['due_date'])->format('M j') }}
                                </span>
                                @endif
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium
                                    {{ $card['priority'] == 3 ? 'bg-error-container/40 text-error' : ($card['priority'] == 2 ? 'bg-tertiary-container/40 text-tertiary' : 'bg-surface-container-high text-on-surface-variant') }}">
                                    {{ $card['priority'] == 3 ? 'High' : ($card['priority'] == 2 ? 'Med' : 'Low') }}
                                </span>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4 text-on-surface-variant/50 text-xs">Empty column</div>
                        @endforelse
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        @elseif($activeTab === 'notes' && $canNotes)
        {{-- ======== READ-ONLY NOTES ======== --}}
        <div class="h-full flex min-h-0">
            {{-- Notes list --}}
            <aside class="w-72 flex flex-col border-r border-outline-variant/30 bg-surface-container shrink-0 overflow-y-auto">
                @forelse($notes as $note)
                <button wire:click="viewNote({{ $note['id'] }})"
                    class="w-full text-left px-4 py-3 border-b border-outline-variant/20 hover:bg-surface-container-high transition
                        {{ $activeNoteId === $note['id'] ? 'bg-primary/5 border-l-2 border-l-primary' : '' }}">
                    <p class="font-semibold text-sm text-on-surface truncate flex items-center gap-1">
                        @if($note['is_pinned'])<span class="material-symbols-outlined text-xs text-primary">push_pin</span>@endif
                        {{ $note['title'] }}
                    </p>
                    <p class="text-xs text-on-surface-variant mt-0.5 line-clamp-2">
                        {{ html_entity_decode(strip_tags($note['content'] ?? 'No content')) }}
                    </p>
                    <div class="flex items-center gap-2 mt-1">
                        @if($note['category'])
                        <span class="text-[10px] bg-primary/10 text-primary px-1.5 py-0.5 rounded-full">{{ $note['category'] }}</span>
                        @endif
                        <span class="text-[10px] text-on-surface-variant">
                            {{ \Carbon\Carbon::parse($note['updated_at'])->diffForHumans() }}
                        </span>
                    </div>
                </button>
                @empty
                <div class="text-center py-12 text-on-surface-variant">
                    <span class="material-symbols-outlined text-4xl block mb-2">notes</span>
                    <p class="text-sm">No notes yet.</p>
                </div>
                @endforelse
            </aside>

            {{-- Note content viewer (read-only) --}}
            <main class="flex-1 overflow-y-auto">
                @if($activeNoteId)
                    @php $activeNote = collect($notes)->firstWhere('id', $activeNoteId); @endphp
                    @if($activeNote)
                    <div class="px-6 sm:px-10 py-8 max-w-3xl">
                        <h1 class="text-2xl font-bold text-on-background mb-2">{{ $activeNote['title'] }}</h1>
                        @if($activeNote['category'])
                        <span class="text-xs bg-primary/10 text-primary px-2.5 py-0.5 rounded-full mb-5 inline-block">{{ $activeNote['category'] }}</span>
                        @endif
                        <p class="text-xs text-on-surface-variant mb-6">
                            Updated {{ \Carbon\Carbon::parse($activeNote['updated_at'])->diffForHumans() }}
                        </p>
                        <div class="text-sm text-on-surface ProseMirror">
                            {!! $activeNote['content'] !!}
                        </div>
                    </div>
                    @endif
                @else
                <div class="flex items-center justify-center h-full text-on-surface-variant">
                    <div class="text-center">
                        <span class="material-symbols-outlined text-4xl block mb-2">arrow_back</span>
                        <p class="text-sm">Select a note to read it</p>
                    </div>
                </div>
                @endif
            </main>
        </div>

        @else
        <div class="flex items-center justify-center h-full text-on-surface-variant">
            <p>Nothing to display.</p>
        </div>
        @endif

    </div>
</div>
