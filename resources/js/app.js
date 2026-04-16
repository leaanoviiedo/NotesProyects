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
});

