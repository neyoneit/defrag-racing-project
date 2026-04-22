<script setup>
import { ref, watch, nextTick, onBeforeUnmount, computed } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import axios from 'axios';
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
    placeholder: { type: String, default: 'Start writing...' },
    minHeight: { type: String, default: '15rem' },
    // Feature toggles — default matches full WikiEditor-like toolbar
    enableImage: { type: Boolean, default: true },
    enableTable: { type: Boolean, default: true },
    enableHeadings: { type: Boolean, default: true },
    enableCode: { type: Boolean, default: true },
    enableSource: { type: Boolean, default: false },
    // Route name for image upload (Laravel named route)
    imageUploadRoute: { type: String, default: 'wiki.uploadImage' },
});

const emit = defineEmits(['update:modelValue']);

const sourceMode = ref(false);
const sourceCode = ref('');
const imageUploading = ref(false);

async function uploadAndInsert(file) {
    imageUploading.value = true;
    try {
        const formData = new FormData();
        formData.append('image', file);
        const { data } = await axios.post(route(props.imageUploadRoute), formData);
        editor.value.chain().focus().setImage({ src: data.url }).run();
    } catch (err) {
        alert('Upload failed: ' + (err.response?.data?.message || err.message));
    }
    imageUploading.value = false;
}

const extensions = computed(() => {
    const exts = [
        StarterKit.configure({ link: false, underline: false }),
        Link.configure({ openOnClick: false, HTMLAttributes: { class: 'text-blue-400 underline' } }),
        Underline,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ];
    if (props.enableTable) {
        exts.push(Table.configure({ resizable: true }), TableRow, TableCell, TableHeader);
    }
    if (props.enableImage) {
        exts.push(Image.configure({ inline: false, allowBase64: false }));
    }
    return exts;
});

const editor = useEditor({
    content: props.modelValue,
    extensions: extensions.value,
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
    editorProps: {
        handlePaste(view, event) {
            if (!props.enableImage) return false;
            const items = event.clipboardData?.items;
            if (!items) return false;
            for (const item of items) {
                if (item.type.startsWith('image/')) {
                    event.preventDefault();
                    const file = item.getAsFile();
                    if (file) uploadAndInsert(file);
                    return true;
                }
            }
            return false;
        },
        handleDrop(view, event) {
            if (!props.enableImage) return false;
            const files = event.dataTransfer?.files;
            if (!files?.length) return false;
            for (const file of files) {
                if (file.type.startsWith('image/')) {
                    event.preventDefault();
                    uploadAndInsert(file);
                    return true;
                }
            }
            return false;
        },
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

const showLinkDialog = ref(false);
const linkUrl = ref('');
const linkDialogRef = ref(null);

watch(showLinkDialog, async (open) => {
    if (open) {
        const attrs = editor.value?.getAttributes('link');
        linkUrl.value = attrs?.href || '';
        await nextTick();
        linkDialogRef.value?.querySelector('input')?.focus();
    }
});

const insertLink = () => {
    if (linkUrl.value) {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: linkUrl.value }).run();
    } else {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    }
    linkUrl.value = '';
    showLinkDialog.value = false;
};

const removeLink = () => {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    linkUrl.value = '';
    showLinkDialog.value = false;
};

const imageInputRef = ref(null);
const showImageDialog = ref(false);
const imageDialogRef = ref(null);
const imageUrl = ref('');

watch(showImageDialog, async (open) => {
    if (open) {
        await nextTick();
        imageDialogRef.value?.focus();
    }
});

const insertImageFromUrl = async () => {
    if (!imageUrl.value) return;
    imageUploading.value = true;
    try {
        const { data } = await axios.post(route(props.imageUploadRoute), { url: imageUrl.value });
        editor.value.chain().focus().setImage({ src: data.url }).run();
        imageUrl.value = '';
        showImageDialog.value = false;
    } catch (err) {
        alert('Failed to download image: ' + (err.response?.data?.error || err.message));
    }
    imageUploading.value = false;
};

const triggerFileUpload = () => imageInputRef.value?.click();

const handleImageUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    await uploadAndInsert(file);
    showImageDialog.value = false;
    e.target.value = '';
};

const addTable = () => {
    editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
};

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div class="rte-wrapper">
        <div class="flex flex-wrap items-center gap-0.5 bg-gray-900/40 border border-gray-700/50 rounded-t-lg px-2 py-1.5">
            <template v-if="!sourceMode">
                <button type="button" @click="editor?.chain().focus().toggleBold().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('bold') }" class="toolbar-btn" title="Bold"><strong>B</strong></button>
                <button type="button" @click="editor?.chain().focus().toggleItalic().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('italic') }" class="toolbar-btn italic" title="Italic">I</button>
                <button type="button" @click="editor?.chain().focus().toggleUnderline().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('underline') }" class="toolbar-btn underline" title="Underline">U</button>
                <button type="button" @click="editor?.chain().focus().toggleStrike().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('strike') }" class="toolbar-btn line-through" title="Strikethrough">S</button>

                <template v-if="enableHeadings">
                    <div class="w-px h-5 bg-gray-700 mx-1"></div>
                    <button type="button" @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('heading', { level: 2 }) }" class="toolbar-btn" title="Heading 2">H2</button>
                    <button type="button" @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('heading', { level: 3 }) }" class="toolbar-btn" title="Heading 3">H3</button>
                    <button type="button" @click="editor?.chain().focus().toggleHeading({ level: 4 }).run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('heading', { level: 4 }) }" class="toolbar-btn" title="Heading 4">H4</button>
                </template>

                <div class="w-px h-5 bg-gray-700 mx-1"></div>
                <button type="button" @click="editor?.chain().focus().toggleBulletList().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('bulletList') }" class="toolbar-btn" title="Bullet List">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <button type="button" @click="editor?.chain().focus().toggleOrderedList().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('orderedList') }" class="toolbar-btn" title="Ordered List">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                </button>

                <div class="w-px h-5 bg-gray-700 mx-1"></div>
                <button type="button" @click="editor?.chain().focus().toggleBlockquote().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('blockquote') }" class="toolbar-btn" title="Quote">"</button>

                <template v-if="enableCode">
                    <button type="button" @click="editor?.chain().focus().toggleCode().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('code') }" class="toolbar-btn font-mono" title="Inline Code">&lt;&gt;</button>
                    <button type="button" @click="editor?.chain().focus().toggleCodeBlock().run()" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('codeBlock') }" class="toolbar-btn font-mono text-xs" title="Code Block">{ }</button>
                </template>
                <button type="button" @click="editor?.chain().focus().setHorizontalRule().run()" class="toolbar-btn" title="Horizontal Rule">--</button>

                <div class="w-px h-5 bg-gray-700 mx-1"></div>
                <button type="button" @click="showLinkDialog = true" :class="{ 'bg-blue-600/30 text-blue-400': editor?.isActive('link') }" class="toolbar-btn" title="Link">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </button>
                <button v-if="enableImage" type="button" @click="showImageDialog = true" class="toolbar-btn" title="Image">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </button>
                <button v-if="enableTable" type="button" @click="addTable" class="toolbar-btn" title="Table">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 3v18M14 3v18"/></svg>
                </button>

                <div class="w-px h-5 bg-gray-700 mx-1"></div>
                <button type="button" @click="editor?.chain().focus().undo().run()" class="toolbar-btn" title="Undo">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l4-4m-4 4l4 4"/></svg>
                </button>
                <button type="button" @click="editor?.chain().focus().redo().run()" class="toolbar-btn" title="Redo">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a5 5 0 00-5 5v2m15-7l-4-4m4 4l-4 4"/></svg>
                </button>
            </template>

            <div class="flex-1"></div>

            <button v-if="enableSource" type="button" @click="toggleSource" class="px-3 py-1 text-xs rounded transition" :class="sourceMode ? 'bg-orange-600/30 text-orange-400 font-bold' : 'text-gray-400 hover:text-white hover:bg-gray-700/50'">
                {{ sourceMode ? 'Visual Editor' : 'Source Code' }}
            </button>
        </div>

        <div v-if="!sourceMode" class="bg-gray-900/60 border border-t-0 border-gray-700/50 rounded-b-lg">
            <editor-content :editor="editor" class="rte-content" :style="{ '--rte-min-height': minHeight }" />
        </div>
        <textarea v-else :value="sourceCode" @input="updateSource" rows="20" class="w-full bg-gray-900/60 border border-t-0 border-gray-700/50 rounded-b-lg px-4 py-3 text-gray-200 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none resize-y"></textarea>

        <Teleport to="body">
            <div v-if="showLinkDialog" ref="linkDialogRef" class="fixed inset-0 z-[9999] flex items-center justify-center px-4" @click.self="showLinkDialog = false" @keydown.esc="showLinkDialog = false" tabindex="-1">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showLinkDialog = false"></div>
                <div class="relative bg-gray-900 border border-white/15 rounded-xl shadow-2xl p-6 w-full max-w-md">
                    <h3 class="text-lg font-bold text-gray-200 mb-4">Insert Link</h3>
                    <div class="space-y-3">
                        <input v-model="linkUrl" type="text" placeholder="https://example.com" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-sm text-gray-200 focus:border-blue-500 outline-none" @keydown.enter="insertLink" />
                        <div class="flex items-center gap-2">
                            <button type="button" @click="insertLink" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">{{ linkUrl ? 'Apply Link' : 'Remove Link' }}</button>
                            <button v-if="editor?.isActive('link')" type="button" @click="removeLink" class="px-4 py-2 bg-red-600/60 hover:bg-red-600 text-white text-sm font-medium rounded-lg transition">Remove</button>
                            <button type="button" @click="showLinkDialog = false" class="px-4 py-2 bg-gray-700/60 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <input ref="imageInputRef" type="file" accept="image/*" class="hidden" @change="handleImageUpload" />

        <Teleport to="body">
            <div v-if="showImageDialog" class="fixed inset-0 z-[9999] flex items-center justify-center px-4" @click.self="showImageDialog = false" @keydown.esc="showImageDialog = false" tabindex="-1" ref="imageDialogRef">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showImageDialog = false"></div>
                <div class="relative bg-gray-900 border border-white/15 rounded-xl shadow-2xl p-6 w-full max-w-md">
                    <h3 class="text-lg font-bold text-gray-200 mb-4">Insert Image</h3>
                    <button type="button" @click="triggerFileUpload" :disabled="imageUploading" class="w-full mb-4 px-4 py-8 border-2 border-dashed border-gray-600 hover:border-blue-500 rounded-lg text-gray-400 hover:text-blue-400 transition flex flex-col items-center gap-2">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span v-if="imageUploading" class="text-sm">Uploading...</span>
                        <span v-else class="text-sm">Click to upload an image</span>
                        <span class="text-xs text-gray-600">Max 5MB - JPG, PNG, GIF, WebP</span>
                    </button>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex-1 h-px bg-gray-700"></div>
                        <span class="text-xs text-gray-600">or paste URL</span>
                        <div class="flex-1 h-px bg-gray-700"></div>
                    </div>
                    <div class="flex gap-2">
                        <input v-model="imageUrl" type="text" placeholder="https://example.com/image.png" class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 focus:border-blue-500 outline-none" @keydown.enter="insertImageFromUrl" />
                        <button type="button" @click="insertImageFromUrl" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">Insert</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style>
.rte-wrapper .toolbar-btn {
    @apply px-2 py-1 text-sm text-gray-400 hover:text-white hover:bg-gray-700/50 rounded transition;
}
.rte-content .tiptap {
    @apply px-6 py-4 outline-none text-gray-300;
    min-height: var(--rte-min-height, 15rem);
}
.rte-content .tiptap:focus { @apply outline-none; }
.rte-content .tiptap h1 { @apply text-3xl font-bold text-gray-200 mt-6 mb-3; }
.rte-content .tiptap h2 { @apply text-2xl font-bold text-gray-200 mt-6 mb-3; }
.rte-content .tiptap h3 { @apply text-xl font-semibold text-gray-300 mt-4 mb-2; }
.rte-content .tiptap h4 { @apply text-lg font-semibold text-gray-300 mt-3 mb-2; }
.rte-content .tiptap p { @apply text-gray-400 leading-relaxed mb-3; }
.rte-content .tiptap ul { @apply list-disc ml-6 text-gray-400 mb-3 space-y-1; }
.rte-content .tiptap ol { @apply list-decimal ml-6 text-gray-400 mb-3 space-y-1; }
.rte-content .tiptap li { @apply text-gray-400; }
.rte-content .tiptap a { @apply text-blue-400 hover:text-blue-300 underline cursor-pointer; }
.rte-content .tiptap code { @apply bg-gray-800 text-green-400 px-1.5 py-0.5 rounded text-sm; }
.rte-content .tiptap pre { @apply bg-gray-800/80 border border-gray-700/50 rounded-lg p-4 mb-3 overflow-x-auto; }
.rte-content .tiptap pre code { @apply bg-transparent p-0 text-gray-300 text-sm; }
.rte-content .tiptap blockquote { @apply border-l-4 border-blue-500/50 pl-4 italic text-gray-500 mb-3; }
.rte-content .tiptap hr { @apply border-gray-700/50 my-4; }
.rte-content .tiptap strong { @apply text-gray-200 font-semibold; }
.rte-content .tiptap em { @apply text-gray-300; }
.rte-content .tiptap img { @apply rounded-lg max-w-full my-3 cursor-pointer; }
.rte-content .tiptap img.ProseMirror-selectednode { outline: 3px solid rgb(59, 130, 246); outline-offset: 2px; }
.rte-content .tiptap table { @apply w-full border-collapse mb-3; }
.rte-content .tiptap th { @apply bg-gray-700/40 border border-gray-700/50 px-3 py-2 text-left text-sm font-semibold text-gray-300; }
.rte-content .tiptap td { @apply border border-gray-700/50 px-3 py-2 text-sm text-gray-400; }
.rte-content .tiptap .selectedCell { @apply bg-blue-600/20; }
.rte-content .tiptap p.is-editor-empty:first-child::before {
    @apply text-gray-600;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
