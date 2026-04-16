import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import Sortable from 'sortablejs';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { createLowlight, all } from 'lowlight';

// Highlight.js theme (github-dark) — imported as CSS
import 'highlight.js/styles/github-dark.css';

const lowlight = createLowlight(all);

window.Pusher = Pusher;
window.Sortable = Sortable;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws'],
});

// TipTap Alpine component — rich text editor for Notes
//
// The editor instance is kept in a CLOSURE variable (_editor) that Alpine never
// proxies. Storing it as `this.editor` causes Alpine to wrap it in a reactive
// Proxy, and calling commands through that proxy triggers ProseMirror's
// "Applying a mismatched transaction" error because Alpine's Proxy intercepts
// the internal state reads that TipTap relies on.
document.addEventListener('alpine:init', () => {
    Alpine.data('tiptap', () => {
        // One closure-scoped editor per component instance — never reactive.
        let _editor = null;
        let _updating = false;

        return {
            active: {},
            autoSaveTimer: null,

            init() {
                const self = this;
                const initialContent = this.$wire.noteContent || '';

                _editor = new Editor({
                    element: this.$refs.editorContent,
                    extensions: [
                        StarterKit.configure({
                            heading: { levels: [1, 2, 3] },
                            codeBlock: false,   // disabled — replaced by CodeBlockLowlight
                        }),
                        Placeholder.configure({ placeholder: 'Start writing your note...' }),
                        CodeBlockLowlight.configure({ lowlight }),
                    ],
                    content: initialContent,
                    editorProps: {
                        attributes: { class: 'outline-none min-h-full' },
                    },
                    onUpdate({ editor }) {
                        if (_updating) return;
                        self.$wire.set('noteContent', editor.getHTML());
                        clearTimeout(self.autoSaveTimer);
                        self.autoSaveTimer = setTimeout(() => {
                            if (self.$wire.noteTitle?.trim()) {
                                self.$wire.saveNote();
                            }
                        }, 3000);
                    },
                    onCreate({ editor }) { self.syncActive(editor); },
                    onSelectionUpdate({ editor }) { self.syncActive(editor); },
                    onTransaction({ editor }) { self.syncActive(editor); },
                });

                // editorContentVersion increments when the note switches or a
                // collaborator saves the note you have open.
                this.$wire.$watch('editorContentVersion', () => {
                    clearTimeout(self.autoSaveTimer);
                    _updating = true;
                    _editor?.commands.setContent(self.$wire.noteContent || '', false);
                    _updating = false;
                    requestAnimationFrame(() => _editor?.commands.focus());
                });
            },

            syncActive(editor) {
                this.active = {
                    bold: editor.isActive('bold'),
                    italic: editor.isActive('italic'),
                    strike: editor.isActive('strike'),
                    code: editor.isActive('code'),
                    codeBlock: editor.isActive('codeBlock'),
                    h1: editor.isActive('heading', { level: 1 }),
                    h2: editor.isActive('heading', { level: 2 }),
                    h3: editor.isActive('heading', { level: 3 }),
                    bulletList: editor.isActive('bulletList'),
                    orderedList: editor.isActive('orderedList'),
                    blockquote: editor.isActive('blockquote'),
                };
            },

            // Toolbar buttons call @mousedown.prevent="run(c => c.toggleBold())"
            // _editor is the raw (unproxied) TipTap instance, so commands execute
            // cleanly against the true current ProseMirror state.
            run(fn) {
                if (!_editor) return;
                fn(_editor.commands);
            },

            destroy() {
                clearTimeout(this.autoSaveTimer);
                _editor?.destroy();
                _editor = null;
            },
        };
    });

    // ── API Tester ────────────────────────────────────────────────────────────
    Alpine.data('apiTester', () => {
        return {
            // ── request state
            method:    'GET',
            url:       '',
            name:      'Untitled Test',
            headers:   [{ key: '', value: '' }],
            body:      '',
            activeTab: 'headers',

            // ── ui state
            loading:    false,
            saving:     false,
            savedFlash: false,
            response:   null,
            error:      null,

            init() {
                this.hydrateFrom(this.$wire.loadedTest);

                this.$watch('$wire.loadedTest', val => {
                    if (val && Object.keys(val).length) {
                        this.hydrateFrom(val);
                        this.response = null;
                        this.error    = null;
                    }
                });

                this.$watch('$wire.activeTestId', val => {
                    if (val === null) this.resetForm();
                });

                this.$wire.$on('test-saved', () => {
                    this.saving     = false;
                    this.savedFlash = true;
                    setTimeout(() => (this.savedFlash = false), 2500);
                });
            },

            hydrateFrom(t) {
                if (!t || t.url === undefined) return;
                this.method  = t.method  || 'GET';
                this.url     = t.url     || '';
                this.name    = t.name    || 'Untitled Test';
                this.body    = t.body    || '';
                this.headers = Array.isArray(t.headers) && t.headers.length
                    ? t.headers
                    : [{ key: '', value: '' }];
            },

            resetForm() {
                this.method    = 'GET';
                this.url       = '';
                this.name      = 'Untitled Test';
                this.headers   = [{ key: '', value: '' }];
                this.body      = '';
                this.response  = null;
                this.error     = null;
            },

            get hasBody() {
                return !['GET', 'HEAD'].includes(this.method);
            },

            addHeader() {
                this.headers.push({ key: '', value: '' });
            },

            removeHeader(idx) {
                this.headers.splice(idx, 1);
                if (this.headers.length === 0) this.headers.push({ key: '', value: '' });
            },

            async send() {
                if (!this.url.trim()) return;
                this.loading  = true;
                this.response = null;
                this.error    = null;
                const start = performance.now();
                try {
                    const opts = { method: this.method, headers: {}, mode: 'cors', credentials: 'omit' };
                    for (const h of this.headers) {
                        if (h.key?.trim()) opts.headers[h.key.trim()] = h.value?.trim() ?? '';
                    }
                    if (this.hasBody && this.body.trim()) {
                        opts.body = this.body.trim();
                        if (!opts.headers['Content-Type'] && !opts.headers['content-type']) {
                            opts.headers['Content-Type'] = 'application/json';
                        }
                    }
                    const resp    = await fetch(this.url.trim(), opts);
                    const elapsed = Math.round(performance.now() - start);
                    const text    = await resp.text();
                    let pretty = text, isJson = false;
                    try { pretty = JSON.stringify(JSON.parse(text), null, 2); isJson = true; } catch (_) {}
                    this.response = { ok: resp.ok, status: resp.status, statusText: resp.statusText, time: elapsed, body: pretty, isJson };
                } catch (e) {
                    const elapsed = Math.round(performance.now() - start);
                    const isCors  = e instanceof TypeError &&
                        (e.message.toLowerCase().includes('fetch') ||
                         e.message.toLowerCase().includes('network') ||
                         e.message === 'Failed to fetch' ||
                         e.message === 'Load failed');
                    this.error = { message: e.message || 'Unknown error', isCors, time: elapsed };
                }
                this.loading = false;
            },

            async save() {
                if (this.saving) return;
                this.saving = true;
                const headers = this.headers.filter(h => h.key?.trim());
                await this.$wire.saveTest(this.name, this.method, this.url, headers, this.body);
            },

            async copyResponse() {
                if (!this.response?.body) return;
                try { await navigator.clipboard.writeText(this.response.body); } catch (_) {}
            },

            statusColor(code) {
                if (code >= 200 && code < 300) return 'text-emerald-400 bg-emerald-400/10 border border-emerald-700/30';
                if (code >= 300 && code < 400) return 'text-sky-400 bg-sky-400/10 border border-sky-700/30';
                if (code >= 400 && code < 500) return 'text-amber-400 bg-amber-400/10 border border-amber-700/30';
                return 'text-red-400 bg-red-400/10 border border-red-700/30';
            },

            colorize(text) {
                if (!text) return '';
                const escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                return escaped.replace(
                    /("(?:\\u[0-9a-fA-F]{4}|\\[^u]|[^\\"])*"\s*:?|\btrue\b|\bfalse\b|\bnull\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
                    m => {
                        const t = m.trimEnd();
                        if (/:$/.test(t))              return `<span style="color:#79c0ff">${m}</span>`;
                        if (/^"/. test(t))             return `<span style="color:#a5d6ff">${m}</span>`;
                        if (t === 'true' || t === 'false') return `<span style="color:#ff7b72">${m}</span>`;
                        if (t === 'null')              return `<span style="color:#8b949e">${m}</span>`;
                        return `<span style="color:#ffa657">${m}</span>`;
                    }
                );
            },
        };
    });
});

