<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Project;
use App\Models\ActivityLog;

new #[Layout('layouts.app')] class extends Component {
    public $projects;
    public bool $showModal = false;
    public ?int $editingId  = null;   // null = create, int = edit
    public string $name = '';
    public string $description = '';
    public string $color = '#3525cd';
    public string $icon = 'folder';
    public array  $links = [];        // [{label, url}]
    public string $newLinkLabel = '';
    public string $newLinkUrl   = '';

    protected $rules = [
        'name'        => 'required|string|max:255',
        'description' => 'nullable|string',
        'color'       => 'required',
        'icon'        => 'nullable|string|max:50',
        'links.*.url' => 'nullable|url',
    ];

    public function mount(): void { $this->loadProjects(); }

    public function loadProjects(): void {
        $user = auth()->user();
        $projectIds = Project::where('owner_id', $user->id)
            ->orWhereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $this->projects = Project::whereIn('id', $projectIds)
            ->withCount(['kanbanCards', 'notes', 'members'])
            ->orderByDesc('is_favorite')
            ->orderByDesc('is_personal')
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void {
        $this->reset(['name','description','icon','links','newLinkLabel','newLinkUrl','editingId']);
        $this->color = '#3525cd';
        $this->icon  = 'folder';
        $this->showModal = true;
    }

    public function openEdit(int $id): void {
        $project = Project::where('owner_id', auth()->id())->findOrFail($id);
        $this->editingId   = $id;
        $this->name        = $project->name;
        $this->description = $project->description ?? '';
        $this->color       = $project->color;
        $this->icon        = $project->icon;
        $this->links       = $project->links ?? [];
        $this->showModal   = true;
    }

    public function addLink(): void {
        $url = trim($this->newLinkUrl);
        $label = trim($this->newLinkLabel) ?: parse_url($url, PHP_URL_HOST);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $this->links[] = ['label' => $label, 'url' => $url];
        }
        $this->newLinkLabel = '';
        $this->newLinkUrl   = '';
    }

    public function removeLink(int $index): void {
        array_splice($this->links, $index, 1);
    }

    public function toggleFavorite(int $id): void {
        $project = Project::where('owner_id', auth()->id())->findOrFail($id);
        $project->update(['is_favorite' => !$project->is_favorite]);
        $this->loadProjects();
    }

    public function saveProject(): void {
        $this->validate();
        $data = [
            'name'        => $this->name,
            'description' => $this->description,
            'color'       => $this->color,
            'icon'        => $this->icon,
            'links'       => empty($this->links) ? null : array_values($this->links),
        ];
        if ($this->editingId) {
            $project = Project::where('owner_id', auth()->id())->findOrFail($this->editingId);
            $project->update($data);
            ActivityLog::record('project_updated', 'Updated project "' . $project->name . '"', $project);
        } else {
            $project = Project::create(array_merge($data, ['owner_id' => auth()->id()]));
            ActivityLog::record('project_created', 'Created project "' . $project->name . '"', $project);
        }
        $this->showModal = false;
        $this->loadProjects();
    }

    // Keep old name for backward compat (sidebar new-project button calls createProject)
    public function createProject(): void { $this->saveProject(); }

    public function archiveProject(int $id): void {
        $project = Project::where('owner_id', auth()->id())->findOrFail($id);
        $project->update(['is_archived' => !$project->is_archived]);
        $this->loadProjects();
    }

    public function deleteProject(int $id): void {
        $project = Project::where('owner_id', auth()->id())->findOrFail($id);
        $project->delete();
        $this->loadProjects();
    }
};
?>
<div class="p-4 md:p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-on-background">Proyectos</h1>
        <button wire:click="openCreate"
            class="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90 transition">
            <span class="material-symbols-outlined text-base">add</span> Nuevo Proyecto
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($projects as $project)
        <div class="bg-surface-container rounded-2xl p-4 flex flex-col gap-3" x-data="{ open: false }">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold shrink-0"
                    style="background-color: {{ $project->color ?? '#3525cd' }}">
                    <span class="material-symbols-outlined">{{ $project->icon ?? 'folder' }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-on-surface truncate flex items-center gap-1">
                        {{ $project->name }}
                        @if($project->is_personal)<span class="text-[10px] text-on-surface-variant bg-surface-container-high px-1.5 py-0.5 rounded-full">Personal</span>@endif
                    </p>
                    @if($project->is_archived)
                    <span class="text-xs bg-surface-container-highest text-on-surface-variant px-2 py-0.5 rounded-full">Archivado</span>
                    @endif
                </div>
                {{-- Favorite toggle --}}
                @if($project->owner_id === auth()->id())
                <button wire:click="toggleFavorite({{ $project->id }})"
                    title="{{ $project->is_favorite ? 'Quitar de favoritos' : 'Agregar a favoritos' }}"
                    class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-surface-container-high transition shrink-0 {{ $project->is_favorite ? 'text-amber-400' : 'text-on-surface-variant/40' }}">
                    <span class="material-symbols-outlined text-lg">{{ $project->is_favorite ? 'star' : 'star_outline' }}</span>
                </button>
                @endif
                <div class="relative shrink-0">
                    <button @click="open = !open" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-surface-container-high">
                        <span class="material-symbols-outlined text-on-surface-variant">more_vert</span>
                    </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                        class="absolute right-0 top-9 w-48 bg-surface-container-highest rounded-xl shadow-lg border border-outline-variant/30 py-1 z-10">
                        @if($project->owner_id === auth()->id())
                        <button wire:click="openEdit({{ $project->id }})" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-on-surface hover:bg-surface-container-high">
                            <span class="material-symbols-outlined text-sm">edit</span> Editar
                        </button>
                        @endif
                        <a href="{{ route('projects.members', $project) }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm text-on-surface hover:bg-surface-container-high">
                            <span class="material-symbols-outlined text-sm">group</span> Miembros
                        </a>
                        <a href="{{ route('projects.share', $project) }}" wire:navigate class="flex items-center gap-2 px-4 py-2 text-sm text-on-surface hover:bg-surface-container-high">
                            <span class="material-symbols-outlined text-sm">share</span> Compartir
                        </a>
                        @if($project->owner_id === auth()->id())
                        <button wire:click="archiveProject({{ $project->id }})" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-on-surface hover:bg-surface-container-high">
                            <span class="material-symbols-outlined text-sm">archive</span> {{ $project->is_archived ? 'Desarchivar' : 'Archivar' }}
                        </button>
                        <button wire:click="deleteProject({{ $project->id }})" wire:confirm="¿Eliminar este proyecto?"
                            class="flex w-full items-center gap-2 px-4 py-2 text-sm text-error hover:bg-error-container/30">
                            <span class="material-symbols-outlined text-sm">delete</span> Eliminar
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            <p class="text-sm text-on-surface-variant line-clamp-2">{{ $project->description ?: 'Sin descripción.' }}</p>
            {{-- External links --}}
            @if(!empty($project->links))
            <div class="flex flex-wrap gap-1.5">
                @foreach($project->links as $link)
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant hover:text-primary hover:bg-primary/10 transition border border-outline-variant/30">
                    @php
                        $icon = match(true) {
                            str_contains($link['url'], 'github.com') => 'code',
                            str_contains($link['url'], 'figma.com')  => 'design_services',
                            str_contains($link['url'], 'notion.so')  => 'article',
                            str_contains($link['url'], 'jira')       => 'bug_report',
                            str_contains($link['url'], 'linear.app') => 'linear_scale',
                            str_contains($link['url'], 'slack.com')  => 'forum',
                            default                                   => $link['icon'] ?? 'link',
                        };
                    @endphp
                    <span class="material-symbols-outlined text-[11px]">{{ $icon }}</span>
                    {{ $link['label'] }}
                </a>
                @endforeach
            </div>
            @endif
            
            {{-- Quick Actions --}}
            <div class="flex gap-2 mt-2">
                <a href="{{ route('kanban', ['projectId' => $project->id]) }}" wire:navigate 
                    class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl bg-primary/5 text-primary text-xs font-semibold hover:bg-primary/10 transition border border-primary/10">
                    <span class="material-symbols-outlined text-sm">view_kanban</span> Kanban
                </a>
                <a href="{{ route('notes', ['projectId' => $project->id]) }}" wire:navigate 
                    class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl bg-indigo-600/5 text-indigo-600 text-xs font-semibold hover:bg-indigo-600/10 transition border border-indigo-600/10">
                    <span class="material-symbols-outlined text-sm">description</span> Notas
                </a>
            </div>

            <div class="flex items-center gap-4 text-xs text-on-surface-variant mt-auto">
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">view_kanban</span>{{ $project->kanban_cards_count }}</span>
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">notes</span>{{ $project->notes_count }}</span>
                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">group</span>{{ $project->members_count }}</span>
            </div>
        </div>
        @empty
        <div class="col-span-full text-center py-16 text-on-surface-variant">
            <span class="material-symbols-outlined text-5xl block mb-3">folder_open</span>
            <p class="text-base">Sin proyectos aún. ¡Crea el primero!</p>
        </div>
        @endforelse
    </div>

    @if($showModal)
    <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" wire:click.self="$set('showModal', false)">
        <div class="bg-surface-container-lowest rounded-2xl shadow-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <h2 class="text-lg font-bold text-on-background mb-4">
                {{ $editingId ? 'Editar Proyecto' : 'Nuevo Proyecto' }}
            </h2>
            <form wire:submit="saveProject" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1">Nombre</label>
                    <input wire:model="name" type="text" placeholder="Nombre del proyecto"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    @error('name')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-1">Descripción</label>
                    <textarea wire:model="description" rows="2" placeholder="Descripción opcional"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-on-surface mb-1">Color</label>
                        <input wire:model="color" type="color" class="w-full h-10 rounded-xl border border-outline-variant cursor-pointer" />
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-on-surface mb-1">Ícono</label>
                        <input wire:model="icon" type="text" placeholder="ej. folder"
                            class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                </div>

                {{-- Links --}}
                <div>
                    <label class="block text-sm font-medium text-on-surface mb-2">Enlaces Externos</label>
                    @if(!empty($links))
                    <div class="space-y-1.5 mb-2">
                        @foreach($links as $i => $link)
                        <div class="flex items-center gap-2 bg-surface-container rounded-xl px-3 py-2">
                            <span class="material-symbols-outlined text-sm text-on-surface-variant">link</span>
                            <span class="text-xs font-medium text-on-surface truncate flex-1">{{ $link['label'] }}</span>
                            <span class="text-xs text-on-surface-variant truncate max-w-[140px]">{{ $link['url'] }}</span>
                            <button type="button" wire:click="removeLink({{ $i }})" class="text-on-surface-variant hover:text-error shrink-0">
                                <span class="material-symbols-outlined text-sm">close</span>
                            </button>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    <div class="flex gap-2">
                        <input wire:model="newLinkLabel" type="text" placeholder="Etiqueta (ej. GitHub)"
                            class="w-1/3 rounded-xl border border-outline-variant bg-surface-container px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary" />
                        <input wire:model="newLinkUrl" type="url" placeholder="https://..."
                            class="flex-1 rounded-xl border border-outline-variant bg-surface-container px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-primary" />
                        <button type="button" wire:click="addLink"
                            class="px-3 py-2 bg-surface-container-high text-on-surface rounded-xl text-xs hover:bg-surface-container-highest transition shrink-0">
                            <span class="material-symbols-outlined text-sm">add</span>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('showModal', false)"
                        class="px-4 py-2 rounded-xl text-sm text-on-surface hover:bg-surface-container-high">Cancelar</button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">
                        {{ $editingId ? 'Guardar Cambios' : 'Crear' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>