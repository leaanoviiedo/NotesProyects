<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\Project;
use App\Models\Note;

new #[Layout('layouts.app')] class extends Component {
    #[Url(as: 'projectId')]
    public ?int $projectId = null;

    public $projects = [];
    public $notes = [];
    public ?int $activeNoteId = null;
    public string $search = '';

    // Editor
    public string $noteTitle = '';
    public string $noteContent = '';
    public string $noteCategory = '';
    public bool $noteIsPinned = false;
    public bool $isEditing = false;

    public function mount(): void
    {
        $this->loadProjects();
        // If projectId came from URL, validate user has access
        if ($this->projectId) {
            $ids = collect($this->projects)->pluck('id');
            if (!$ids->contains($this->projectId)) {
                $this->projectId = null;
            }
        }
        if (!$this->projectId && count($this->projects)) {
            $personal = collect($this->projects)->firstWhere('is_personal', true);
            $this->projectId = $personal ? $personal['id'] : $this->projects[0]['id'];
        }
        $this->loadNotes();
    }

    public function loadProjects(): void
    {
        $user = auth()->user();
        $ids = Project::where('owner_id', $user->id)
            ->orWhereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->pluck('id');
        $this->projects = Project::whereIn('id', $ids)
            ->orderByDesc('is_personal')
            ->orderByDesc('is_favorite')
            ->orderBy('name')
            ->get(['id','name','color','icon','is_personal','is_favorite'])
            ->toArray();
    }

    public function updatedProjectId(): void
    {
        $this->activeNoteId = null;
        $this->isEditing = false;
        $this->loadNotes();
    }

    public function updatedSearch(): void
    {
        $this->loadNotes();
    }

    public function loadNotes(): void
    {
        if (!$this->projectId) { $this->notes = []; return; }
        $q = Note::where('project_id', $this->projectId);
        if ($this->search) {
            $q->where(fn($sq) => $sq->where('title', 'like', '%'.$this->search.'%')
                ->orWhere('content', 'like', '%'.$this->search.'%'));
        }
        $this->notes = $q->orderByDesc('is_pinned')->orderByDesc('updated_at')->get()->toArray();
    }

    public function newNote(): void
    {
        $this->activeNoteId = null;
        $this->noteTitle = '';
        $this->noteContent = '';
        $this->noteCategory = '';
        $this->noteIsPinned = false;
        $this->isEditing = true;
    }

    public function selectNote(int $id): void
    {
        $note = Note::where('project_id', $this->projectId)->findOrFail($id);
        $this->activeNoteId = $id;
        $this->noteTitle = $note->title;
        $this->noteContent = $note->content ?? '';
        $this->noteCategory = $note->category ?? '';
        $this->noteIsPinned = $note->is_pinned;
        $this->isEditing = true;
    }

    public function saveNote(): void
    {
        $this->validate(['noteTitle' => 'required|string|max:255']);
        if ($this->activeNoteId) {
            Note::where('project_id', $this->projectId)->findOrFail($this->activeNoteId)->update([
                'title' => $this->noteTitle,
                'content' => $this->noteContent,
                'category' => $this->noteCategory ?: null,
                'is_pinned' => $this->noteIsPinned,
            ]);
        } else {
            $note = Note::create([
                'project_id' => $this->projectId,
                'user_id' => auth()->id(),
                'title' => $this->noteTitle,
                'content' => $this->noteContent,
                'category' => $this->noteCategory ?: null,
                'is_pinned' => $this->noteIsPinned,
            ]);
            $this->activeNoteId = $note->id;
        }
        $this->loadNotes();
    }

    public function deleteNote(int $id): void
    {
        Note::where('project_id', $this->projectId)->findOrFail($id)->delete();
        if ($this->activeNoteId === $id) {
            $this->activeNoteId = null;
            $this->isEditing = false;
        }
        $this->loadNotes();
    }

    public function togglePin(int $id): void
    {
        $note = Note::where('project_id', $this->projectId)->findOrFail($id);
        $note->update(['is_pinned' => !$note->is_pinned]);
        if ($this->activeNoteId === $id) $this->noteIsPinned = !$this->noteIsPinned;
        $this->loadNotes();
    }
};
?>
<div class="h-full flex flex-col bg-surface" x-data="{ mobilePanel: 'list' }">

    {{-- Project selector bar --}}
    <div class="flex items-center gap-3 px-4 sm:px-6 py-3 border-b border-outline-variant/30 shrink-0 flex-wrap">
        <select wire:model.live="projectId"
            class="rounded-xl border border-outline-variant bg-surface-container px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary min-w-[160px]">
            @foreach($projects as $p)
            <option value="{{ $p['id'] }}">
                {{ $p['is_personal'] ? '👤 ' : '' }}{{ $p['name'] }}{{ $p['is_favorite'] ? ' ★' : '' }}
            </option>
            @endforeach
        </select>
        {{-- Mobile panel toggle --}}
        <div class="md:hidden flex gap-2 ml-auto">
            <button @click="mobilePanel = 'list'"
                :class="mobilePanel === 'list' ? 'bg-primary/10 text-primary' : 'text-on-surface-variant'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium">
                <span class="material-symbols-outlined text-base align-middle">list</span> Notes
            </button>
            <button @click="mobilePanel = 'editor'"
                :class="mobilePanel === 'editor' ? 'bg-primary/10 text-primary' : 'text-on-surface-variant'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium">
                <span class="material-symbols-outlined text-base align-middle">edit_note</span> Editor
            </button>
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden min-h-0">

        {{-- LEFT: Notes list --}}
        <aside class="w-full md:w-72 lg:w-80 flex flex-col border-r border-outline-variant/30 bg-surface-container shrink-0"
            :class="mobilePanel === 'list' ? 'flex' : 'hidden md:flex'">
            {{-- Search + New --}}
            <div class="p-3 border-b border-outline-variant/30 flex gap-2">
                <div class="relative flex-1">
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm">search</span>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search notes..."
                        class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-outline-variant bg-surface-container-low text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <button wire:click="newNote" @click="mobilePanel = 'editor'"
                    class="flex items-center justify-center w-9 h-9 bg-primary text-on-primary rounded-xl hover:bg-primary/90 transition shrink-0">
                    <span class="material-symbols-outlined text-base">add</span>
                </button>
            </div>
            {{-- Note list --}}
            <div class="flex-1 overflow-y-auto">
                @forelse($notes as $note)
                <button wire:click="selectNote({{ $note['id'] }})" @click="mobilePanel = 'editor'"
                    class="w-full text-left px-4 py-3 border-b border-outline-variant/20 hover:bg-surface-container-high transition
                        {{ $activeNoteId === $note['id'] ? 'bg-primary/5 border-l-2 border-l-primary' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
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
                        </div>
                    </div>
                </button>
                @empty
                <div class="text-center py-12 text-on-surface-variant">
                    <span class="material-symbols-outlined text-4xl block mb-2">notes</span>
                    <p class="text-sm">No notes yet.</p>
                    <button wire:click="newNote" @click="mobilePanel = 'editor'" class="text-primary text-sm hover:underline mt-1">Create one</button>
                </div>
                @endforelse
            </div>
        </aside>

        {{-- RIGHT: Editor --}}
        <main class="flex-1 flex flex-col min-w-0"
            :class="mobilePanel === 'editor' ? 'flex' : 'hidden md:flex'">
            @if($isEditing)
            <div class="flex items-center justify-between px-4 sm:px-6 py-3 border-b border-outline-variant/30 shrink-0 gap-3">
                <input wire:model.blur="noteTitle" type="text" placeholder="Note title..."
                    class="flex-1 text-lg font-bold bg-transparent border-none focus:outline-none text-on-background placeholder:text-on-surface-variant/40" />
                <div class="flex items-center gap-2 shrink-0">
                    @if($activeNoteId)
                    <button wire:click="togglePin({{ $activeNoteId }})"
                        class="p-1.5 rounded-lg hover:bg-surface-container-high {{ $noteIsPinned ? 'text-primary' : 'text-on-surface-variant' }}">
                        <span class="material-symbols-outlined text-base">push_pin</span>
                    </button>
                    <button wire:click="deleteNote({{ $activeNoteId }})" wire:confirm="Delete this note?"
                        class="p-1.5 rounded-lg hover:bg-error-container/30 text-on-surface-variant hover:text-error">
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                    @endif
                    <button wire:click="saveNote"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">
                        <span class="material-symbols-outlined text-base">save</span> Save
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 sm:px-6 py-2 border-b border-outline-variant/20 shrink-0">
                <input wire:model.blur="noteCategory" type="text" placeholder="Category (optional)"
                    class="text-xs bg-transparent border border-outline-variant rounded-full px-3 py-1 focus:outline-none focus:ring-1 focus:ring-primary text-on-surface-variant" />
                <label class="flex items-center gap-1.5 text-xs text-on-surface-variant cursor-pointer">
                    <input wire:model="noteIsPinned" type="checkbox" class="accent-primary"> Pinned
                </label>
            </div>

            {{-- TipTap rich text editor --}}
            <div wire:ignore
                 x-data="tiptap"
                 class="flex-1 flex flex-col overflow-hidden min-h-0">

                {{-- Toolbar --}}
                <div class="flex flex-wrap items-center gap-0.5 px-3 py-1.5 border-b border-outline-variant/20 bg-surface-container/50 shrink-0">
                    {{-- Headings --}}
                    <button @click.prevent="run(c => c.toggleHeading({ level: 1 }).run())"
                        :class="active.h1 ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="px-2 py-1 rounded text-xs font-bold transition" title="Heading 1">H1</button>
                    <button @click.prevent="run(c => c.toggleHeading({ level: 2 }).run())"
                        :class="active.h2 ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="px-2 py-1 rounded text-xs font-bold transition" title="Heading 2">H2</button>
                    <button @click.prevent="run(c => c.toggleHeading({ level: 3 }).run())"
                        :class="active.h3 ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="px-2 py-1 rounded text-xs font-bold transition" title="Heading 3">H3</button>

                    <span class="w-px h-4 bg-outline-variant mx-1"></span>

                    {{-- Inline formatting --}}
                    <button @click.prevent="run(c => c.toggleBold().run())"
                        :class="active.bold ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Bold">
                        <span class="material-symbols-outlined text-sm">format_bold</span>
                    </button>
                    <button @click.prevent="run(c => c.toggleItalic().run())"
                        :class="active.italic ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Italic">
                        <span class="material-symbols-outlined text-sm">format_italic</span>
                    </button>
                    <button @click.prevent="run(c => c.toggleStrike().run())"
                        :class="active.strike ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Strikethrough">
                        <span class="material-symbols-outlined text-sm">strikethrough_s</span>
                    </button>
                    <button @click.prevent="run(c => c.toggleCode().run())"
                        :class="active.code ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Inline Code">
                        <span class="material-symbols-outlined text-sm">code</span>
                    </button>

                    <span class="w-px h-4 bg-outline-variant mx-1"></span>

                    {{-- Lists --}}
                    <button @click.prevent="run(c => c.toggleBulletList().run())"
                        :class="active.bulletList ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Bullet List">
                        <span class="material-symbols-outlined text-sm">format_list_bulleted</span>
                    </button>
                    <button @click.prevent="run(c => c.toggleOrderedList().run())"
                        :class="active.orderedList ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Ordered List">
                        <span class="material-symbols-outlined text-sm">format_list_numbered</span>
                    </button>
                    <button @click.prevent="run(c => c.toggleBlockquote().run())"
                        :class="active.blockquote ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Blockquote">
                        <span class="material-symbols-outlined text-sm">format_quote</span>
                    </button>
                    <button @click.prevent="run(c => c.toggleCodeBlock().run())"
                        :class="active.codeBlock ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Code Block">
                        <span class="material-symbols-outlined text-sm">data_object</span>
                    </button>

                    <span class="w-px h-4 bg-outline-variant mx-1"></span>

                    {{-- History --}}
                    <button @click.prevent="run(c => c.undo().run())"
                        class="p-1.5 rounded text-on-surface-variant hover:bg-surface-container-high transition" title="Undo">
                        <span class="material-symbols-outlined text-sm">undo</span>
                    </button>
                    <button @click.prevent="run(c => c.redo().run())"
                        class="p-1.5 rounded text-on-surface-variant hover:bg-surface-container-high transition" title="Redo">
                        <span class="material-symbols-outlined text-sm">redo</span>
                    </button>
                    <button @click.prevent="run(c => c.setHorizontalRule().run())"
                        class="p-1.5 rounded text-on-surface-variant hover:bg-surface-container-high transition" title="Horizontal Rule">
                        <span class="material-symbols-outlined text-sm">horizontal_rule</span>
                    </button>
                </div>

                {{-- Editor content area --}}
                <div class="flex-1 overflow-y-auto text-sm text-on-surface leading-relaxed"
                     x-ref="editorContent"></div>
            </div>
            @else
            <div class="flex-1 flex items-center justify-center text-on-surface-variant">
                <div class="text-center">
                    <span class="material-symbols-outlined text-5xl block mb-3">edit_note</span>
                    <p class="text-sm mb-3">Select a note or create a new one</p>
                    <button wire:click="newNote"
                        class="px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">
                        New Note
                    </button>
                </div>
            </div>
            @endif
        </main>
    </div>
</div>
