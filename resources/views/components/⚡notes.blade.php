<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\Project;
use App\Models\Note;
use App\Events\NoteUpdated;

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

    // Real-time collaboration
    public array $onlineUsers = [];
    public array $focusMap = [];      // [(string)userId => noteId] — who's editing which note
    public int   $editorContentVersion = 0; // increments to tell JS to reload editor
    public string $collabNotice = '';       // shown when a collaborator updates your active note

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
        // Default to the user's personal project (create it if it doesn't exist yet)
        if (!$this->projectId) {
            $this->projectId = auth()->user()->getOrCreatePersonalProject()->id;
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
        $this->collabNotice = '';
        $this->focusMap = [];
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
        $this->collabNotice = '';
        $this->editorContentVersion++;
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
        $this->collabNotice = '';
        $this->editorContentVersion++;
        // Let collaborators know which note we're editing
        if ($this->projectId) {
            broadcast(new NoteUpdated($this->projectId, 'focus', ['note_id' => $id], auth()->id(), auth()->user()->name));
        }
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
        if ($this->projectId && $this->activeNoteId) {
            broadcast(new NoteUpdated($this->projectId, 'saved', [
                'note_id' => $this->activeNoteId,
            ], auth()->id(), auth()->user()->name));
        }
    }

    public function deleteNote(int $id): void
    {
        Note::where('project_id', $this->projectId)->findOrFail($id)->delete();
        if ($this->activeNoteId === $id) {
            $this->activeNoteId = null;
            $this->isEditing = false;
        }
        $this->loadNotes();
        if ($this->projectId) {
            broadcast(new NoteUpdated($this->projectId, 'deleted', ['note_id' => $id], auth()->id(), auth()->user()->name));
        }
    }

    public function togglePin(int $id): void
    {
        $note = Note::where('project_id', $this->projectId)->findOrFail($id);
        $note->update(['is_pinned' => !$note->is_pinned]);
        if ($this->activeNoteId === $id) $this->noteIsPinned = !$this->noteIsPinned;
        $this->loadNotes();
    }

    // ── Real-time ──────────────────────────────────────────────────────────────

    public function getListeners(): array
    {
        $id = $this->projectId ?? 0;
        return [
            'echo-presence:project.' . $id . '.notes,here'    => 'hereUsers',
            'echo-presence:project.' . $id . '.notes,joining' => 'userJoined',
            'echo-presence:project.' . $id . '.notes,leaving' => 'userLeft',
            'echo:project.' . $id . '.public,.note.updated'   => 'onNoteUpdated',
        ];
    }

    public function hereUsers(array $users): void { $this->onlineUsers = $users; }
    public function userJoined(array $user): void { $this->onlineUsers[] = $user; }
    public function userLeft(array $user): void
    {
        $this->onlineUsers = array_values(array_filter($this->onlineUsers, fn($u) => $u['id'] !== $user['id']));
        unset($this->focusMap[(string) $user['id']]);
    }

    public function onNoteUpdated(array $event): void
    {
        $action     = $event['action'] ?? '';
        $payload    = $event['payload'] ?? [];
        $senderId   = (int) ($event['userId'] ?? 0);
        $senderName = $event['userName'] ?? 'Someone';

        // Ignore own events (same user, possibly another tab)
        if ($senderId === auth()->id()) return;

        // Track which note each collaborator has open
        if ($action === 'focus') {
            $this->focusMap[(string) $senderId] = (int) ($payload['note_id'] ?? 0);
            return;
        }

        // Refresh note list for create / save / delete events
        $this->loadNotes();

        // If the collaborator saved the very note we have open, reload its content
        if ($action === 'saved' && !empty($payload['note_id']) && (int) $payload['note_id'] === $this->activeNoteId) {
            $note = Note::find($this->activeNoteId);
            if ($note) {
                $this->noteContent    = $note->content ?? '';
                $this->noteTitle      = $note->title;
                $this->collabNotice   = $senderName . ' updated this note';
                $this->editorContentVersion++;
            }
        }
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
        {{-- Online collaborators --}}
        @if(count($onlineUsers) > 0)
        <div class="flex items-center gap-1.5">
            <span class="text-xs text-on-surface-variant">En línea:</span>
            <div class="flex -space-x-1.5">
                @foreach($onlineUsers as $u)
                <span class="w-6 h-6 rounded-full bg-primary text-on-primary text-[9px] font-bold flex items-center justify-center border-2 border-surface cursor-default"
                      title="{{ $u['name'] }}">{{ $u['initials'] ?? '' }}</span>
                @endforeach
            </div>
        </div>
        @endif
        {{-- Mobile panel toggle --}}
        <div class="md:hidden flex gap-2 ml-auto">
            <button @click="mobilePanel = 'list'"
                :class="mobilePanel === 'list' ? 'bg-primary/10 text-primary' : 'text-on-surface-variant'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium">
                <span class="material-symbols-outlined text-base align-middle">list</span> Notas
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
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar notas..."
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
                @php
                    $noteEditors = collect($onlineUsers)->filter(
                        fn($u) => isset($focusMap[(string)$u['id']]) && $focusMap[(string)$u['id']] === $note['id']
                    );
                @endphp
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
                                {{ html_entity_decode(strip_tags($note['content'] ?? 'Sin contenido')) }}
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
                        {{-- Collaborator editing indicators --}}
                        @if($noteEditors->isNotEmpty())
                        <div class="flex -space-x-1 shrink-0 mt-0.5">
                            @foreach($noteEditors as $u)
                            <span class="w-4 h-4 rounded-full bg-tertiary text-on-tertiary text-[8px] font-bold flex items-center justify-center border border-surface"
                                  title="{{ $u['name'] }} is editing">{{ $u['initials'] ?? '' }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </button>
                @empty
                <div class="text-center py-12 text-on-surface-variant">
                    <span class="material-symbols-outlined text-4xl block mb-2">notes</span>
                    <p class="text-sm">Sin notas aún.</p>
                    <button wire:click="newNote" @click="mobilePanel = 'editor'" class="text-primary text-sm hover:underline mt-1">Crear una</button>
                </div>
                @endforelse
            </div>
        </aside>

        {{-- RIGHT: Editor --}}
        <main class="flex-1 flex flex-col min-w-0"
            :class="mobilePanel === 'editor' ? 'flex' : 'hidden md:flex'">
            @if($isEditing)
            {{-- Collab notice: shown when a collaborator saves the note you have open --}}
            @if($collabNotice)
            <div class="flex items-center justify-between px-4 py-2 bg-amber-500/10 text-amber-700 dark:text-amber-400 text-xs border-b border-amber-500/20 shrink-0">
                <span class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">sync</span>
                    {{ $collabNotice }}
                </span>
                <button wire:click="$set('collabNotice','')" class="text-amber-600/70 hover:text-amber-600">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
            @endif
            <div class="flex items-center justify-between px-4 sm:px-6 py-3 border-b border-outline-variant/30 shrink-0 gap-3">
                <input wire:model.blur="noteTitle" type="text" placeholder="Título de la nota..."
                    class="flex-1 text-lg font-bold bg-transparent border-none focus:outline-none text-on-background placeholder:text-on-surface-variant/40" />
                <div class="flex items-center gap-2 shrink-0">
                    @if($activeNoteId)
                    <button wire:click="togglePin({{ $activeNoteId }})"
                        class="p-1.5 rounded-lg hover:bg-surface-container-high {{ $noteIsPinned ? 'text-primary' : 'text-on-surface-variant' }}">
                        <span class="material-symbols-outlined text-base">push_pin</span>
                    </button>
                    <button wire:click="deleteNote({{ $activeNoteId }})" wire:confirm="¿Eliminar esta nota?"
                        class="p-1.5 rounded-lg hover:bg-error-container/30 text-on-surface-variant hover:text-error">
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                    @endif
                    <button wire:click="saveNote"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">
                        <span class="material-symbols-outlined text-base">save</span> Guardar
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-3 px-4 sm:px-6 py-2 border-b border-outline-variant/20 shrink-0">
                <input wire:model.blur="noteCategory" type="text" placeholder="Categoría (opcional)"
                    class="text-xs bg-transparent border border-outline-variant rounded-full px-3 py-1 focus:outline-none focus:ring-1 focus:ring-primary text-on-surface-variant" />
                <label class="flex items-center gap-1.5 text-xs text-on-surface-variant cursor-pointer">
                    <input wire:model="noteIsPinned" type="checkbox" class="accent-primary"> Fijada
                </label>
            </div>

            {{-- TipTap rich text editor --}}
            <div wire:ignore
                 x-data="tiptap"
                 class="flex-1 flex flex-col overflow-hidden min-h-0">

                {{-- Toolbar --}}
                <div class="flex flex-wrap items-center gap-0.5 px-3 py-1.5 border-b border-outline-variant/20 bg-surface-container/50 shrink-0">
                    {{-- Headings --}}
                    <button @mousedown.prevent="run(c => c.toggleHeading({ level: 1 }))"
                        :class="active.h1 ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="px-2 py-1 rounded text-xs font-bold transition" title="Heading 1">H1</button>
                    <button @mousedown.prevent="run(c => c.toggleHeading({ level: 2 }))"
                        :class="active.h2 ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="px-2 py-1 rounded text-xs font-bold transition" title="Heading 2">H2</button>
                    <button @mousedown.prevent="run(c => c.toggleHeading({ level: 3 }))"
                        :class="active.h3 ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="px-2 py-1 rounded text-xs font-bold transition" title="Heading 3">H3</button>

                    <span class="w-px h-4 bg-outline-variant mx-1"></span>

                    {{-- Inline formatting --}}
                    <button @mousedown.prevent="run(c => c.toggleBold())"
                        :class="active.bold ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Bold">
                        <span class="material-symbols-outlined text-sm">format_bold</span>
                    </button>
                    <button @mousedown.prevent="run(c => c.toggleItalic())"
                        :class="active.italic ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Italic">
                        <span class="material-symbols-outlined text-sm">format_italic</span>
                    </button>
                    <button @mousedown.prevent="run(c => c.toggleStrike())"
                        :class="active.strike ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Strikethrough">
                        <span class="material-symbols-outlined text-sm">strikethrough_s</span>
                    </button>
                    <button @mousedown.prevent="run(c => c.toggleCode())"
                        :class="active.code ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Inline Code">
                        <span class="material-symbols-outlined text-sm">code</span>
                    </button>

                    <span class="w-px h-4 bg-outline-variant mx-1"></span>

                    {{-- Lists --}}
                    <button @mousedown.prevent="run(c => c.toggleBulletList())"
                        :class="active.bulletList ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Bullet List">
                        <span class="material-symbols-outlined text-sm">format_list_bulleted</span>
                    </button>
                    <button @mousedown.prevent="run(c => c.toggleOrderedList())"
                        :class="active.orderedList ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Ordered List">
                        <span class="material-symbols-outlined text-sm">format_list_numbered</span>
                    </button>
                    <button @mousedown.prevent="run(c => c.toggleBlockquote())"
                        :class="active.blockquote ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Blockquote">
                        <span class="material-symbols-outlined text-sm">format_quote</span>
                    </button>
                    <button @mousedown.prevent="run(c => c.toggleCodeBlock())"
                        :class="active.codeBlock ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:bg-surface-container-high'"
                        class="p-1.5 rounded transition" title="Code Block">
                        <span class="material-symbols-outlined text-sm">data_object</span>
                    </button>

                    <span class="w-px h-4 bg-outline-variant mx-1"></span>

                    {{-- History --}}
                    <button @mousedown.prevent="run(c => c.undo())"
                        class="p-1.5 rounded text-on-surface-variant hover:bg-surface-container-high transition" title="Undo">
                        <span class="material-symbols-outlined text-sm">undo</span>
                    </button>
                    <button @mousedown.prevent="run(c => c.redo())"
                        class="p-1.5 rounded text-on-surface-variant hover:bg-surface-container-high transition" title="Redo">
                        <span class="material-symbols-outlined text-sm">redo</span>
                    </button>
                    <button @mousedown.prevent="run(c => c.setHorizontalRule())"
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
                    <p class="text-sm mb-3">Selecciona una nota o crea una nueva</p>
                    <button wire:click="newNote"
                        class="px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">
                        Nueva Nota
                    </button>
                </div>
            </div>
            @endif
        </main>
    </div>
</div>
