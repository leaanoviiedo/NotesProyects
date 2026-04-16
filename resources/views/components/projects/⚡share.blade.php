<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Project;
use App\Models\ShareLink;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    public Project $project;
    public $links;
    public bool $canKanban = true;
    public bool $canNotes = true;
    public bool $canCalendar = false;
    public ?string $expiresAt = null;

    public function mount(Project $project): void {
        abort_unless(
            $project->owner_id === auth()->id() ||
            $project->members()->where('user_id', auth()->id())->whereIn('role', ['admin'])->exists(),
            403
        );
        $this->project = $project;
        $this->loadLinks();
    }

    public function loadLinks(): void {
        $this->links = $this->project->shareLinks()->with('creator')->latest()->get();
    }

    public function generate(): void {
        ShareLink::create([
            'token' => Str::random(64),
            'project_id' => $this->project->id,
            'created_by' => auth()->id(),
            'can_kanban' => $this->canKanban,
            'can_notes' => $this->canNotes,
            'can_calendar' => $this->canCalendar,
            'expires_at' => $this->expiresAt ?: null,
            'is_active' => true,
        ]);
        $this->loadLinks();
    }

    public function revoke(int $id): void {
        ShareLink::where('project_id', $this->project->id)->findOrFail($id)->update(['is_active' => false]);
        $this->loadLinks();
    }
};
?>
<div class="p-4 md:p-6 space-y-6 max-w-2xl">
    <div class="flex items-center gap-3">
        <a href="{{ route('projects') }}" wire:navigate class="text-on-surface-variant hover:text-on-surface">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h1 class="text-xl font-bold text-on-background">Share: {{ $project->name }}</h1>
    </div>

    <div class="bg-surface-container rounded-2xl p-5 space-y-4">
        <h2 class="font-semibold text-on-background">Generate Share Link</h2>
        <div class="flex flex-wrap gap-4">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="canKanban" class="accent-primary"> Kanban</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="canNotes" class="accent-primary"> Notes</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="canCalendar" class="accent-primary"> Calendar</label>
        </div>
        <div>
            <label class="block text-sm font-medium text-on-surface mb-1">Expires at (optional)</label>
            <input wire:model="expiresAt" type="datetime-local"
                class="rounded-xl border border-outline-variant bg-surface-container px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
        </div>
        <button wire:click="generate"
            class="flex items-center gap-2 px-4 py-2.5 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90 transition">
            <span class="material-symbols-outlined text-base">link</span> Generate Link
        </button>
    </div>

    <div class="bg-surface-container rounded-2xl p-5 space-y-3">
        <h2 class="font-semibold text-on-background">Active Links</h2>
        @forelse($links as $link)
        <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-container-high">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-mono text-on-surface-variant truncate">{{ $link->url }}</p>
                <p class="text-xs text-on-surface-variant mt-0.5">
                    Kanban: {{ $link->can_kanban ? '✓' : '✗' }} · Notes: {{ $link->can_notes ? '✓' : '✗' }} · Calendar: {{ $link->can_calendar ? '✓' : '✗' }}
                    @if($link->expires_at) · Expires: {{ $link->expires_at->format('M j, Y') }} @endif
                    @if(!$link->is_active) · <span class="text-error">Revoked</span> @endif
                </p>
            </div>
            @if($link->is_active)
            <button onclick="navigator.clipboard.writeText('{{ $link->url }}')"
                class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-surface-container-highest" title="Copy">
                <span class="material-symbols-outlined text-sm text-on-surface-variant">content_copy</span>
            </button>
            <button wire:click="revoke({{ $link->id }})" wire:confirm="Revoke this link?"
                class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-error-container/30 text-error" title="Revoke">
                <span class="material-symbols-outlined text-sm">link_off</span>
            </button>
            @endif
        </div>
        @empty
        <p class="text-sm text-on-surface-variant text-center py-4">No share links yet.</p>
        @endforelse
    </div>
</div>