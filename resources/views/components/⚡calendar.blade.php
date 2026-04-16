<?php
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public string $title = 'Calendar — DevOS Pro';
};
?>
<div class="h-full flex items-center justify-center bg-surface">
    <div class="text-center max-w-sm px-6">
        <div class="w-16 h-16 bg-surface-container-high rounded-full flex items-center justify-center mx-auto mb-6">
            <span class="material-symbols-outlined text-primary text-3xl">calendar_month</span>
        </div>
        <h3 class="text-xl font-bold text-on-background mb-2">Team Schedule</h3>
        <p class="text-on-surface-variant text-sm mb-6">
            The interactive calendar view is currently being optimized for high-density team availability tracking.
        </p>
        <a href="{{ route('kanban') }}" class="text-primary font-semibold inline-flex items-center gap-2 hover:gap-3 transition-all">
            Back to Kanban <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </a>
    </div>
</div>
