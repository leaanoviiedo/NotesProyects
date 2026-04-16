<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\ApiTest;

new #[Layout('layouts.app')] class extends Component {

    #[Url]
    public ?int $activeTestId = null;

    public array $savedTests = [];
    public array $loadedTest = [];

    public function mount(): void
    {
        $this->loadSavedTests();
        if ($this->activeTestId) {
            $this->loadTest($this->activeTestId);
        }
    }

    private function loadSavedTests(): void
    {
        $this->savedTests = ApiTest::where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'method', 'url'])
            ->toArray();
    }

    public function saveTest(string $name, string $method, string $url, array $headers, string $body): void
    {
        $allowed = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
        $method  = in_array(strtoupper($method), $allowed) ? strtoupper($method) : 'GET';

        $data = [
            'user_id' => auth()->id(),
            'name'    => $name ?: 'Untitled Test',
            'method'  => $method,
            'url'     => $url,
            'headers' => array_values(array_filter($headers, fn($h) => !empty(trim($h['key'] ?? '')))),
            'body'    => $body,
        ];

        if ($this->activeTestId) {
            ApiTest::where('id', $this->activeTestId)
                ->where('user_id', auth()->id())
                ->update($data);
        } else {
            $test = ApiTest::create($data);
            $this->activeTestId = $test->id;
        }

        $this->loadSavedTests();
        $this->dispatch('test-saved');
    }

    public function loadTest(int $id): void
    {
        $test = ApiTest::where('id', $id)
            ->where('user_id', auth()->id())
            ->first(['id', 'name', 'method', 'url', 'headers', 'body']);

        if (!$test) return;

        $this->activeTestId = $id;
        $this->loadedTest   = [
            'id'      => $test->id,
            'name'    => $test->name,
            'method'  => $test->method,
            'url'     => $test->url ?? '',
            'headers' => $test->headers ?: [],
            'body'    => $test->body ?? '',
        ];
    }

    public function deleteTest(int $id): void
    {
        ApiTest::where('id', $id)->where('user_id', auth()->id())->delete();

        if ($this->activeTestId === $id) {
            $this->activeTestId = null;
            $this->loadedTest   = [];
        }

        $this->loadSavedTests();
    }

    public function newTest(): void
    {
        $this->activeTestId = null;
        $this->loadedTest   = [];
    }
};
?>
{{-- ═══════════════════════════════════════════════════════════════
     API TESTER — browser-side fetch, Livewire persistence
     ═══════════════════════════════════════════════════════════════ --}}
<div
    x-data="apiTester"
    class="flex h-full overflow-hidden bg-slate-950 text-slate-300 font-sans text-sm"
>
    {{-- ══════════ LEFT SIDEBAR — saved tests ══════════ --}}
    <aside class="w-64 shrink-0 flex flex-col border-r border-slate-700/50 bg-slate-900 overflow-hidden">

        {{-- header --}}
        <div class="px-4 py-3 border-b border-slate-700/50 flex items-center justify-between shrink-0">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Pruebas Guardadas</span>
            <button wire:click="newTest" @click="resetForm()"
                title="Nueva prueba"
                class="p-1 text-slate-500 hover:text-white hover:bg-slate-700 rounded-lg transition">
                <span class="material-symbols-outlined text-base leading-none">add</span>
            </button>
        </div>

        {{-- list --}}
        <div class="flex-1 overflow-y-auto py-1">
            @forelse($savedTests as $test)
            <div class="group relative flex items-stretch"
                 wire:key="st-{{ $test['id'] }}">
                <button
                    wire:click="loadTest({{ $test['id'] }})"
                    @click="response = null; error = null"
                    class="flex-1 min-w-0 text-left flex items-start gap-2.5 px-3 py-2.5 hover:bg-slate-800 transition-colors {{ $activeTestId === $test['id'] ? 'bg-slate-800 border-l-2 border-indigo-500' : 'border-l-2 border-transparent' }}">
                    <span class="text-[9px] font-bold font-mono px-1.5 py-0.5 rounded shrink-0 mt-0.5
                        @if($test['method'] === 'GET')    bg-emerald-900/60 text-emerald-400
                        @elseif($test['method'] === 'POST')   bg-blue-900/60   text-blue-400
                        @elseif($test['method'] === 'PUT')    bg-amber-900/60  text-amber-400
                        @elseif($test['method'] === 'DELETE') bg-red-900/60    text-red-400
                        @elseif($test['method'] === 'PATCH')  bg-purple-900/60 text-purple-400
                        @else                                  bg-slate-700     text-slate-400
                        @endif">{{ $test['method'] }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-300 truncate leading-tight">{{ $test['name'] }}</p>
                        <p class="text-[10px] text-slate-600 truncate mt-0.5">{{ $test['url'] }}</p>
                    </div>
                </button>
                <button wire:click="deleteTest({{ $test['id'] }})"
                    wire:confirm="¿Eliminar '{{ $test['name'] }}'?"
                    title="Delete"
                    class="px-2 text-slate-700 hover:text-red-400 opacity-0 group-hover:opacity-100 transition shrink-0">
                    <span class="material-symbols-outlined text-sm leading-none">close</span>
                </button>
            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-10 px-4 text-center">
                <span class="material-symbols-outlined text-3xl text-slate-700 mb-2">api</span>
                <p class="text-xs text-slate-600 italic">Sin pruebas guardadas.<br>Configura una solicitud y guárdala.</p>
            </div>
            @endforelse
        </div>
    </aside>

    {{-- ══════════ RIGHT PANEL — editor + response ══════════ --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        {{-- ── Name bar ── --}}
        <div class="px-4 py-2.5 border-b border-slate-700/50 flex items-center gap-3 shrink-0 bg-slate-900/60">
            <span class="material-symbols-outlined text-slate-600 text-base shrink-0">api</span>
            <input x-model="name" type="text" placeholder="Nombre de prueba…"
                class="flex-1 bg-transparent text-slate-200 text-sm font-medium placeholder-slate-600 focus:outline-none" />
            <button @click="save()" :disabled="saving"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs font-semibold rounded-lg transition shrink-0">
                <span class="material-symbols-outlined text-sm leading-none">save</span>
                <span x-text="saving ? 'Guardando…' : 'Guardar'"></span>
            </button>
            {{-- Save badge --}}
            <span x-show="savedFlash" x-transition.opacity
                class="text-[10px] text-emerald-400 flex items-center gap-1 shrink-0">
                <span class="material-symbols-outlined text-sm leading-none">check_circle</span> Guardado
            </span>
        </div>

        {{-- ── URL bar ── --}}
        <div class="px-4 py-3 border-b border-slate-700/50 flex items-center gap-2 shrink-0">
            <select x-model="method"
                :class="{
                    'text-emerald-400': method === 'GET',
                    'text-blue-400':    method === 'POST',
                    'text-amber-400':   method === 'PUT',
                    'text-red-400':     method === 'DELETE',
                    'text-purple-400':  method === 'PATCH',
                    'text-slate-400':   !['GET','POST','PUT','DELETE','PATCH'].includes(method),
                }"
                class="bg-slate-800 text-xs font-bold rounded-lg px-2 py-2 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer shrink-0 w-28">
                <option value="GET"     class="text-emerald-400">GET</option>
                <option value="POST"    class="text-blue-400">POST</option>
                <option value="PUT"     class="text-amber-400">PUT</option>
                <option value="DELETE"  class="text-red-400">DELETE</option>
                <option value="PATCH"   class="text-purple-400">PATCH</option>
                <option value="HEAD"    class="text-slate-400">HEAD</option>
                <option value="OPTIONS" class="text-slate-400">OPTIONS</option>
            </select>

            <input x-model="url" type="text"
                placeholder="https://api.example.com/endpoint"
                @keydown.enter="send()"
                class="flex-1 min-w-0 bg-slate-800/70 text-slate-200 rounded-lg px-3 py-2 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono text-xs placeholder-slate-600" />

            <button @click="send()" :disabled="loading || !url.trim()"
                class="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-xs font-bold rounded-lg transition shrink-0 min-w-[90px] justify-center">
                <template x-if="loading">
                    <span class="material-symbols-outlined text-sm leading-none animate-spin">progress_activity</span>
                </template>
                <template x-if="!loading">
                    <span class="material-symbols-outlined text-sm leading-none">send</span>
                </template>
                <span x-text="loading ? 'Enviando…' : 'Enviar'"></span>
            </button>
        </div>

        {{-- ── Tabs: Headers | Body ── --}}
        <div class="flex border-b border-slate-700/50 px-4 gap-0 shrink-0 bg-slate-900/40">
            <button @click="activeTab = 'headers'"
                :class="activeTab === 'headers'
                    ? 'text-white border-b-2 border-indigo-500'
                    : 'text-slate-500 hover:text-slate-300 border-b-2 border-transparent'"
                class="px-4 py-2.5 text-xs font-medium transition-colors">
                Cabeceras
                <span x-text="`(${headers.filter(h => h.key?.trim()).length})`"
                      class="ml-1 text-slate-600 text-[10px]"></span>
            </button>
            <button @click="hasBody && (activeTab = 'body')"
                :class="activeTab === 'body'
                    ? 'text-white border-b-2 border-indigo-500'
                    : (hasBody ? 'text-slate-500 hover:text-slate-300 border-b-2 border-transparent' : 'text-slate-700 cursor-not-allowed border-b-2 border-transparent')"
                class="px-4 py-2.5 text-xs font-medium transition-colors">
                Cuerpo
                <template x-if="!hasBody">
                    <span class="ml-1 text-[9px] text-slate-700">N/A</span>
                </template>
            </button>
        </div>

        {{-- ── Headers panel ── --}}
        <div x-show="activeTab === 'headers'"
             class="px-4 py-3 space-y-2 border-b border-slate-700/50 shrink-0 max-h-52 overflow-y-auto">
            <template x-for="(header, idx) in headers" :key="idx">
                <div class="flex gap-2 items-center">
                    <input x-model="header.key" type="text" placeholder="Nombre de cabecera"
                        class="w-40 shrink-0 bg-slate-800/70 text-slate-200 rounded-lg px-3 py-1.5 text-xs border border-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono placeholder-slate-600" />
                    <input x-model="header.value" type="text" placeholder="Valor"
                        class="flex-1 min-w-0 bg-slate-800/70 text-slate-200 rounded-lg px-3 py-1.5 text-xs border border-slate-700 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono placeholder-slate-600" />
                    <button @click="removeHeader(idx)"
                        class="p-1.5 text-slate-600 hover:text-red-400 rounded-lg hover:bg-slate-800 transition shrink-0">
                        <span class="material-symbols-outlined text-sm leading-none">remove</span>
                    </button>
                </div>
            </template>
            <button @click="addHeader()"
                class="flex items-center gap-1 text-xs text-indigo-400 hover:text-indigo-300 transition mt-1">
                <span class="material-symbols-outlined text-sm leading-none">add</span> Agregar Cabecera
            </button>
        </div>

        {{-- ── Body panel ── --}}
        <div x-show="activeTab === 'body'"
             class="border-b border-slate-700/50 shrink-0" style="height: 160px;">
            <textarea x-model="body" :disabled="!hasBody"
                placeholder='{ "key": "value" }'
                spellcheck="false"
                class="w-full h-full bg-transparent text-slate-200 font-mono text-xs p-4 resize-none focus:outline-none placeholder-slate-700 disabled:opacity-30 disabled:cursor-not-allowed"></textarea>
        </div>

        {{-- ══════════ RESPONSE PANEL ══════════ --}}
        <div class="flex-1 flex flex-col min-h-0 overflow-hidden">

            {{-- Empty state --}}
            <template x-if="!response && !error && !loading">
                <div class="flex-1 flex flex-col items-center justify-center gap-3 text-slate-700">
                    <span class="material-symbols-outlined text-5xl opacity-30">send</span>
                    <p class="text-sm">Configura una solicitud arriba y haz clic en <strong class="text-slate-500 font-semibold">Enviar</strong></p>
                </div>
            </template>

            {{-- Loading --}}
            <template x-if="loading">
                <div class="flex-1 flex flex-col items-center justify-center gap-3 text-slate-500">
                    <span class="material-symbols-outlined text-3xl animate-spin">progress_activity</span>
                    <p class="text-xs">Enviando solicitud…</p>
                </div>
            </template>

            {{-- Error / CORS block --}}
            <template x-if="error && !loading">
                <div class="flex-1 p-5 overflow-auto">
                    <div class="max-w-xl bg-red-950/40 border border-red-800/30 rounded-2xl p-5 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-red-400 text-xl">error</span>
                            <span class="text-red-400 font-semibold">Solicitud fallida</span>
                            <span class="ml-auto text-slate-600 text-xs" x-text="`${error.time} ms`"></span>
                        </div>
                        <pre class="text-red-300/80 text-xs font-mono bg-slate-900/60 rounded-xl px-4 py-2 whitespace-pre-wrap break-all"
                             x-text="error.message"></pre>
                        <template x-if="error.isCors">
                            <div class="bg-amber-950/30 border border-amber-700/30 rounded-xl p-4 text-xs text-amber-300 space-y-2">
                                <p class="font-semibold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base leading-none">shield_question</span>
                                    CORS / Network restriction detected
                                </p>
                                <p class="text-amber-400/80 leading-relaxed">
                                    The browser blocked this request because the target server does not
                                    include the required <code class="bg-amber-900/40 px-1 rounded">Access-Control-Allow-Origin</code> header,
                                    or there was a network error reaching the host.
                                </p>
                                <p class="text-amber-400/80 leading-relaxed">
                                    <strong>Testing a local service?</strong> Install a browser extension such as
                                    <em>CORS Unblock</em>, <em>Allow CORS: Access-Control-Allow-Origin</em>,
                                    or <em>Moesif Origin & CORS Changer</em> and enable it for this tab,
                                    then retry.
                                </p>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Success response --}}
            <template x-if="response && !loading">
                <div class="flex-1 flex flex-col min-h-0 overflow-hidden">

                    {{-- Status bar --}}
                    <div class="px-4 py-2 border-b border-slate-700/50 flex items-center gap-3 shrink-0 bg-slate-900/50">
                        <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold font-mono"
                              :class="statusColor(response.status)"
                              x-text="`${response.status} ${response.statusText}`"></span>
                        <span class="text-xs text-slate-500 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm leading-none">timer</span>
                            <span x-text="`${response.time} ms`"></span>
                        </span>
                        <span class="ml-auto text-[10px] font-mono text-slate-600"
                              x-text="response.isJson ? 'JSON' : 'TEXT'"></span>
                        <button @click="copyResponse()"
                            title="Copiar al portapapeles"
                            class="p-1 text-slate-600 hover:text-slate-300 transition">
                            <span class="material-symbols-outlined text-sm leading-none">content_copy</span>
                        </button>
                    </div>

                    {{-- Response body --}}
                    <div class="flex-1 overflow-auto">
                        <pre class="p-5 m-0 text-xs font-mono leading-relaxed whitespace-pre-wrap break-all"
                             x-html="colorize(response.body)"></pre>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
