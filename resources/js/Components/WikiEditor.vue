<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { Image } from '@tiptap/extension-image';
import { Underline } from '@tiptap/extension-underline';
import { TextAlign } from '@tiptap/extension-text-align';
import Placeholder from '@tiptap/extension-placeholder';

const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const sourceMode = ref(false);
const sourceCode = ref('');

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Link.configure({ openOnClick: false, HTMLAttributes: { class: 'text-blue-400 underline' } }),
        Table.configure({ resizable: true }),
        TableRow,
        TableCell,
        TableHeader,
        Image,
        Underline,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Placeholder.configure({ placeholder: 'Start writing...' }),
    ],
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val, false);
    }
});

const toggleSource = () => {
    if (!sourceMode.value) {
        sourceCode.value = editor.value.getHTML();
        sourceMode.value = true;
    } else {
        editor.value.commands.setContent(sourceCode.value, false);
        emit('update:modelValue', sourceCode.value);
        sourceMode.value = false;
    }
};

const updateSource = (e) => {
    sourceCode.value = e.target.value;
    emit('update:modelValue', sourceCode.value);
};

const addLink = () => {
    const url = window.prompt('URL:');
    if (url) {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
    }
};

const addImage = () => {
    const url = window.prompt('Image URL:');
    if (url) {
        editor.value.chain().focus().setImage({ src: url }).run();
    }
};

const addTable = () => {
    editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
};

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div class="wiki-editor-wrapper">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-0.5 bg-gray-900/40 border border-gray-700/50 rounded-t-lg px-2 py-1.5">
            <template v-if="!sourceMode">
                <!-- Text formatting -->
                <button type="button" @click="editor?.chain().focus().toggleBold().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('bold') }" class="toolbar-btn" title="Bold"><strong>B</strong></button>
                <button type="button" @click="editor?.chain().focus().toggleItalic().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('italic') }" class="toolbar-btn italic" title="Italic">I</button>
                <button type="button" @click="editor?.chain().focus().toggleUnderline().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('underline') }" class="toolbar-btn underline" title="Underline">U</button>
                <button type="button" @click="editor?.chain().focus().toggleStrike().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('strike') }" class="toolbar-btn line-through" title="Strikethrough">S</button>
                <div class="w-px h-5 bg-gray-700 mx-1"></div>

                <!-- Headings -->
                <button type="button" @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('heading', { level: 2 }) }" class="toolbar-btn" title="Heading 2">H2</button>
                <button type="button" @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('heading', { level: 3 }) }" class="toolbar-btn" title="Heading 3">H3</button>
                <button type="button" @click="editor?.chain().focus().toggleHeading({ level: 4 }).run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('heading', { level: 4 }) }" class="toolbar-btn" title="Heading 4">H4</button>
                <div class="w-px h-5 bg-gray-700 mx-1"></div>

                <!-- Lists -->
                <button type="button" @click="editor?.chain().focus().toggleBulletList().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('bulletList') }" class="toolbar-btn" title="Bullet List">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <button type="button" @click="editor?.chain().focus().toggleOrderedList().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('orderedList') }" class="toolbar-btn" title="Ordered List">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </button>
                <div class="w-px h-5 bg-gray-700 mx-1"></div>

                <!-- Block elements -->
                <button type="button" @click="editor?.chain().focus().toggleBlockquote().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('blockquote') }" class="toolbar-btn" title="Quote">"</button>
                <button type="button" @click="editor?.chain().focus().toggleCode().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('code') }" class="toolbar-btn font-mono" title="Inline Code">&lt;&gt;</button>
                <button type="button" @click="editor?.chain().focus().toggleCodeBlock().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('codeBlock') }" class="toolbar-btn font-mono text-xs" title="Code Block">{ }</button>
                <button type="button" @click="editor?.chain().focus().setHorizontalRule().run()" class="toolbar-btn" title="Horizontal Rule">--</button>
                <div class="w-px h-5 bg-gray-700 mx-1"></div>

                <!-- Insert -->
                <button type="button" @click="addLink" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('link') }" class="toolbar-btn" title="Link">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </button>
                <button type="button" @click="addImage" class="toolbar-btn" title="Image">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </button>
                <button type="button" @click="addTable" class="toolbar-btn" title="Table">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 3v18M14 3v18"/></svg>
                </button>
                <div class="w-px h-5 bg-gray-700 mx-1"></div>

                <!-- Undo/Redo -->
                <button type="button" @click="editor?.chain().focus().undo().run()" class="toolbar-btn" title="Undo">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l4-4m-4 4l4 4"/></svg>
                </button>
                <button type="button" @click="editor?.chain().focus().redo().run()" class="toolbar-btn" title="Redo">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a5 5 0 00-5 5v2m15-7l-4-4m4 4l-4 4"/></svg>
                </button>
            </template>

            <div class="flex-1"></div>

            <!-- Source toggle -->
            <button type="button" @click="toggleSource" class="px-3 py-1 text-xs rounded transition" :class="sourceMode ? 'bg-orange-600/30 text-orange-400 font-bold' : 'text-gray-400 hover:text-white hover:bg-gray-700/50'">
                {{ sourceMode ? 'Visual Editor' : 'Source Code' }}
            </button>
        </div>

        <!-- Editor -->
        <div v-if="!sourceMode" class="bg-gray-900/60 border border-t-0 border-gray-700/50 rounded-b-lg">
            <editor-content :editor="editor" class="wiki-editor-content" />
        </div>

        <!-- Source code editor -->
        <textarea
            v-else
            :value="sourceCode"
            @input="updateSource"
            rows="30"
            class="w-full bg-gray-900/60 border border-t-0 border-gray-700/50 rounded-b-lg px-4 py-3 text-gray-200 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none resize-y"
        ></textarea>
    </div>
</template>

<style>
.toolbar-btn {
    @apply px-2 py-1 text-sm text-gray-400 hover:text-white hover:bg-gray-700/50 rounded transition;
}

.wiki-editor-content .tiptap {
    @apply px-6 py-4 min-h-[30rem] outline-none text-gray-300;
}

.wiki-editor-content .tiptap:focus {
    @apply outline-none;
}

/* Content styles inside editor */
.wiki-editor-content .tiptap h1 { @apply text-3xl font-bold text-gray-200 mt-6 mb-3; }
.wiki-editor-content .tiptap h2 { @apply text-2xl font-bold text-gray-200 mt-6 mb-3; }
.wiki-editor-content .tiptap h3 { @apply text-xl font-semibold text-gray-300 mt-4 mb-2; }
.wiki-editor-content .tiptap h4 { @apply text-lg font-semibold text-gray-300 mt-3 mb-2; }
.wiki-editor-content .tiptap p { @apply text-gray-400 leading-relaxed mb-3; }
.wiki-editor-content .tiptap ul { @apply list-disc ml-6 text-gray-400 mb-3 space-y-1; }
.wiki-editor-content .tiptap ol { @apply list-decimal ml-6 text-gray-400 mb-3 space-y-1; }
.wiki-editor-content .tiptap li { @apply text-gray-400; }
.wiki-editor-content .tiptap a { @apply text-blue-400 hover:text-blue-300 underline cursor-pointer; }
.wiki-editor-content .tiptap code { @apply bg-gray-800 text-green-400 px-1.5 py-0.5 rounded text-sm; }
.wiki-editor-content .tiptap pre { @apply bg-gray-800/80 border border-gray-700/50 rounded-lg p-4 mb-3 overflow-x-auto; }
.wiki-editor-content .tiptap pre code { @apply bg-transparent p-0 text-gray-300 text-sm; }
.wiki-editor-content .tiptap blockquote { @apply border-l-4 border-blue-500/50 pl-4 italic text-gray-500 mb-3; }
.wiki-editor-content .tiptap hr { @apply border-gray-700/50 my-4; }
.wiki-editor-content .tiptap strong { @apply text-gray-200 font-semibold; }
.wiki-editor-content .tiptap em { @apply text-gray-300; }
.wiki-editor-content .tiptap img { @apply rounded-lg max-w-full my-3; }

/* Table styles */
.wiki-editor-content .tiptap table { @apply w-full border-collapse mb-3; }
.wiki-editor-content .tiptap th { @apply bg-gray-700/40 border border-gray-700/50 px-3 py-2 text-left text-sm font-semibold text-gray-300; }
.wiki-editor-content .tiptap td { @apply border border-gray-700/50 px-3 py-2 text-sm text-gray-400; }
.wiki-editor-content .tiptap .selectedCell { @apply bg-blue-600/20; }

/* Placeholder */
.wiki-editor-content .tiptap p.is-editor-empty:first-child::before {
    @apply text-gray-600;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
