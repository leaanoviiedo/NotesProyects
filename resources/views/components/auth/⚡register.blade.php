<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

new #[Layout('layouts.auth')] class extends Component {
    #[Rule('required|string|max:255')] public string $name = '';
    #[Rule('required|email|unique:users,email')] public string $email = '';
    #[Rule('required|min:8')] public string $password = '';
    #[Rule('required|same:password')] public string $password_confirmation = '';

    public function register(): void
    {
        $this->validate();
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);
        // Auto-create personal project for the user
        Project::create([
            'name' => 'Personal',
            'description' => 'Your personal workspace',
            'color' => '#4f46e5',
            'icon' => 'person',
            'owner_id' => $user->id,
            'is_personal' => true,
            'is_favorite' => true,
        ]);
        Auth::login($user);
        session()->regenerate();
        $this->redirect(route('dashboard'), navigate: true);
    }
};
?>
<div class="bg-surface-container-lowest rounded-2xl shadow-xl p-8 border border-outline-variant/30">
    <h2 class="text-xl font-bold text-on-background mb-1">Crear cuenta</h2>
    <p class="text-on-surface-variant text-sm mb-6">Empieza a organizar tu trabajo hoy</p>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-on-surface mb-1">Nombre</label>
            <input wire:model="name" type="text" autocomplete="name" placeholder="Tu nombre"
                class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2.5 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" />
            @error('name')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-on-surface mb-1">Email</label>
            <input wire:model="email" type="email" autocomplete="email" placeholder="you@example.com"
                class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2.5 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" />
            @error('email')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-on-surface mb-1">Contraseña</label>
            <input wire:model="password" type="password" autocomplete="new-password" placeholder="Mín. 8 caracteres"
                class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2.5 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" />
            @error('password')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-on-surface mb-1">Confirmar contraseña</label>
            <input wire:model="password_confirmation" type="password" autocomplete="new-password" placeholder="Repite la contraseña"
                class="w-full rounded-xl border border-outline-variant bg-surface-container px-4 py-2.5 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" />
            @error('password_confirmation')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <button type="submit"
            class="w-full bg-primary text-on-primary font-semibold rounded-xl py-2.5 hover:bg-primary/90 transition flex items-center justify-center gap-2" wire:loading.attr="disabled">
            <span wire:loading.remove>Crear cuenta</span>
            <span wire:loading class="material-symbols-outlined animate-spin text-base">progress_activity</span>
        </button>
    </form>
    <p class="text-center text-sm text-on-surface-variant mt-6">
        ¿Ya tienes una cuenta? <a href="{{ route('login') }}" wire:navigate class="text-primary font-medium hover:underline">Inicia sesión</a>
    </p>
</div>