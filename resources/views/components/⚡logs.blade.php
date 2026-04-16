<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component {

    // ── Filter state ────────────────────────────────────────────────────────
    public array  $projectFilter = [];   // empty = all projects
    public string $levelFilter   = 'all';
    public string $search        = '';
    public bool   $paused        = false;

    // ── Data ────────────────────────────────────────────────────────────────
    public array $userProjects = [];   // [{id, name, color, webhook_token}, ...]
    public array $logs         = [];

    // ── Token panel ─────────────────────────────────────────────────────────
    public bool   $showTokenPanel = false;
    public ?int   $tokenPanelProjId = null;

    // ── Convert-to-task modal ───────────────────────────────────────────────
    public bool   $showTaskModal  = false;
    public ?int   $taskLogId      = null;
    public string $taskTitle      = '';
    public ?int   $taskProjectId  = null;

    // ── Lifecycle ───────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->userProjects = Project::where(function ($q) {
            $q->where('owner_id', auth()->id())
              ->orWhereHas('members', fn ($sq) => $sq->where('user_id', auth()->id()));
        })->orderBy('name')->get(['id', 'name', 'color', 'webhook_token'])->toArray();

        $this->loadLogs();
    }

    public function getListeners(): array
    {
        $listeners = [];
        foreach ($this->userProjects as $proj) {
            $listeners["echo:project.{$proj['id']}.logs,.log.received"] = 'onLogReceived';
        }
        return $listeners;
    }

    // ── Data loading ────────────────────────────────────────────────────────
    public function loadLogs(): void
    {
        $ids = $this->activeProjectIds();

        $query = ProjectLog::whereIn('project_id', $ids)
            ->with('project:id,name,color')
            ->orderByDesc('created_at')
            ->limit(300);

        if ($this->levelFilter !== 'all') {
            $query->where('level', $this->levelFilter);
        }

        if (trim($this->search) !== '') {
            $q = '%' . trim($this->search) . '%';
            $query->where(function ($sq) use ($q) {
                $sq->where('message', 'like', $q)
                   ->orWhere('source_app', 'like', $q)
                   ->orWhere('channel', 'like', $q);
            });
        }

        $this->logs = $query->get()->map(fn ($log) => [
            'id'           => $log->id,
            'project_id'   => $log->project_id,
            'project_name' => $log->project?->name ?? '—',
            'project_color'=> $log->project?->color ?? '#6366f1',
            'level'        => $log->level,
            'level_color'  => $log->level_color,
            'channel'      => $log->channel,
            'message'      => $log->message,
            'stack_trace'  => $log->stack_trace,
            'context'      => $log->context,
            'source_app'   => $log->source_app,
            'environment'  => $log->environment,
            'created_at'   => $log->created_at?->format('Y-m-d H:i:s'),
        ])->toArray();
    }

    // ── Watchers ────────────────────────────────────────────────────────────
    public function updatedProjectFilter(): void { $this->loadLogs(); }
    public function updatedLevelFilter(): void   { $this->loadLogs(); }
    public function updatedSearch(): void        { $this->loadLogs(); }

    // ── Real-time ───────────────────────────────────────────────────────────
    public function onLogReceived(array $data): void
    {
        if ($this->paused) return;

        $projectId = $data['project_id'] ?? null;

        // Project filter check
        $active = $this->activeProjectIds();
        if ($projectId && !in_array($projectId, $active)) return;

        // Level filter check
        if ($this->levelFilter !== 'all' && ($data['level'] ?? '') !== $this->levelFilter) return;

        // Search filter check
        if (trim($this->search) !== '') {
            $needle = mb_strtolower(trim($this->search));
            $haystack = mb_strtolower(
                ($data['message'] ?? '') . ' ' .
                ($data['source_app'] ?? '') . ' ' .
                ($data['channel'] ?? '')
            );
            if (!str_contains($haystack, $needle)) return;
        }

        // Resolve project meta
        $proj = collect($this->userProjects)->firstWhere('id', $projectId);

        array_unshift($this->logs, [
            'id'           => $data['id'] ?? null,
            'project_id'   => $projectId,
            'project_name' => $proj['name'] ?? '—',
            'project_color'=> $proj['color'] ?? '#6366f1',
            'level'        => $data['level'] ?? 'info',
            'level_color'  => $this->resolveLevelColor($data['level'] ?? 'info'),
            'channel'      => $data['channel'] ?? null,
            'message'      => $data['message'] ?? '',
            'stack_trace'  => $data['stack_trace'] ?? null,
            'context'      => isset($data['context']) && is_array($data['context']) ? $data['context'] : null,
            'source_app'   => $data['source_app'] ?? null,
            'environment'  => $data['environment'] ?? null,
            'created_at'   => isset($data['created_at'])
                ? \Carbon\Carbon::parse($data['created_at'])->format('Y-m-d H:i:s')
                : now()->format('Y-m-d H:i:s'),
        ]);

        if (count($this->logs) > 400) {
            array_pop($this->logs);
        }
    }

    private function resolveLevelColor(string $level): string
    {
        return match (strtolower($level)) {
            'error'   => 'bg-red-900/60 text-red-300 border border-red-700/50',
            'warning' => 'bg-amber-900/60 text-amber-300 border border-amber-700/50',
            'info'    => 'bg-sky-900/60 text-sky-300 border border-sky-700/50',
            'debug'   => 'bg-slate-700/60 text-slate-300 border border-slate-600/50',
            default   => 'bg-slate-700/60 text-slate-300 border border-slate-600/50',
        };
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    private function activeProjectIds(): array
    {
        if (empty($this->projectFilter)) {
            return array_column($this->userProjects, 'id');
        }
        return array_values(array_map('intval', $this->projectFilter));
    }

    public function toggleProjectFilter(int $id): void
    {
        if (in_array($id, $this->projectFilter)) {
            $this->projectFilter = array_values(array_filter($this->projectFilter, fn($v) => $v !== $id));
        } else {
            $this->projectFilter[] = $id;
        }
        $this->loadLogs();
    }

    public function clearProjectFilter(): void
    {
        $this->projectFilter = [];
        $this->loadLogs();
    }

    // ── Refresh / clear ─────────────────────────────────────────────────────
    public function refreshLogs(): void  { $this->loadLogs(); }
    public function clearDisplay(): void { $this->logs = []; }

    // ── Token panel ─────────────────────────────────────────────────────────
    public function openTokenPanel(?int $projectId = null): void
    {
        $this->tokenPanelProjId = $projectId;
        $this->showTokenPanel   = true;
    }

    public function rotateToken(int $projectId): void
    {
        $proj = Project::where('id', $projectId)
            ->where(function ($q) {
                $q->where('owner_id', auth()->id())
                  ->orWhereHas('members', fn ($sq) => $sq->where('user_id', auth()->id()));
            })->first();

        if (!$proj) return;

        $proj->update(['webhook_token' => bin2hex(random_bytes(32))]);

        // Refresh local project list with new token
        $this->userProjects = Project::where(function ($q) {
            $q->where('owner_id', auth()->id())
              ->orWhereHas('members', fn ($sq) => $sq->where('user_id', auth()->id()));
        })->orderBy('name')->get(['id', 'name', 'color', 'webhook_token'])->toArray();
    }

    // ── Convert to task ─────────────────────────────────────────────────────
    public function openConvertModal(int $logId): void
    {
        $log = ProjectLog::find($logId);
        if (!$log) return;

        $this->taskLogId     = $logId;
        $this->taskProjectId = $log->project_id;
        $this->taskTitle     = '[' . strtoupper($log->level) . '] ' . mb_strimwidth($log->message, 0, 120, '...');
        $this->showTaskModal = true;
    }

    public function cancelTaskModal(): void
    {
        $this->showTaskModal = false;
        $this->taskLogId     = null;
        $this->taskTitle     = '';
        $this->taskProjectId = null;
    }

    public function createTask(): void
    {
        if (!$this->taskTitle || !$this->taskProjectId) return;

        $column = KanbanColumn::where('project_id', $this->taskProjectId)
            ->orderBy('position')
            ->first();

        if (!$column) {
            session()->flash('task_error', 'No Kanban columns found for this project.');
            $this->cancelTaskModal();
            return;
        }

        $maxPos = KanbanCard::where('column_id', $column->id)->max('position') ?? 0;

        $desc = '';
        if ($this->taskLogId) {
            $log  = ProjectLog::find($this->taskLogId);
            $parts = [];
            if ($log?->source_app)  $parts[] = "Source: {$log->source_app}";
            if ($log?->environment) $parts[] = "Env: {$log->environment}";
            if ($log?->stack_trace) $parts[] = "\n\n```\n{$log->stack_trace}\n```";
            $desc = implode("\n", $parts);
        }

        KanbanCard::create([
            'column_id'   => $column->id,
            'project_id'  => $this->taskProjectId,
            'created_by'  => auth()->id(),
            'title'       => $this->taskTitle,
            'description' => $desc,
            'priority'    => 2,
            'position'    => $maxPos + 1,
        ]);

        session()->flash('task_created', 'Task created in Kanban!');
        $this->cancelTaskModal();
    }
};
?>

<div class="h-full flex flex-col bg-slate-950 text-slate-100 font-mono overflow-hidden">

    {{-- ═══ FILTER BAR ══════════════════════════════════════════════════════ --}}
    <div class="shrink-0 bg-slate-900 border-b border-slate-800">

        {{-- Row 1: project chips + search + actions --}}
        <div class="px-4 py-3 flex flex-wrap items-center gap-2">

            {{-- "All Projects" chip --}}
            <button wire:click="clearProjectFilter"
                    class="px-2.5 py-1 rounded-full text-[11px] font-medium border transition-colors
                        {{ empty($projectFilter)
                            ? 'bg-indigo-600 border-indigo-500 text-white'
                            : 'bg-slate-800 border-slate-700 text-slate-400 hover:border-slate-500 hover:text-slate-200' }}">
                All Projects
            </button>

            {{-- Individual project chips --}}
            @foreach($userProjects as $proj)
            <button wire:click="toggleProjectFilter({{ $proj['id'] }})"
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border transition-colors
                        {{ in_array($proj['id'], $projectFilter)
                            ? 'border-indigo-500 text-white bg-indigo-600/30'
                            : 'bg-slate-800 border-slate-700 text-slate-400 hover:border-slate-500 hover:text-slate-200' }}">
                <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: {{ $proj['color'] ?? '#6366f1' }}"></span>
                {{ $proj['name'] }}
            </button>
            @endforeach

            {{-- Tokens button --}}
            <button wire:click="openTokenPanel(null)"
                    class="ml-auto flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 border border-slate-700 text-slate-400 hover:text-slate-200 hover:border-slate-500 transition-colors shrink-0">
                <span class="material-symbols-outlined text-sm">key</span>
                Tokens
            </button>
        </div>

        {{-- Row 2: level filter + search + live + refresh + clear --}}
        <div class="px-4 pb-3 flex flex-wrap items-center gap-2">

            {{-- Level chips --}}
            @foreach(['all' => 'All', 'error' => 'Error', 'warning' => 'Warn', 'info' => 'Info', 'debug' => 'Debug'] as $value => $label)
            <button wire:click="$set('levelFilter', '{{ $value }}')"
                    class="px-2.5 py-0.5 rounded-full text-[11px] font-medium border transition-colors
                        {{ $levelFilter === $value
                            ? 'bg-indigo-600 border-indigo-500 text-white'
                            : 'bg-slate-800 border-slate-700 text-slate-400 hover:border-slate-500 hover:text-slate-200' }}">
                {{ $label }}
            </button>
            @endforeach

            {{-- Search --}}
            <div class="relative ml-1">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-600 text-sm pointer-events-none">search</span>
                <input wire:model.live.debounce.400ms="search"
                       type="text"
                       placeholder="Search message / source..."
                       class="bg-slate-800 border border-slate-700 text-slate-200 text-xs rounded-lg pl-8 pr-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/60 w-52 placeholder-slate-600">
            </div>

            <div class="ml-auto flex items-center gap-2">
                {{-- Live / Pause toggle --}}
                <button wire:click="$toggle('paused')"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                            {{ $paused
                                ? 'bg-amber-900/40 border-amber-700/50 text-amber-300 hover:bg-amber-900/60'
                                : 'bg-emerald-900/40 border-emerald-700/50 text-emerald-300 hover:bg-emerald-900/60' }}">
                    <span class="relative flex h-2 w-2 shrink-0">
                        @unless($paused)
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        @endunless
                        <span class="relative inline-flex rounded-full h-2 w-2 {{ $paused ? 'bg-amber-400' : 'bg-emerald-500' }}"></span>
                    </span>
                    {{ $paused ? 'Paused' : 'Live' }}
                </button>

                <button wire:click="refreshLogs"
                        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 border border-slate-700 text-slate-400 hover:text-slate-200 hover:border-slate-500 transition-colors">
                    <span class="material-symbols-outlined text-sm" wire:loading.class="animate-spin" wire:target="refreshLogs">refresh</span>
                    Reload
                </button>

                <button wire:click="clearDisplay"
                        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 border border-slate-700 text-slate-400 hover:text-red-400 hover:border-red-800 transition-colors">
                    <span class="material-symbols-outlined text-sm">close</span>
                    Clear
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ FLASH MESSAGES ══════════════════════════════════════════════════ --}}
    @if(session('task_created'))
    <div class="shrink-0 bg-emerald-900/30 border-b border-emerald-800/50 px-4 py-2 text-emerald-300 text-xs flex items-center gap-2">
        <span class="material-symbols-outlined text-base">check_circle</span>
        {{ session('task_created') }}
    </div>
    @endif
    @if(session('task_error'))
    <div class="shrink-0 bg-red-900/30 border-b border-red-800/50 px-4 py-2 text-red-300 text-xs flex items-center gap-2">
        <span class="material-symbols-outlined text-base">error</span>
        {{ session('task_error') }}
    </div>
    @endif

    {{-- ═══ LOG STREAM ══════════════════════════════════════════════════════ --}}
    <div class="flex-1 overflow-y-auto" wire:loading.class="opacity-50"
         wire:target="refreshLogs,updatedProjectFilter,updatedSearch,updatedLevelFilter,clearProjectFilter">

        @if(empty($logs))
        <div class="flex flex-col items-center justify-center h-full gap-3 select-none">
            <div class="text-slate-700 text-4xl font-bold tracking-tighter">[ ]</div>
            <p class="text-slate-600 text-xs">No logs match the current filters.</p>
            <p class="text-slate-700 text-[11px]">Send a log via <span class="text-indigo-500">POST /api/logs/{projectId}</span> with the project token.</p>
        </div>
        @else

        {{-- Column header --}}
        <div class="sticky top-0 bg-slate-900/95 backdrop-blur border-b border-slate-800 px-4 py-1.5 text-[10px] text-slate-600 uppercase tracking-widest flex items-center gap-3 select-none z-10">
            <span class="w-[130px] shrink-0">Timestamp</span>
            <span class="w-[68px] shrink-0">Level</span>
            <span class="w-[110px] shrink-0 hidden sm:block">Project</span>
            <span class="w-[90px] shrink-0 hidden md:block">Source</span>
            <span class="flex-1">Message</span>
            <span class="w-[80px] shrink-0 hidden lg:block text-right">Action</span>
        </div>

        <div class="divide-y divide-slate-800/50">
            @foreach($logs as $log)
            <div x-data="{ expanded: false }"
                 class="group px-4 py-2.5 hover:bg-slate-900/60 transition-colors text-[11px] sm:text-xs">
                <div class="flex items-start gap-3">

                    {{-- Timestamp --}}
                    <span class="text-slate-600 shrink-0 w-[130px] pt-px tabular-nums leading-tight">{{ $log['created_at'] }}</span>

                    {{-- Level --}}
                    <span class="shrink-0 w-[68px]">
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $log['level_color'] }}">
                            {{ $log['level'] }}
                        </span>
                    </span>

                    {{-- Project name --}}
                    <span class="hidden sm:flex items-center gap-1.5 shrink-0 w-[110px] truncate">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: {{ $log['project_color'] }}"></span>
                        <span class="text-slate-400 truncate text-[11px]">{{ $log['project_name'] }}</span>
                    </span>

                    {{-- Source --}}
                    <span class="text-slate-500 shrink-0 w-[90px] truncate hidden md:block">
                        {{ $log['source_app'] ?? ($log['channel'] ?? '—') }}
                    </span>

                    {{-- Message --}}
                    <div class="flex-1 min-w-0">
                        <span class="text-slate-200 leading-snug break-words">{{ $log['message'] }}</span>

                        @if($log['environment'])
                        <span class="ml-2 px-1.5 py-0.5 rounded text-[9px] bg-slate-800 text-slate-500 border border-slate-700 align-middle">
                            {{ $log['environment'] }}
                        </span>
                        @endif

                        @if(!empty($log['context']))
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach($log['context'] as $k => $v)
                            @if(!is_array($v))
                            <span class="px-1.5 py-px rounded text-[9px] bg-slate-800/80 text-slate-500 border border-slate-700/50">{{ $k }}: {{ $v }}</span>
                            @endif
                            @endforeach
                        </div>
                        @endif

                        @if($log['stack_trace'])
                        <button @click="expanded = !expanded"
                                class="mt-1 text-[10px] text-indigo-500 hover:text-indigo-300 transition-colors flex items-center gap-0.5">
                            <span class="material-symbols-outlined text-xs" x-text="expanded ? 'expand_less' : 'expand_more'"></span>
                            <span x-text="expanded ? 'Hide trace' : 'Show trace'"></span>
                        </button>
                        <pre x-show="expanded" x-cloak
                             class="mt-2 p-3 bg-slate-900 border border-slate-800 rounded text-[10px] text-red-300/80 overflow-x-auto leading-relaxed whitespace-pre-wrap break-words max-h-64 overflow-y-auto">{{ $log['stack_trace'] }}</pre>
                        @endif
                    </div>

                    {{-- Convert to task --}}
                    @if($log['id'])
                    <div class="shrink-0 hidden lg:block text-right">
                        <button wire:click="openConvertModal({{ $log['id'] }})"
                                class="opacity-0 group-hover:opacity-100 transition-opacity px-2 py-1 rounded text-[10px] font-medium bg-indigo-600/20 border border-indigo-600/40 text-indigo-400 hover:bg-indigo-600/40 hover:text-indigo-200 whitespace-nowrap">
                            <span class="material-symbols-outlined text-[11px] align-text-bottom">add_task</span> Task
                        </button>
                    </div>
                    @endif

                </div>
            </div>
            @endforeach
        </div>

        @endif
    </div>

    {{-- ═══ STATUS BAR ══════════════════════════════════════════════════════ --}}
    <div class="shrink-0 bg-slate-900 border-t border-slate-800 px-4 py-1.5 flex items-center gap-4 text-[10px] text-slate-600 select-none">
        <span>{{ count($logs) }} entries</span>
        @if(!empty($projectFilter))
            <span>{{ count($projectFilter) }} project(s) selected</span>
        @else
            <span>{{ count($userProjects) }} project(s)</span>
        @endif
        <span class="ml-auto" wire:loading wire:target="refreshLogs,loadLogs">
            <span class="animate-pulse text-indigo-400">Loading...</span>
        </span>
    </div>

    {{-- ═══ TOKENS PANEL ════════════════════════════════════════════════════ --}}
    @if($showTokenPanel)
    <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
         wire:click.self="$set('showTokenPanel', false)">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl w-full max-w-2xl p-6 font-sans max-h-[85vh] flex flex-col"
             @keydown.escape.window="$wire.set('showTokenPanel', false)">

            <div class="flex items-center justify-between mb-5 shrink-0">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-indigo-400 text-2xl">key</span>
                    <h2 class="text-white font-bold text-lg">Webhook Tokens</h2>
                </div>
                <button wire:click="$set('showTokenPanel', false)"
                        class="text-slate-500 hover:text-slate-300 transition-colors p-1">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <p class="text-slate-500 text-xs mb-5 shrink-0">
                Each project has its own token. Use it to authenticate <code class="bg-slate-800 px-1 rounded text-indigo-400">POST /api/logs/{projectId}</code> requests.
            </p>

            <div class="overflow-y-auto flex-1 space-y-4 pr-1">
                @foreach($userProjects as $proj)
                <div class="bg-slate-800/60 border border-slate-700 rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $proj['color'] ?? '#6366f1' }}"></span>
                        <span class="text-white font-semibold text-sm">{{ $proj['name'] }}</span>
                        <span class="ml-auto text-slate-600 text-[10px]">ID: {{ $proj['id'] }}</span>
                    </div>

                    {{-- Endpoint --}}
                    <p class="text-[11px] text-slate-500 mb-1.5 font-mono">Endpoint</p>
                    <div class="flex items-center gap-2 mb-3"
                         x-data x-on:click="navigator.clipboard.writeText('POST {{ url('/api/logs/' . $proj['id']) }}'); $dispatch('toast', 'Copied!')">
                        <code class="flex-1 bg-slate-900 text-indigo-400 text-xs px-3 py-2 rounded-lg border border-slate-700 truncate cursor-pointer hover:border-indigo-600/50 transition-colors">
                            POST {{ url('/api/logs/' . $proj['id']) }}
                        </code>
                        <span class="material-symbols-outlined text-slate-600 text-base cursor-pointer hover:text-slate-300 shrink-0">content_copy</span>
                    </div>

                    {{-- Token --}}
                    <p class="text-[11px] text-slate-500 mb-1.5 font-mono">Token (Authorization: Bearer ...)</p>
                    <div class="flex items-center gap-2"
                         x-data="{ show: false }">
                        <code x-show="show" class="flex-1 bg-slate-900 text-emerald-400 text-[11px] px-3 py-2 rounded-lg border border-slate-700 break-all leading-relaxed cursor-pointer"
                              x-on:click="navigator.clipboard.writeText('{{ $proj['webhook_token'] }}')">{{ $proj['webhook_token'] }}</code>
                        <code x-show="!show" class="flex-1 bg-slate-900 text-slate-600 text-xs px-3 py-2 rounded-lg border border-slate-700 select-none">••••••••••••••••••••••••••••••••••••••••</code>
                        <button @click="show = !show"
                                class="shrink-0 text-slate-500 hover:text-slate-200 transition-colors p-1.5 rounded-lg hover:bg-slate-700">
                            <span class="material-symbols-outlined text-base" x-text="show ? 'visibility_off' : 'visibility'"></span>
                        </button>
                        <button wire:click="rotateToken({{ $proj['id'] }})"
                                wire:confirm="Rotate this token? All integrations using the old token will stop working."
                                class="shrink-0 text-slate-500 hover:text-amber-400 transition-colors p-1.5 rounded-lg hover:bg-amber-900/20"
                                title="Rotate token">
                            <span class="material-symbols-outlined text-base" wire:loading.remove wire:target="rotateToken({{ $proj['id'] }})">autorenew</span>
                            <span wire:loading wire:target="rotateToken({{ $proj['id'] }})" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                        </button>
                    </div>

                    {{-- curl example --}}
                    <details class="mt-3 open:mt-3">
                        <summary class="text-[10px] text-slate-600 cursor-pointer hover:text-slate-400 transition-colors select-none">curl example</summary>
                        <pre class="mt-2 bg-slate-950 text-slate-400 text-[10px] p-3 rounded-lg border border-slate-800 overflow-x-auto leading-relaxed whitespace-pre">curl -X POST {{ url('/api/logs/' . $proj['id']) }} \
  -H "Authorization: Bearer {{ $proj['webhook_token'] }}" \
  -H "Content-Type: application/json" \
  -d '{"level":"error","message":"Something broke","source_app":"my-app","environment":"production"}'</pre>
                    </details>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ═══ CONVERT-TO-TASK MODAL ══════════════════════════════════════════ --}}
    @if($showTaskModal)
    <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
         wire:click.self="cancelTaskModal">
        <div class="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl w-full max-w-lg p-6 font-sans"
             @keydown.escape.window="$wire.cancelTaskModal()">

            <div class="flex items-center gap-3 mb-5">
                <span class="material-symbols-outlined text-indigo-400 text-2xl">add_task</span>
                <h2 class="text-white font-bold text-lg">Convert to Kanban Task</h2>
            </div>

            <label class="block text-slate-400 text-xs font-medium mb-1.5">Task Title</label>
            <textarea wire:model.defer="taskTitle" rows="3"
                      class="w-full bg-slate-800 border border-slate-700 text-slate-100 text-sm rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500/60 resize-none placeholder-slate-600"
                      placeholder="Describe the task..."></textarea>

            <p class="mt-2 text-slate-600 text-xs">
                Will be added to the first Kanban column of the originating project with <strong class="text-slate-500">Medium</strong> priority.
            </p>

            <div class="flex items-center justify-end gap-3 mt-6">
                <button wire:click="cancelTaskModal"
                        class="px-4 py-2 rounded-xl text-sm font-medium text-slate-400 hover:bg-slate-800 transition-colors border border-slate-700">
                    Cancel
                </button>
                <button wire:click="createTask"
                        class="px-5 py-2 rounded-xl text-sm font-bold bg-indigo-600 hover:bg-indigo-500 text-white transition-colors shadow-lg shadow-indigo-600/20">
                    <span wire:loading.remove wire:target="createTask">Create Task</span>
                    <span wire:loading wire:target="createTask">Creating...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

</div>

