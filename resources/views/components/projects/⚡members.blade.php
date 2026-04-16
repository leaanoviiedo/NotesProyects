<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Project;
use App\Models\User;

new #[Layout('layouts.app')] class extends Component {
    public Project $project;
    public $members;
    public string $inviteEmail = '';
    public string $inviteRole = 'editor';
    public string $error = '';

    public function mount(Project $project): void {
        abort_unless(
            $project->owner_id === auth()->id() ||
            $project->members()->where('user_id', auth()->id())->whereIn('role', ['admin'])->exists(),
            403
        );
        $this->project = $project;
        $this->loadMembers();
    }

    public function loadMembers(): void {
        $this->members = $this->project->members()->withPivot('role','can_kanban','can_notes','can_calendar','joined_at')->get();
    }

    public function invite(): void {
        $this->validate(['inviteEmail' => 'required|email', 'inviteRole' => 'required|in:viewer,editor,admin']);
        $user = User::where('email', $this->inviteEmail)->first();
        if (!$user) { $this->error = 'No se encontró usuario con ese correo.'; return; }
        if ($user->id === $this->project->owner_id) { $this->error = 'El propietario no puede ser miembro.'; return; }
        $this->project->members()->syncWithoutDetaching([$user->id => [
            'role' => $this->inviteRole,
            'can_kanban' => true, 'can_notes' => true, 'can_calendar' => false,
            'joined_at' => now(),
        ]]);
        $this->error = '';
        $this->inviteEmail = '';
        $this->loadMembers();
    }

    public function changeRole(int $userId, string $role): void {
        $this->project->members()->updateExistingPivot($userId, ['role' => $role]);
        $this->loadMembers();
    }

    public function removeMember(int $userId): void {
        $this->project->members()->detach($userId);
        $this->loadMembers();
    }
};
?>
<div class="p-4 md:p-6 space-y-6 max-w-2xl">
    <div class="flex items-center gap-3">
        <a href="{{ route('projects') }}" wire:navigate class="text-on-surface-variant hover:text-on-surface">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h1 class="text-xl font-bold text-on-background">Miembros: {{ $project->name }}</h1>
    </div>

    <div class="bg-surface-container rounded-2xl p-5 space-y-4">
        <h2 class="font-semibold text-on-background">Invitar Miembro</h2>
        @if($error)<div class="text-error text-sm bg-error-container/20 rounded-lg px-3 py-2">{{ $error }}</div>@endif
        <div class="flex gap-3 flex-wrap">
            <input wire:model="inviteEmail" type="email" placeholder="user@example.com"
                class="flex-1 min-w-0 rounded-xl border border-outline-variant bg-surface-container px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
            <select wire:model="inviteRole"
                class="rounded-xl border border-outline-variant bg-surface-container px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="viewer">Visor</option>
                <option value="editor">Editor</option>
                <option value="admin">Admin</option>
            </select>
            <button wire:click="invite"
                class="px-4 py-2 bg-primary text-on-primary rounded-xl text-sm font-medium hover:bg-primary/90">Invitar</button>
        </div>
    </div>

    <div class="bg-surface-container rounded-2xl p-5 space-y-3">
        <h2 class="font-semibold text-on-background">Miembros Actuales</h2>
        @forelse($members as $member)
        <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-surface-container-high">
            <img src="{{ $member->avatar_url }}" class="w-9 h-9 rounded-full object-cover" alt="">
            <div class="flex-1 min-w-0">
                <p class="font-medium text-sm text-on-surface">{{ $member->name }}</p>
                <p class="text-xs text-on-surface-variant">{{ $member->email }}</p>
            </div>
            <select wire:change="changeRole({{ $member->id }}, $event.target.value)"
                class="rounded-xl border border-outline-variant bg-surface-container px-2 py-1 text-xs focus:outline-none">
                <option value="viewer" {{ $member->pivot->role === 'viewer' ? 'selected' : '' }}>Visor</option>
                <option value="editor" {{ $member->pivot->role === 'editor' ? 'selected' : '' }}>Editor</option>
                <option value="admin" {{ $member->pivot->role === 'admin' ? 'selected' : '' }}>Admin</option>
            </select>
            <button wire:click="removeMember({{ $member->id }})" wire:confirm="¿Eliminar este miembro?"
                class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-error-container/30 text-error">
                <span class="material-symbols-outlined text-sm">person_remove</span>
            </button>
        </div>
        @empty
        <p class="text-sm text-on-surface-variant text-center py-4">Sin miembros aún.</p>
        @endforelse
    </div>
</div>
</div>
</div>
