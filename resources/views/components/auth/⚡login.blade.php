<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;

new #[Layout('layouts.auth')] class extends Component {
    #[Rule('required|email')] public string $email = '';
    #[Rule('required|min:8')] public string $password = '';
    public bool $remember = false;
    public string $error = '';

    public function login(): void
    {
        $this->validate();
        if (!auth()->attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->error = 'Credenciales incorrectas.';
            return;
        }
        session()->regenerate();
        $this->redirect(route('dashboard'), navigate: true);
    }
};
?>
<div class="bg-surface-container-lowest rounded-2xl shadow-xl p-8 border border-outline-variant/30">
    <h2 class="text-xl font-bold text-on-background mb-1">Iniciar sesión</h2>
    <p class="text-on-surface-variant text-sm mb-6">Bienvenido de vuelta a tu espacio</p>

    @if($error)
        <div class="bg-error-container text-on-error-container text-sm rounded-lg px-4 py-3 mb-4">{{ $error }}</div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-on-background mb-1.5">Email</label>
            <input wire:model="email" type="email" autocomplete="email"
                class="w-full bg-surface-container-low border border-outline-variant rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"
                placeholder="you@example.com" />
            @error('email')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-on-background mb-1.5">Contraseña</label>
            <input wire:model="password" type="password" autocomplete="current-password"
                class="w-full bg-surface-container-low border border-outline-variant rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"
                placeholder="••••••••" />
            @error('password')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center gap-2">
            <input wire:model="remember" type="checkbox" id="remember" class="rounded border-outline-variant text-primary" />
            <label for="remember" class="text-sm text-on-surface-variant">Recordarme</label>
        </div>
        <button type="submit"
            class="w-full bg-primary text-on-primary font-semibold py-2.5 rounded-xl hover:bg-primary/90 transition-all active:scale-[0.99] flex items-center justify-center gap-2">
            <span wire:loading.remove wire:target="login">Iniciar sesión</span>
            <span wire:loading wire:target="login" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Iniciando sesión...
            </span>
        </button>
    </form>

    <p class="text-center text-sm text-on-surface-variant mt-6">
        ¿Sin cuenta? <a href="{{ route('register') }}" class="text-primary font-semibold hover:underline">Créala</a>
    </p>
</div>
