<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\Snippet;
use App\Models\Project;

new #[Layout('layouts.app')] class extends Component {

    // ── Filters ─────────────────────────────────────────────────────────────
    #[Url]
    public string $search   = '';
    #[Url]
    public string $langFilter = '';
    #[Url]
    public bool   $favOnly  = false;

    // ── List / detail ───────────────────────────────────────────────────────
    public array   $snippets      = [];
    public ?array  $active        = null;   // currently viewed snippet
    public ?int    $activeId      = null;

    // ── Form ────────────────────────────────────────────────────────────────
    public bool    $showForm      = false;
    public bool    $editing       = false;
    public ?int    $editingId     = null;

    public string  $form_title       = '';
    public string  $form_description = '';
    public string  $form_language    = 'php';
    public string  $form_code        = '';
    public string  $form_tags        = '';    // comma-separated input
    public ?int    $form_project_id  = null;

    // ── Misc ────────────────────────────────────────────────────────────────
    public array   $userProjects  = [];
    public array   $runResult     = [];   // stdout, stderr, exit_code, wall_time

    public static array $languages = [
        'php'        => 'PHP',
        'go'         => 'Go',
        'javascript' => 'JavaScript',
        'typescript' => 'TypeScript',
        'python'     => 'Python',
        'sql'        => 'SQL',
        'bash'       => 'Bash',
        'dockerfile' => 'Dockerfile',
        'html'       => 'HTML',
        'css'        => 'CSS',
        'json'       => 'JSON',
        'yaml'       => 'YAML',
        'plaintext'  => 'Plain Text',
    ];

    // ── Lifecycle ────────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->userProjects = Project::where(function ($q) {
            $q->where('owner_id', auth()->id())
              ->orWhereHas('members', fn ($sq) => $sq->where('user_id', auth()->id()));
        })->orderBy('name')->get(['id', 'name'])->toArray();

        $this->loadSnippets();

        if ($this->activeId) {
            $this->selectById($this->activeId);
        } elseif (!empty($this->snippets)) {
            $this->selectSnippet($this->snippets[0]['id']);
        }
    }

    // ── Data ─────────────────────────────────────────────────────────────────
    public function loadSnippets(): void
    {
        $query = Snippet::where('user_id', auth()->id())
            ->orderByDesc('is_favorite')
            ->orderByDesc('updated_at');

        if (trim($this->search) !== '') {
            $q = '%' . trim($this->search) . '%';
            $query->where(function ($sq) use ($q) {
                $sq->where('title', 'like', $q)
                   ->orWhere('language', 'like', $q)
                   ->orWhereJsonContains('tags', trim($this->search));
            });
        }

        if ($this->langFilter !== '') {
            $query->where('language', $this->langFilter);
        }

        if ($this->favOnly) {
            $query->where('is_favorite', true);
        }

        $this->snippets = $query->get([
            'id', 'title', 'language', 'tags', 'is_favorite', 'updated_at',
        ])->map(fn ($s) => [
            'id'          => $s->id,
            'title'       => $s->title,
            'language'    => $s->language,
            'tags'        => $s->tags ?? [],
            'is_favorite' => $s->is_favorite,
            'updated_at'  => $s->updated_at?->diffForHumans(),
        ])->toArray();

        // Keep active in sync after filter
        if ($this->activeId && !collect($this->snippets)->contains('id', $this->activeId)) {
            $this->active   = null;
            $this->activeId = null;
        }
    }

    public function updatedSearch(): void   { $this->loadSnippets(); }
    public function updatedLangFilter(): void { $this->loadSnippets(); }
    public function updatedFavOnly(): void  { $this->loadSnippets(); }

    public function selectSnippet(int $id): void
    {
        $snippet = Snippet::where('id', $id)->where('user_id', auth()->id())->first();
        if (!$snippet) return;

        $this->activeId = $id;
        $this->active   = [
            'id'          => $snippet->id,
            'title'       => $snippet->title,
            'description' => $snippet->description,
            'language'    => $snippet->language,
            'code'        => $snippet->code,
            'tags'        => $snippet->tags ?? [],
            'is_favorite' => $snippet->is_favorite,
            'extension'   => $snippet->extension,
            'project_id'  => $snippet->project_id,
            'updated_at'  => $snippet->updated_at?->format('Y-m-d H:i'),
        ];

        $this->showForm = false;
    }

    private function selectById(int $id): void
    {
        $this->selectSnippet($id);
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────
    public function openCreate(): void
    {
        $this->resetForm();
        $this->editing  = false;
        $this->showForm = true;
        $this->active   = null;
    }

    public function openEdit(): void
    {
        if (!$this->active) return;

        $this->editing          = true;
        $this->editingId        = $this->active['id'];
        $this->form_title       = $this->active['title'];
        $this->form_description = $this->active['description'] ?? '';
        $this->form_language    = $this->active['language'];
        $this->form_code        = $this->active['code'];
        $this->form_tags        = implode(', ', $this->active['tags'] ?? []);
        $this->form_project_id  = $this->active['project_id'];
        $this->showForm         = true;
    }

    public function save(): void
    {
        $this->validate([
            'form_title'    => 'required|string|max:200',
            'form_language' => 'required|string',
            'form_code'     => 'required|string',
        ]);

        $tags = array_values(array_filter(
            array_map('trim', explode(',', $this->form_tags))
        ));

        $data = [
            'user_id'     => auth()->id(),
            'project_id'  => $this->form_project_id ?: null,
            'title'       => $this->form_title,
            'description' => $this->form_description ?: null,
            'language'    => $this->form_language,
            'code'        => $this->form_code,
            'tags'        => $tags ?: null,
        ];

        if ($this->editing && $this->editingId) {
            Snippet::where('id', $this->editingId)
                ->where('user_id', auth()->id())
                ->update($data);
            $savedId = $this->editingId;
        } else {
            $snippet = Snippet::create($data);
            $savedId = $snippet->id;
        }

        $this->resetForm();
        $this->showForm = false;
        $this->loadSnippets();
        $this->selectSnippet($savedId);
    }

    public function toggleFavorite(int $id): void
    {
        $snippet = Snippet::where('id', $id)->where('user_id', auth()->id())->first();
        if (!$snippet) return;

        $snippet->update(['is_favorite' => !$snippet->is_favorite]);

        $this->loadSnippets();

        if ($this->activeId === $id) {
            $this->active['is_favorite'] = !$this->active['is_favorite'];
        }
    }

    public function delete(): void
    {
        if (!$this->activeId) return;

        Snippet::where('id', $this->activeId)->where('user_id', auth()->id())->delete();

        $this->active   = null;
        $this->activeId = null;
        $this->loadSnippets();

        if (!empty($this->snippets)) {
            $this->selectSnippet($this->snippets[0]['id']);
        }
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editing         = false;
        $this->editingId       = null;
        $this->form_title      = '';
        $this->form_description = '';
        $this->form_language   = 'php';
        $this->form_code       = '';
        $this->form_tags       = '';
        $this->form_project_id = null;
    }

    // ── Export ────────────────────────────────────────────────────────────────
    public function exportSnippet(): void
    {
        if (!$this->active) return;

        $slug = $this->active['language'];
        $ext  = \App\Models\Snippet::$extensions[$slug] ?? 'txt';

        // Dockerfile has no dot prefix
        $filename = $slug === 'dockerfile'
            ? 'Dockerfile'
            : str($this->active['title'])->slug('-') . '.' . $ext;

        $this->dispatch('snippet-export', [
            'filename' => $filename,
            'content'  => $this->active['code'],
        ]);
    }

    // ── Local code execution ──────────────────────────────────────────────────
    public function runCode(): void
    {
        if (!$this->active) return;

        $language = $this->active['language'];
        $code     = $this->active['code'];

        $supported = ['php', 'go', 'javascript', 'typescript', 'python', 'bash'];
        if (!in_array($language, $supported)) return;

        // Write code to a temp file with the right extension
        $ext = match ($language) {
            'php'        => '.php',
            'go'         => '.go',
            'javascript' => '.js',
            'typescript' => '.ts',
            'python'     => '.py',
            'bash'       => '.sh',
        };

        $tmpBase = tempnam(sys_get_temp_dir(), 'snip_');
        $tmpFile = $tmpBase . $ext;
        @unlink($tmpBase);             // remove the no-extension file tempnam creates
        file_put_contents($tmpFile, $code);

        // Build the command for each language
        // Wrap with `timeout 10` so runaway code is killed after 10 seconds
        $cmd = match ($language) {
            'php'        => ['timeout', '10', 'php',      $tmpFile],
            'go'         => ['timeout', '15', 'go',       'run', $tmpFile],
            'javascript' => ['timeout', '10', 'node',     $tmpFile],
            'typescript' => ['timeout', '15', 'npx', '--yes', 'tsx', $tmpFile],
            'python'     => ['timeout', '10', 'python3',  $tmpFile],
            'bash'       => ['timeout', '10', 'bash',     $tmpFile],
        };

        $start = microtime(true);
        $proc  = @proc_open(
            $cmd,
            [
                0 => ['pipe', 'r'],   // stdin
                1 => ['pipe', 'w'],   // stdout
                2 => ['pipe', 'w'],   // stderr
            ],
            $pipes,
            sys_get_temp_dir(),
            null
        );

        if (!is_resource($proc)) {
            @unlink($tmpFile);
            $this->runResult = [
                'stdout'    => '',
                'stderr'    => 'Failed to start process. Is the runtime installed?',
                'exit_code' => -1,
                'wall_time' => 0,
            ];
            return;
        }

        fclose($pipes[0]);  // no stdin needed
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        $elapsed  = (int) round((microtime(true) - $start) * 1000);

        @unlink($tmpFile);

        $this->runResult = [
            'stdout'    => $stdout ?: '',
            'stderr'    => $stderr ?: '',
            'exit_code' => $exitCode,
            'wall_time' => $elapsed,
        ];
    }

    // ── Language helpers ──────────────────────────────────────────────────────
    public function getLangLabel(string $slug): string
    {
        return self::$languages[$slug] ?? strtoupper($slug);
    }
};
?>



<div class="h-full flex overflow-hidden bg-slate-950 text-slate-100 font-mono"
     x-data="snippetPage">

    {{-- ═══ LEFT PANEL — List ════════════════════════════════════════════════ --}}
    <div class="w-72 shrink-0 flex flex-col border-r border-slate-800 bg-slate-900 overflow-hidden">

        {{-- Search --}}
        <div class="p-3 space-y-2 border-b border-slate-800 shrink-0">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm pointer-events-none">search</span>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Buscar fragmentos..."
                       class="w-full bg-slate-800 border border-slate-700 text-slate-200 text-xs rounded-lg pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 placeholder-slate-600">
            </div>

            {{-- Language filter --}}
            <select wire:model.live="langFilter"
                    class="w-full bg-slate-800 border border-slate-700 text-slate-400 text-xs rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500/50">
                <option value="">Todos los lenguajes</option>
                @foreach(self::$languages as $slug => $label)
                <option value="{{ $slug }}">{{ $label }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2">
                <label class="flex items-center gap-1.5 text-xs text-slate-400 cursor-pointer select-none">
                    <input wire:model.live="favOnly" type="checkbox"
                           class="rounded border-slate-600 bg-slate-700 text-amber-400 focus:ring-0">
                    <span class="text-amber-400">★</span> Solo favoritos
                </label>
                <button wire:click="openCreate"
                        class="ml-auto flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors">
                    <span class="material-symbols-outlined text-sm">add</span> Nuevo
                </button>
            </div>
        </div>

        {{-- Snippet list --}}
        <div class="flex-1 overflow-y-auto divide-y divide-slate-800/60">
            @forelse($snippets as $s)
            <button wire:click="selectSnippet({{ $s['id'] }})"
                    class="w-full text-left px-4 py-3 hover:bg-slate-800/60 transition-colors group
                        {{ $activeId === $s['id'] ? 'bg-slate-800/80 border-l-2 border-indigo-500' : '' }}">
                <div class="flex items-start gap-2">
                    <span class="flex-1 min-w-0">
                        <span class="flex items-center gap-1.5 flex-wrap">
                            @if($s['is_favorite'])
                            <span class="text-amber-400 text-xs leading-none">★</span>
                            @endif
                            <span class="text-slate-200 text-xs font-semibold truncate">{{ $s['title'] }}</span>
                        </span>
                        <span class="flex items-center gap-1.5 mt-1">
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-indigo-900/60 text-indigo-300 border border-indigo-700/40">
                                {{ $s['language'] }}
                            </span>
                            @foreach(array_slice($s['tags'], 0, 2) as $tag)
                            <span class="px-1.5 py-0.5 rounded text-[9px] bg-slate-700/70 text-slate-400 border border-slate-700/50">{{ $tag }}</span>
                            @endforeach
                        </span>
                    </span>
                    <span class="text-[10px] text-slate-600 shrink-0 mt-0.5">{{ $s['updated_at'] }}</span>
                </div>
            </button>
            @empty
            <div class="p-6 text-center text-slate-600 text-xs">
                No se encontraron fragmentos.<br>
                <button wire:click="openCreate" class="mt-2 text-indigo-500 hover:text-indigo-300 transition-colors">Crear uno →</button>
            </div>
            @endforelse
        </div>
    </div>

    {{-- ═══ RIGHT AREA — Viewer / Form ═══════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        @if($showForm)
        {{-- ─── CREATE / EDIT FORM ─────────────────────────────────────────── --}}
        <div class="shrink-0 bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center gap-3">
            <span class="material-symbols-outlined text-indigo-400">{{ $editing ? 'edit' : 'add_circle' }}</span>
            <h2 class="text-white font-bold font-sans">{{ $editing ? 'Editar Fragmento' : 'Nuevo Fragmento' }}</h2>
            <button wire:click="cancelForm" class="ml-auto text-slate-500 hover:text-slate-300 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 font-sans">
            <div class="max-w-3xl mx-auto space-y-5">

                {{-- Title & Language --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-slate-400 font-medium mb-1.5">Título <span class="text-red-500">*</span></label>
                        <input wire:model.defer="form_title" type="text"
                               placeholder="ej. Plantilla base de Docker Compose"
                               class="w-full bg-slate-800 border border-slate-700 text-slate-100 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/60 placeholder-slate-600">
                        @error('form_title') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 font-medium mb-1.5">Lenguaje <span class="text-red-500">*</span></label>
                        <select wire:model.defer="form_language"
                                class="w-full bg-slate-800 border border-slate-700 text-slate-200 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/60">
                            @foreach(self::$languages as $slug => $label)
                            <option value="{{ $slug }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-xs text-slate-400 font-medium mb-1.5">Descripción</label>
                    <input wire:model.defer="form_description" type="text"
                           placeholder="¿Qué hace este fragmento?"
                           class="w-full bg-slate-800 border border-slate-700 text-slate-100 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/60 placeholder-slate-600">
                </div>

                {{-- Code --}}
                <div>
                    <label class="block text-xs text-slate-400 font-medium mb-1.5">Código <span class="text-red-500">*</span></label>
                    <textarea wire:model.defer="form_code" rows="18"
                              placeholder="Pega tu código aquí..."
                              class="w-full bg-slate-950 border border-slate-700 text-emerald-300 text-xs font-mono rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500/60 resize-y placeholder-slate-700 leading-relaxed"></textarea>
                    @error('form_code') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Tags & Project --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 font-medium mb-1.5">Etiquetas <span class="text-slate-600">(separadas por coma)</span></label>
                        <input wire:model.defer="form_tags" type="text"
                               placeholder="docker, devops, config"
                               class="w-full bg-slate-800 border border-slate-700 text-slate-100 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/60 placeholder-slate-600">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 font-medium mb-1.5">Vincular a Proyecto <span class="text-slate-600">(opcional)</span></label>
                        <select wire:model.defer="form_project_id"
                                class="w-full bg-slate-800 border border-slate-700 text-slate-200 text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/60">
                            <option value="">— Ninguno —</option>
                            @foreach($userProjects as $proj)
                            <option value="{{ $proj['id'] }}">{{ $proj['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="cancelForm"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-slate-400 hover:bg-slate-800 border border-slate-700 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="save"
                            class="px-6 py-2 rounded-xl text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors shadow-lg shadow-indigo-600/20">
                        <span wire:loading.remove wire:target="save">{{ $editing ? 'Guardar Cambios' : 'Guardar Fragmento' }}</span>
                        <span wire:loading wire:target="save">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>

        @elseif($active)
        {{-- ─── VIEWER ─────────────────────────────────────────────────────── --}}

        {{-- Header bar --}}
        <div class="shrink-0 bg-slate-900 border-b border-slate-800 px-4 py-3 flex items-center gap-3 font-sans">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    @if($active['is_favorite'])
                    <span class="text-amber-400 text-sm">★</span>
                    @endif
                    <h2 class="text-white font-bold text-sm truncate">{{ $active['title'] }}</h2>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-900/60 text-indigo-300 border border-indigo-700/40 shrink-0">
                        {{ $active['language'] }}
                    </span>
                </div>
                @if($active['description'])
                <p class="text-slate-500 text-xs mt-0.5 truncate">{{ $active['description'] }}</p>
                @endif
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-1 shrink-0">
                {{-- Favorite --}}
                <button wire:click="toggleFavorite({{ $active['id'] }})"
                        title="{{ $active['is_favorite'] ? 'Quitar de favoritos' : 'Agregar a favoritos' }}"
                        class="p-2 rounded-lg text-slate-500 hover:bg-slate-800 transition-colors {{ $active['is_favorite'] ? 'text-amber-400 hover:text-amber-300' : 'hover:text-amber-400' }}">
                    <span class="material-symbols-outlined text-lg">{{ $active['is_favorite'] ? 'star' : 'star_border' }}</span>
                </button>

                {{-- Copy --}}
                <button x-data="{ copied: false }"
                        @click="
                            navigator.clipboard.writeText($wire.active.code);
                            copied = true;
                            setTimeout(() => copied = false, 2000);
                        "
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 border border-slate-700 text-slate-300 hover:border-indigo-500/60 hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-sm" x-text="copied ? 'check' : 'content_copy'"></span>
                    <span x-text="copied ? '¡Copiado!' : 'Copiar'"></span>
                </button>

                {{-- Export --}}
                <button @click="$wire.exportSnippet()"
                        x-data
                        @snippet-export.window="downloadFile($event.detail.filename, $event.detail.content)"
                        title="Descargar archivo"
                        class="p-2 rounded-lg text-slate-500 hover:bg-slate-800 hover:text-slate-200 transition-colors border border-transparent hover:border-slate-700">
                    <span class="material-symbols-outlined text-lg">download</span>
                </button>

                {{-- Edit --}}
                <button wire:click="openEdit"
                        class="p-2 rounded-lg text-slate-500 hover:bg-slate-800 hover:text-slate-200 transition-colors border border-transparent hover:border-slate-700">
                    <span class="material-symbols-outlined text-lg">edit</span>
                </button>

                {{-- Delete --}}
                <button wire:click="delete"
                        wire:confirm="¿Eliminar '{{ $active['title'] }}'? Esta acción no se puede deshacer."
                        class="p-2 rounded-lg text-slate-500 hover:bg-red-900/30 hover:text-red-400 transition-colors border border-transparent hover:border-red-800/50">
                    <span class="material-symbols-outlined text-lg">delete</span>
                </button>

                {{-- Run (Sandbox) — only for executable languages --}}
                @if(in_array($active['language'], ['php','go','javascript','typescript','python','bash']))
                <button @click="runSandbox($wire.active)"
                        :disabled="running"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors ml-1"
                        :class="running
                            ? 'bg-emerald-900/20 border-emerald-800/50 text-emerald-600 cursor-wait'
                            : 'bg-emerald-600 border-emerald-500 text-white hover:bg-emerald-500'">
                    <span class="material-symbols-outlined text-sm" x-text="running ? 'hourglass_top' : 'play_arrow'"
                          :class="running ? 'animate-spin' : ''"></span>
                    <span x-text="running ? 'Ejecutando…' : 'Ejecutar'"></span>
                </button>
                @endif
            </div>
        </div>

        {{-- Tags row --}}
        @if(!empty($active['tags']))
        <div class="shrink-0 px-4 py-2 bg-slate-900/60 border-b border-slate-800 flex items-center gap-1.5 flex-wrap font-sans">
            <span class="material-symbols-outlined text-slate-600 text-sm">label</span>
            @foreach($active['tags'] as $tag)
            <span class="px-2 py-0.5 rounded text-[10px] bg-slate-800 text-slate-400 border border-slate-700">{{ $tag }}</span>
            @endforeach
            <span class="ml-auto text-[10px] text-slate-600">{{ $active['updated_at'] }}</span>
        </div>
        @endif

        {{-- Code viewer --}}
        <div class="overflow-auto bg-slate-950"
             :class="outputVisible ? 'flex-1' : 'flex-1'"
             wire:key="viewer-{{ $activeId }}"
             x-init="clearOutput(); $nextTick(() => $dispatch('snippet-rendered'))">
            <pre class="p-6 min-h-full text-xs leading-relaxed"><code class="language-{{ $active['language'] === 'dockerfile' ? 'dockerfile' : $active['language'] }}">{{ $active['code'] }}</code></pre>
        </div>

        {{-- ─── SANDBOX OUTPUT PANEL ──────────────────────────────────────── --}}
        <div x-show="outputVisible" x-cloak
             class="shrink-0 flex flex-col bg-slate-900 border-t border-slate-700"
             style="height: 220px; min-height: 120px; max-height: 50vh; resize: vertical; overflow: auto;">

            {{-- Output header --}}
            <div class="flex items-center gap-2 px-4 py-2 bg-slate-800/80 border-b border-slate-700 shrink-0 font-sans">
                <span class="material-symbols-outlined text-sm text-slate-500">terminal</span>
                <span class="text-xs font-semibold text-slate-400">Salida</span>

                {{-- Exit code badge --}}
                <template x-if="runOutput.exitCode !== null">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                          :class="runOutput.exitCode === 0
                              ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-700/50'
                              : 'bg-red-900/60 text-red-300 border border-red-700/50'">
                        exit <span x-text="runOutput.exitCode"></span>
                    </span>
                </template>

                {{-- Runtime badge --}}
                <template x-if="runOutput.wall_time">
                    <span class="text-[10px] text-slate-600" x-text="runOutput.wall_time + 'ms'"></span>
                </template>

                <button @click="clearOutput()" class="ml-auto text-slate-600 hover:text-slate-300 transition-colors p-1">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            {{-- Output content --}}
            <div class="flex-1 overflow-auto p-4 text-xs font-mono leading-relaxed">
                {{-- Stdout --}}
                <template x-if="runOutput.stdout">
                    <pre class="text-emerald-300 whitespace-pre-wrap break-words" x-text="runOutput.stdout"></pre>
                </template>

                {{-- Stderr --}}
                <template x-if="runOutput.stderr">
                    <div>
                        <p class="text-red-500 text-[10px] uppercase font-bold mb-1 tracking-wider">stderr</p>
                        <pre class="text-red-300 whitespace-pre-wrap break-words" x-text="runOutput.stderr"></pre>
                    </div>
                </template>

                {{-- No output --}}
                <template x-if="!runOutput.stdout && !runOutput.stderr && runOutput.exitCode !== null">
                    <p class="text-slate-600 italic text-[11px]">(sin salida)</p>
                </template>

                {{-- Loading --}}
                <template x-if="running">
                    <div class="flex items-center gap-2 text-slate-500">
                        <span class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                        <span>Ejecutando localmente…</span>
                    </div>
                </template>
            </div>
        </div>

        @else
        {{-- ─── EMPTY STATE ─────────────────────────────────────────────────── --}}
        <div class="flex-1 flex flex-col items-center justify-center gap-4 text-center px-8 font-sans">
            <span class="material-symbols-outlined text-slate-700 text-6xl">code_blocks</span>
            <div>
                <p class="text-slate-500 font-semibold">Ningún fragmento seleccionado</p>
                <p class="text-slate-700 text-sm mt-1">Selecciona uno de la lista o crea uno nuevo.</p>
            </div>
            <button wire:click="openCreate"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors shadow-lg shadow-indigo-600/20 mt-2">
                <span class="material-symbols-outlined text-base">add</span> Nuevo Fragmento
            </button>
        </div>
        @endif
    </div>

</div>

