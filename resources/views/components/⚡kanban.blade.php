<?php
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public string $title = 'Kanban — DevOS Pro';
};
?>
<div class="h-full p-4 sm:p-6 lg:p-8 flex flex-col">

    {{-- Sprint header --}}
    <div class="flex items-start sm:items-center justify-between mb-6 lg:mb-8 gap-4 shrink-0">
        <div>
            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-on-background">Sprint 14: Core Infrastructure</h2>
            <p class="text-on-surface-variant text-sm mt-1">Updated 2 minutes ago &bull; 14 Tasks Pending</p>
        </div>
        <div class="flex -space-x-2 shrink-0">
            <div class="h-8 w-8 rounded-full border-2 border-surface bg-indigo-400 flex items-center justify-center text-[10px] font-bold text-white">AL</div>
            <div class="h-8 w-8 rounded-full border-2 border-surface bg-violet-400 flex items-center justify-center text-[10px] font-bold text-white">MK</div>
            <div class="h-8 w-8 rounded-full border-2 border-surface bg-indigo-100 flex items-center justify-center text-[10px] font-bold text-indigo-600">+3</div>
        </div>
    </div>

    {{-- Kanban grid --}}
    <div class="flex-1 overflow-auto min-h-0">
        <div class="grid gap-4 lg:gap-6 h-full" style="grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); min-width: min(100%, 1120px);">

            {{-- BACKLOG --}}
            <div class="flex flex-col min-h-72 lg:min-h-0">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="font-label text-xs font-bold text-on-surface-variant uppercase tracking-wider">Backlog</span>
                        <span class="bg-surface-container-high px-2 py-0.5 rounded text-[10px] font-bold text-on-surface-variant">5</span>
                    </div>
                    <button class="p-1 hover:bg-surface-container-low rounded-md transition-colors text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">add</span>
                    </button>
                </div>
                <div class="flex-1 bg-surface-container-low rounded-xl p-3 space-y-3 overflow-y-auto">
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 border-slate-300">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-label text-[10px] font-bold px-2 py-0.5 bg-slate-100 text-slate-600 rounded uppercase">Database</span>
                            <span class="font-label text-[10px] text-on-surface-variant">Oct 12</span>
                        </div>
                        <h4 class="text-sm font-semibold text-on-background mb-1">Optimize SQL queries for dashboard v2</h4>
                        <p class="text-xs text-on-surface-variant leading-relaxed">Refactor join operations in the legacy reporting module to reduce latency by 40%.</p>
                    </div>
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 border-slate-300">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-label text-[10px] font-bold px-2 py-0.5 bg-slate-100 text-slate-600 rounded uppercase">Security</span>
                            <span class="font-label text-[10px] text-on-surface-variant">Oct 14</span>
                        </div>
                        <h4 class="text-sm font-semibold text-on-background mb-1">Implement OAuth2 MFA flows</h4>
                        <p class="text-xs text-on-surface-variant leading-relaxed">Integration with Google Authenticator for high-risk account logins.</p>
                    </div>
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 border-slate-300">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-label text-[10px] font-bold px-2 py-0.5 bg-slate-100 text-slate-600 rounded uppercase">Infra</span>
                            <span class="font-label text-[10px] text-on-surface-variant">Oct 18</span>
                        </div>
                        <h4 class="text-sm font-semibold text-on-background mb-1">Kubernetes HPA tuning</h4>
                        <p class="text-xs text-on-surface-variant leading-relaxed">Adjust pod autoscaler thresholds for the API gateway workload.</p>
                    </div>
                </div>
            </div>

            {{-- IN PROGRESS --}}
            <div class="flex flex-col min-h-72 lg:min-h-0">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="font-label text-xs font-bold text-indigo-600 uppercase tracking-wider">In Progress</span>
                        <span class="bg-indigo-100 px-2 py-0.5 rounded text-[10px] font-bold text-indigo-600">3</span>
                    </div>
                    <button class="p-1 hover:bg-surface-container-low rounded-md transition-colors text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">add</span>
                    </button>
                </div>
                <div class="flex-1 bg-surface-container-low rounded-xl p-3 space-y-3 overflow-y-auto">
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-md ring-1 ring-primary/10 border-l-4 border-primary">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-label text-[10px] font-bold px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded uppercase">Frontend</span>
                            <span class="font-label text-[10px] text-indigo-600 font-bold">Active</span>
                        </div>
                        <h4 class="text-sm font-semibold text-on-background mb-1">React-Table Concurrent Rendering</h4>
                        <div class="flex items-center gap-2 mt-4">
                            <div class="flex-1 h-1 bg-slate-100 rounded-full overflow-hidden">
                                <div class="w-3/4 h-full bg-primary rounded-full"></div>
                            </div>
                            <span class="font-label text-[10px] text-on-surface-variant">75%</span>
                        </div>
                    </div>
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 border-primary/50">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-label text-[10px] font-bold px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded uppercase">API</span>
                            <span class="font-label text-[10px] text-on-surface-variant">Oct 15</span>
                        </div>
                        <h4 class="text-sm font-semibold text-on-background mb-1">GraphQL Schema Federation</h4>
                        <div class="flex items-center gap-2 mt-4">
                            <div class="flex-1 h-1 bg-slate-100 rounded-full overflow-hidden">
                                <div class="w-1/3 h-full bg-primary rounded-full"></div>
                            </div>
                            <span class="font-label text-[10px] text-on-surface-variant">33%</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- REVIEW --}}
            <div class="flex flex-col min-h-72 lg:min-h-0">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="font-label text-xs font-bold text-tertiary-container uppercase tracking-wider">Review</span>
                        <span class="bg-tertiary-fixed px-2 py-0.5 rounded text-[10px] font-bold text-tertiary">2</span>
                    </div>
                    <button class="p-1 hover:bg-surface-container-low rounded-md transition-colors text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">add</span>
                    </button>
                </div>
                <div class="flex-1 bg-surface-container-low rounded-xl p-3 space-y-3 overflow-y-auto">
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 border-tertiary-container">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-label text-[10px] font-bold px-2 py-0.5 bg-orange-50 text-orange-600 rounded uppercase">DevOps</span>
                            <span class="font-label text-[10px] text-on-surface-variant">Today</span>
                        </div>
                        <h4 class="text-sm font-semibold text-on-background mb-1">GH Action: Docker Build Layer Caching</h4>
                        <p class="text-xs text-on-surface-variant leading-relaxed mt-1">Optimize CI pipeline build times using GitHub Actions cache.</p>
                    </div>
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 border-tertiary-container">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-label text-[10px] font-bold px-2 py-0.5 bg-orange-50 text-orange-600 rounded uppercase">Tests</span>
                            <span class="font-label text-[10px] text-on-surface-variant">Oct 16</span>
                        </div>
                        <h4 class="text-sm font-semibold text-on-background mb-1">E2E Coverage: Auth Flow</h4>
                    </div>
                </div>
            </div>

            {{-- DONE --}}
            <div class="flex flex-col min-h-72 lg:min-h-0">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <span class="font-label text-xs font-bold text-green-600 uppercase tracking-wider">Done</span>
                        <span class="bg-green-50 px-2 py-0.5 rounded text-[10px] font-bold text-green-600">8</span>
                    </div>
                    <button class="p-1 hover:bg-surface-container-low rounded-md transition-colors text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">add</span>
                    </button>
                </div>
                <div class="flex-1 bg-surface-container-low rounded-xl p-3 space-y-3 overflow-y-auto opacity-70">
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 border-green-500">
                        <h4 class="text-sm font-medium mb-1 line-through text-slate-400">Fix CSS Grid overflow on mobile</h4>
                    </div>
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 border-green-500">
                        <h4 class="text-sm font-medium mb-1 line-through text-slate-400">Update Node.js to v20 LTS</h4>
                    </div>
                    <div class="bg-surface-container-lowest p-4 rounded-xl shadow-sm border-l-4 border-green-500">
                        <h4 class="text-sm font-medium mb-1 line-through text-slate-400">Configure Lighthouse CI thresholds</h4>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Floating Add button --}}
    <div class="fixed bottom-20 md:bottom-8 right-4 sm:right-8 z-50">
        <button class="w-14 h-14 bg-primary text-on-primary rounded-full flex items-center justify-center shadow-xl shadow-primary/30 hover:scale-110 active:scale-95 transition-all">
            <span class="material-symbols-outlined text-2xl">add</span>
        </button>
    </div>

</div>
