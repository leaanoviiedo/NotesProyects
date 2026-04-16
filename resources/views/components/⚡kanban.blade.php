<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\Project;
use App\Models\KanbanColumn;
use App\Models\KanbanCard;
use App\Events\KanbanUpdated;

new #[Layout('layouts.app')] class extends Component {
    #[Url(as: 'projectId')]
    public ?int $projectId = null;

    public $projects = [];
    public $columns = [];
    public array $onlineUsers = [];

    // Card modal
    public bool $showCardModal = false;
    public ?int $addingToColumnId = null;
    public string $cardTitle = '';
    public string $cardDescription = '';
    public string $cardLabel = '';
    public string $cardLabelColor = '#4f46e5';
    public int $cardPriority = 1;
    public string $cardDueDate = '';

    // Column modal
    public bool $showColumnModal = false;
    public string $columnName = '';
    public string $columnColor = '#94a3b8';

    protected $rules = [
        'cardTitle' => 'required|string|max:255',
        'cardDescription' => 'nullable|string',
        'cardLabel' => 'nullable|string|max:50',
        'cardPriority' => 'required|integer|between:1,3',
        'cardDueDate' => 'nullable|date',
        'columnName' => 'required|string|max:100',
    ];

    public function mount(): void
    {
        $this->loadProjects();
        // If projectId came from URL, verify the user actually has access
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
        $this->loadColumns();
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
        $this->loadColumns();
    }

    public function loadColumns(): void
    {
        if (!$this->projectId) { $this->columns = []; return; }
        $columns = KanbanColumn::where('project_id', $this->projectId)
            ->with(['cards' => fn($q) => $q->orderBy('position')])
            ->orderBy('position')
            ->get();
        if ($columns->isEmpty()) {
            $this->createDefaultColumns();
            return;
        }
        $this->columns = $columns->toArray();
        $this->dispatch('kanban-columns-loaded');
    }

    public function createDefaultColumns(): void
    {
        $defaults = [
            ['name' => 'Backlog', 'color' => '#94a3b8', 'position' => 0],
            ['name' => 'In Progress', 'color' => '#3b82f6', 'position' => 1],
            ['name' => 'Review', 'color' => '#f59e0b', 'position' => 2],
            ['name' => 'Done', 'color' => '#22c55e', 'position' => 3],
        ];
        foreach ($defaults as $col) {
            KanbanColumn::create(array_merge($col, ['project_id' => $this->projectId]));
        }
        $this->loadColumns();
    }

    public function openAddCard(int $columnId): void
    {
        $this->addingToColumnId = $columnId;
        $this->reset(['cardTitle','cardDescription','cardLabel','cardLabelColor','cardPriority','cardDueDate']);
        $this->cardLabelColor = '#4f46e5';
        $this->cardPriority = 1;
        $this->showCardModal = true;
    }

    public function saveCard(): void
    {
        $this->validateOnly('cardTitle');
        $col = KanbanColumn::where('project_id', $this->projectId)->findOrFail($this->addingToColumnId);
        $position = $col->cards()->count();
        KanbanCard::create([
            'column_id' => $col->id,
            'project_id' => $this->projectId,
            'created_by' => auth()->id(),
            'title' => $this->cardTitle,
            'description' => $this->cardDescription ?: null,
            'label' => $this->cardLabel ?: null,
            'label_color' => $this->cardLabel ? $this->cardLabelColor : null,
            'priority' => $this->cardPriority,
            'due_date' => $this->cardDueDate ?: null,
            'position' => $position,
        ]);
        $this->showCardModal = false;
        $this->loadColumns();
        if ($this->projectId) {
            broadcast(new KanbanUpdated($this->projectId, 'card_added', ['column' => $col->name], auth()->id(), auth()->user()->name));
        }
    }

    public function moveCard(int $cardId, int $toColumnId): void
    {
        KanbanCard::where('project_id', $this->projectId)->findOrFail($cardId)
            ->update(['column_id' => $toColumnId, 'position' => 9999]);
        $this->loadColumns();
    }

    public function moveCardToPosition(int $cardId, int $toColumnId, int $newPosition): void
    {
        $card = KanbanCard::where('project_id', $this->projectId)->findOrFail($cardId);
        $fromColumnId = $card->column_id;

        // Move card to target column
        $card->update(['column_id' => $toColumnId]);

        // Re-sort target column, inserting moved card at new position
        $others = KanbanCard::where('column_id', $toColumnId)
            ->where('id', '!=', $cardId)
            ->orderBy('position')
            ->get();

        $newPosition = min($newPosition, $others->count());
        $others->splice($newPosition, 0, [$card]);
        $others->each(fn($c, $i) => KanbanCard::where('id', $c->id ?? $c['id'])->update(['position' => $i]));

        // Re-normalize source column if card moved between columns
        if ($fromColumnId !== $toColumnId) {
            KanbanCard::where('column_id', $fromColumnId)
                ->orderBy('position')
                ->get()
                ->each(fn($c, $i) => $c->update(['position' => $i]));
        }

        $this->loadColumns();
        if ($this->projectId) {
            broadcast(new KanbanUpdated($this->projectId, 'card_moved', [], auth()->id(), auth()->user()->name));
        }
    }

    public function deleteCard(int $cardId): void
    {
        KanbanCard::where('project_id', $this->projectId)->findOrFail($cardId)->delete();
        $this->loadColumns();
    }

    public function openAddColumn(): void
    {
        $this->reset(['columnName','columnColor']);
        $this->columnColor = '#94a3b8';
        $this->showColumnModal = true;
    }

    public function saveColumn(): void
    {
        $this->validateOnly('columnName');
        $position = KanbanColumn::where('project_id', $this->projectId)->count();
        KanbanColumn::create([
            'project_id' => $this->projectId,
            'name' => $this->columnName,
            'color' => $this->columnColor,
            'position' => $position,
        ]);
        $this->showColumnModal = false;
        $this->loadColumns();
    }

    public function deleteColumn(int $columnId): void
    {
        KanbanColumn::where('project_id', $this->projectId)->findOrFail($columnId)->delete();
        $this->loadColumns();
    }

    public function getListeners(): array
    {
        $id = $this->projectId ?? 0;
        return [
            // Presence: online users tracking
            'echo-presence:project.' . $id . '.kanban,here'    => 'hereUsers',
            'echo-presence:project.' . $id . '.kanban,joining' => 'userJoined',
            'echo-presence:project.' . $id . '.kanban,leaving' => 'userLeft',
            // Public channel: no auth needed, fires reliably for everyone
            'echo:project.' . $id . '.public,kanban.updated'   => 'loadColumns',
        ];
    }

    public function hereUsers(array $users): void { $this->onlineUsers = $users; }
    public function userJoined(array $user): void { $this->onlineUsers[] = $user; }
    public function userLeft(array $user): void {
        $this->onlineUsers = array_values(array_filter($this->onlineUsers, fn($u) => $u['id'] !== $user['id']));
    }
};
?>
<div class="h-full flex flex-col bg-surface">

    {{-- Top bar: project selector + online users + add column --}}
    <div class="flex items-center justify-between px-4 sm:px-6 py-3 border-b border-outline-variant/30 shrink-0 gap-3 flex-wrap">
        <div class="flex items-center gap-3 flex-wrap">
            <select wire:model.live="projectId"
                class="rounded-xl border border-outline-variant bg-surface-container px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary min-w-[160px]">
                @foreach($projects as $p)
                <option value="{{ $p['id'] }}">
                    {{ $p['is_personal'] ? '👤 ' : '' }}{{ $p['name'] }}{{ $p['is_favorite'] ? ' ★' : '' }}
                </option>
                @endforeach
            </select>
            {{-- Online users --}}
            @if(count($onlineUsers))
            <div class="flex -space-x-2">
                @foreach(array_slice($onlineUsers, 0, 4) as $user)
                <div class="h-7 w-7 rounded-full border-2 border-surface bg-primary/20 flex items-center justify-center text-[10px] font-bold text-primary"
                    title="{{ $user['name'] ?? '' }}">{{ $user['initials'] ?? '?' }}</div>
                @endforeach
                @if(count($onlineUsers) > 4)
                <div class="h-7 w-7 rounded-full border-2 border-surface bg-surface-container-high flex items-center justify-center text-[10px] font-bold text-on-surface-variant">+{{ count($onlineUsers) - 4 }}</div>
                @endif
            </div>
            @endif
        </div>
        <button wire:click="openAddColumn"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-surface-container-high text-on-surface rounded-xl text-sm hover:bg-surface-container-highest transition">
            <span class="material-symbols-outlined text-base">add</span> Add Column
        </button>
    </div>

    {{-- Kanban grid --}}
    <div class="flex-1 overflow-auto p-4 sm:p-6">
        @if(empty($columns))
        <div class="flex items-center justify-center h-full text-on-surface-variant">
            <div class="text-center">
                <span class="material-symbols-outlined text-5xl block mb-3">view_kanban</span>
                <p>Select a project to view its kanban board.</p>
            </div>
        </div>
        @else
        <div class="flex gap-4 lg:gap-5 h-full items-start" style="min-width: max-content;">
            @foreach($columns as $column)
            <div class="flex flex-col w-72 shrink-0">
                {{-- Column header --}}
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full shrink-0" style="background-color: {{ $column['color'] }}"></span>
                        <span class="font-semibold text-sm text-on-surface">{{ $column['name'] }}</span>
                        <span class="bg-surface-container-high px-2 py-0.5 rounded text-[10px] font-bold text-on-surface-variant">
                            {{ count($column['cards']) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button wire:click="openAddCard({{ $column['id'] }})"
                            class="p-1 hover:bg-surface-container-low rounded-lg transition text-on-surface-variant">
                            <span class="material-symbols-outlined text-base">add</span>
                        </button>
                        <button wire:click="deleteColumn({{ $column['id'] }})" wire:confirm="Delete this column and all its cards?"
                            class="p-1 hover:bg-error-container/30 rounded-lg transition text-on-surface-variant hover:text-error">
                            <span class="material-symbols-outlined text-base">delete</span>
                        </button>
                    </div>
                </div>

                {{-- Cards — data-kanban-column enables SortableJS drag & drop --}}
                <div class="flex-1 bg-surface-container-low rounded-xl p-2.5 space-y-2.5 min-h-24"
                     data-kanban-column="{{ $column['id'] }}">
                    @forelse($column['cards'] as $card)
                    <div class="bg-surface-container-lowest rounded-xl p-3 shadow-sm border-l-4 group relative cursor-grab active:cursor-grabbing"
                        data-card-id="{{ $card['id'] }}"
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
                        <div class="flex items-center justify-between mt-2 gap-2">
                            <div class="flex items-center gap-2">
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
                            {{-- Move button + Delete (visible on hover for non-drag fallback) --}}
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                <button wire:click="deleteCard({{ $card['id'] }})" wire:confirm="Delete this card?"
                                    class="p-0.5 rounded hover:text-error text-on-surface-variant">
                                    <span class="material-symbols-outlined text-xs">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4 text-on-surface-variant/50 text-xs select-none">Drop cards here</div>
                    @endforelse
                </div>

                {{-- Add card quick button --}}
                <button wire:click="openAddCard({{ $column['id'] }})"
                    class="mt-2 w-full flex items-center gap-2 px-3 py-2 rounded-xl text-on-surface-variant hover:bg-surface-container-low text-sm transition">
                    <span class="material-symbols-outlined text-base">add</span> Add card
                </button>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Add Card Modal --}}
    @if($showCardModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" wire:click.self="$set('showCardModal', false)">
        <div class="bg-surface-container-lowest rounded-2xl shadow-xl p-6 w-full max-w-md">
            <h2 class="text-lg font-bold text-on-background mb-4">New Card</h2>
            <form wire:submit="saveCard" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Title <span class="text-error">*</span></label>
                    <input wire:model="cardTitle" type="text" autofocus
                        class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    @error('cardTitle')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea wire:model="cardDescription" rows="2"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-1">Label</label>
                        <input wire:model="cardLabel" type="text" placeholder="e.g. Bug"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Color</label>
                        <input wire:model="cardLabelColor" type="color" class="h-10 w-12 rounded-xl border border-outline-variant cursor-pointer" />
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-1">Priority</label>
                        <select wire:model="cardPriority"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="1">Low</option>
                            <option value="2">Medium</option>
                            <option value="3">High</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-1">Due Date</label>
                        <input wire:model="cardDueDate" type="date"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showCardModal', false)"
                        class="px-4 py-2 rounded-xl text-sm text-on-surface hover:bg-surface-container-high">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">Add Card</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Add Column Modal --}}
    @if($showColumnModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" wire:click.self="$set('showColumnModal', false)">
        <div class="bg-surface-container-lowest rounded-2xl shadow-xl p-6 w-full max-w-sm">
            <h2 class="text-lg font-bold text-on-background mb-4">New Column</h2>
            <form wire:submit="saveColumn" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Column Name</label>
                    <input wire:model="columnName" type="text" autofocus
                        class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    @error('columnName')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Color</label>
                    <input wire:model="columnColor" type="color" class="w-full h-10 rounded-xl border border-outline-variant cursor-pointer" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showColumnModal', false)"
                        class="px-4 py-2 rounded-xl text-sm text-on-surface hover:bg-surface-container-high">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">Add</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

@script
<script>
function initKanbanSortable() {
    document.querySelectorAll('[data-kanban-column]').forEach(col => {
        if (col._sortable) col._sortable.destroy();
        col._sortable = Sortable.create(col, {
            group: 'kanban-cards',
            animation: 150,
            ghostClass: 'opacity-30',
            dragClass: 'shadow-2xl',
            filter: 'button, [wire\\:click]',
            preventOnFilter: false,
            onEnd(evt) {
                if (evt.oldDraggableIndex === evt.newDraggableIndex && evt.from === evt.to) return;
                const cardId = parseInt(evt.item.dataset.cardId);
                const toColId = parseInt(evt.to.dataset.kanbanColumn);
                const newPos = evt.newDraggableIndex;
                $wire.moveCardToPosition(cardId, toColId, newPos);
            },
        });
    });
}

initKanbanSortable();

// Re-init after Livewire re-renders the column list
window.addEventListener('kanban-columns-loaded', () => requestAnimationFrame(initKanbanSortable));
</script>
@endscript
