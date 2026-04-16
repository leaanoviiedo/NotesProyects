<?php
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public string $title = 'Notes — DevOS Pro';
};
?>
<div class="h-full flex flex-col" x-data="{ activeView: 'list' }">

    {{-- Mobile panel toggle --}}
    <div class="md:hidden flex gap-2 p-3 border-b border-surface-variant bg-surface-container-low shrink-0">
        <button
            @click="activeView = 'list'"
            :class="activeView === 'list' ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-slate-500'"
            class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1"
        >
            <span class="material-symbols-outlined text-base">list</span> All Notes
        </button>
        <button
            @click="activeView = 'editor'"
            :class="activeView === 'editor' ? 'bg-indigo-50 text-indigo-600 font-semibold' : 'text-slate-500'"
            class="flex-1 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1"
        >
            <span class="material-symbols-outlined text-base">edit_note</span> Editor
        </button>
    </div>

    <div class="flex flex-1 overflow-hidden min-h-0">

        {{-- Notes list panel --}}
        <div :class="activeView === 'list' ? 'flex' : 'hidden md:flex'" class="flex-col w-full md:w-80 bg-surface-container-low shrink-0">
            <div class="p-4 sm:p-6 border-b border-surface-variant/20 shrink-0">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-label font-bold text-lg tracking-tight">Recent Notes</h3>
                    <button class="bg-white p-1.5 rounded-lg shadow-sm border border-slate-100 hover:bg-slate-50 transition-colors">
                        <span class="material-symbols-outlined text-sm">edit_note</span>
                    </button>
                </div>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">search</span>
                    <input
                        class="w-full bg-white border-none rounded-lg pl-9 pr-4 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all"
                        placeholder="Filter notes..."
                        type="text"
                    />
                </div>
            </div>
            <div class="flex-1 overflow-y-auto">
                <div class="p-2 space-y-1">
                    <button @click="activeView = 'editor'" class="w-full text-left p-4 rounded-xl bg-surface-container-lowest shadow-sm border-l-2 border-primary">
                        <p class="font-label text-[10px] text-primary font-bold uppercase mb-1">Architecture</p>
                        <h4 class="text-sm font-semibold text-on-background line-clamp-1">Redis Caching Strategy v2</h4>
                        <p class="text-xs text-on-surface-variant mt-1 line-clamp-2">Moving from simple string values to hash sets for user session management...</p>
                        <p class="font-label text-[10px] text-slate-400 mt-3">UPDATED 2H AGO</p>
                    </button>
                    <button @click="activeView = 'editor'" class="w-full text-left p-4 rounded-xl hover:bg-white/50 transition-colors">
                        <p class="font-label text-[10px] text-on-surface-variant font-bold uppercase mb-1">Quick Snippets</p>
                        <h4 class="text-sm font-medium text-on-background line-clamp-1">Go Interface Mocking Pattern</h4>
                        <p class="text-xs text-on-surface-variant mt-1 line-clamp-2">Example of how we handle unit tests for the gRPC services.</p>
                        <p class="font-label text-[10px] text-slate-400 mt-3">UPDATED 1D AGO</p>
                    </button>
                    <button @click="activeView = 'editor'" class="w-full text-left p-4 rounded-xl hover:bg-white/50 transition-colors">
                        <p class="font-label text-[10px] text-on-surface-variant font-bold uppercase mb-1">Deploy Log</p>
                        <h4 class="text-sm font-medium text-on-background line-clamp-1">Post-Mortem: Oct 10 Incident</h4>
                        <p class="text-xs text-on-surface-variant mt-1 line-clamp-2">Analysis of the load balancer failure during the morning spike.</p>
                        <p class="font-label text-[10px] text-slate-400 mt-3">UPDATED 3D AGO</p>
                    </button>
                    <button @click="activeView = 'editor'" class="w-full text-left p-4 rounded-xl hover:bg-white/50 transition-colors">
                        <p class="font-label text-[10px] text-on-surface-variant font-bold uppercase mb-1">Patterns</p>
                        <h4 class="text-sm font-medium text-on-background line-clamp-1">Event Sourcing with Kafka</h4>
                        <p class="text-xs text-on-surface-variant mt-1 line-clamp-2">Notes on implementing CQRS and event sourcing architecture.</p>
                        <p class="font-label text-[10px] text-slate-400 mt-3">UPDATED 5D AGO</p>
                    </button>
                </div>
            </div>
        </div>

        {{-- Hinge divider (desktop) --}}
        <div class="hidden md:block w-3 bg-surface-container-low h-full shrink-0"></div>

        {{-- Editor panel --}}
        <div :class="activeView === 'editor' ? 'flex' : 'hidden md:flex'" class="flex-1 flex-col bg-surface-container-lowest overflow-y-auto">
            {{-- Mobile back button --}}
            <div class="md:hidden p-3 border-b border-surface-variant sticky top-0 bg-surface-container-lowest z-10 shrink-0">
                <button @click="activeView = 'list'" class="flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-on-background transition-colors">
                    <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Notes
                </button>
            </div>
            <div class="max-w-4xl mx-auto w-full px-6 sm:px-12 py-8 sm:py-16">
                <div class="mb-8 sm:mb-10">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <span class="font-label bg-indigo-50 text-indigo-600 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-widest">Active Draft</span>
                        <span class="text-slate-300">&bull;</span>
                        <span class="font-label text-slate-400 text-[10px] uppercase tracking-widest">Modified: Today, 2:45 PM</span>
                    </div>
                    <h1 class="text-2xl sm:text-4xl font-bold text-on-background tracking-tight mb-4">Go Interface Mocking Pattern</h1>
                    <div class="flex flex-wrap gap-4">
                        <div class="flex items-center gap-1.5 text-xs text-on-surface-variant">
                            <span class="material-symbols-outlined text-base">person</span>
                            <span>Lead Architect</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs text-on-surface-variant">
                            <span class="material-symbols-outlined text-base">sell</span>
                            <span>Go, Testing, Patterns</span>
                        </div>
                    </div>
                </div>
                <article class="prose prose-slate max-w-none text-on-surface leading-relaxed">
                    <p class="text-base sm:text-lg text-on-surface-variant mb-6">When designing services in Go, we prioritize interfaces to keep our code modular and testable. The following pattern demonstrates how we mock dependencies without external libraries like Mockery.</p>
                    <h3 class="font-label text-xl font-bold mb-4 text-on-background">Implementation Example</h3>
                    <div class="bg-inverse-surface rounded-xl overflow-hidden shadow-2xl mb-8">
                        <div class="bg-[#1f2635] px-4 py-2.5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-red-500/30"></div>
                                    <div class="w-3 h-3 rounded-full bg-amber-500/30"></div>
                                    <div class="w-3 h-3 rounded-full bg-green-500/30"></div>
                                </div>
                                <span class="font-label text-[10px] text-slate-500 ml-4 uppercase tracking-widest">main.go</span>
                            </div>
                            <button class="text-slate-500 hover:text-white transition-colors">
                                <span class="material-symbols-outlined text-sm">content_copy</span>
                            </button>
                        </div>
                        <pre class="p-4 sm:p-6 text-xs sm:text-sm overflow-x-auto text-slate-300"><code class="font-mono"><span class="text-primary-fixed-dim">package</span> main

<span class="text-primary-fixed-dim">type</span> <span class="text-tertiary-fixed-dim">DataStore</span> <span class="text-primary-fixed-dim">interface</span> {
    <span class="text-on-primary-container">GetUser</span>(id <span class="text-tertiary-fixed-dim">string</span>) (*<span class="text-tertiary-fixed-dim">User</span>, <span class="text-tertiary-fixed-dim">error</span>)
}

<span class="text-slate-500">// MockStore implements DataStore for testing</span>
<span class="text-primary-fixed-dim">type</span> <span class="text-tertiary-fixed-dim">MockStore</span> <span class="text-primary-fixed-dim">struct</span> {
    <span class="text-on-primary-container">GetUserFunc</span> <span class="text-primary-fixed-dim">func</span>(id <span class="text-tertiary-fixed-dim">string</span>) (*<span class="text-tertiary-fixed-dim">User</span>, <span class="text-tertiary-fixed-dim">error</span>)
}

<span class="text-primary-fixed-dim">func</span> (m *<span class="text-tertiary-fixed-dim">MockStore</span>) <span class="text-on-primary-container">GetUser</span>(id <span class="text-tertiary-fixed-dim">string</span>) (*<span class="text-tertiary-fixed-dim">User</span>, <span class="text-tertiary-fixed-dim">error</span>) {
    <span class="text-primary-fixed-dim">return</span> m.<span class="text-on-primary-container">GetUserFunc</span>(id)
}</code></pre>
                    </div>
                    <p class="mb-4 text-sm sm:text-base">This approach allows for per-test behavior modification without complex setup routines. Ensure all new services follow the <code class="bg-surface-container-low px-1.5 py-0.5 rounded text-primary text-sm font-mono">Service-Repository</code> separation logic as discussed in the Q3 review.</p>
                </article>
            </div>
        </div>

    </div>
</div>
