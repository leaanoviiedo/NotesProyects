import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import Sortable from 'sortablejs';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';

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
document.addEventListener('alpine:init', () => {
    Alpine.data('tiptap', () => ({
        editor: null,
        updatingFromWire: false,
        active: {},

        init() {
            const self = this;
            this.editor = new Editor({
                element: this.$refs.editorContent,
                extensions: [
                    StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
                    Placeholder.configure({ placeholder: 'Start writing your note...' }),
                ],
                editorProps: {
                    attributes: { class: 'outline-none min-h-full' },
                },
                onUpdate({ editor }) {
                    if (!self.updatingFromWire) {
                        self.$wire.set('noteContent', editor.getHTML());
                    }
                },
                onCreate({ editor }) { self.syncActive(editor); },
                onSelectionUpdate({ editor }) { self.syncActive(editor); },
                onTransaction({ editor }) { self.syncActive(editor); },
            });

            // Update editor when switching notes
            this.$wire.$watch('activeNoteId', () => {
                requestAnimationFrame(() => {
                    self.updatingFromWire = true;
                    self.editor?.commands.setContent(self.$wire.noteContent || '', false);
                    self.updatingFromWire = false;
                });
            });

            // Clear + focus editor when opening a new (blank) note
            this.$wire.$watch('isEditing', (val) => {
                if (val && !this.$wire.activeNoteId) {
                    requestAnimationFrame(() => {
                        self.updatingFromWire = true;
                        self.editor?.commands.setContent('', false);
                        self.editor?.commands.focus();
                        self.updatingFromWire = false;
                    });
                }
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

        run(fn) { fn(this.editor.chain().focus()); },
        destroy() { this.editor?.destroy(); },
    }));
});

