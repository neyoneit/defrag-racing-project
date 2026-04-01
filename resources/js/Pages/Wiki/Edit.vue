<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import WikiEditor from '@/Components/WikiEditor.vue';

const props = defineProps({
    page: Object,
    parents: Array,
    isStaff: Boolean,
});

const form = useForm({
    title: props.page.title,
    content: props.page.content,
    keywords: props.page.keywords || '',
    summary: '',
    parent_id: props.page.parent_id,
    is_locked: props.page.is_locked,
});

const noChangesMessage = ref(false);

const originalData = {
    title: props.page.title,
    content: props.page.content,
    keywords: props.page.keywords || '',
    parent_id: props.page.parent_id,
    is_locked: props.page.is_locked,
};

const submit = () => {
    const hasChanges = form.title !== originalData.title
        || form.content !== originalData.content
        || form.keywords !== originalData.keywords
        || form.parent_id !== originalData.parent_id
        || form.is_locked !== originalData.is_locked;

    if (!hasChanges) {
        noChangesMessage.value = true;
        setTimeout(() => noChangesMessage.value = false, 3000);
        return;
    }

    noChangesMessage.value = false;
    form.put(route('wiki.update', props.page.slug));
};
</script>

<template>
    <div class="pb-4">
        <Head :title="'Edit: ' + page.title + ' - Wiki'" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <Link :href="route('wiki.index')" class="hover:text-gray-300 transition">Wiki</Link>
                    <span>/</span>
                    <Link :href="route('wiki.show', page.slug)" class="hover:text-gray-300 transition">{{ page.title }}</Link>
                    <span>/</span>
                    <span class="text-gray-400">Edit</span>
                </div>
                <div class="flex items-center justify-between">
                    <h1 class="text-3xl md:text-4xl font-black text-gray-300/90">Edit: {{ page.title }}</h1>
                    <div class="flex items-center gap-2">
                        <Link :href="route('wiki.show', page.slug)" class="px-4 py-2 bg-gray-700/60 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition">Cancel</Link>
                        <span v-show="noChangesMessage" class="text-sm text-yellow-400">No changes to save.</span>
                        <button
                            type="button"
                            @click="submit"
                            :disabled="form.processing"
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <form @submit.prevent="submit" class="space-y-4">
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-6">
                    <!-- Auto-generated page warning (staff only) -->
                    <div v-if="page.slug === 'defrag-releases'" class="mb-4 bg-red-900/30 border border-red-700/50 rounded-lg px-4 py-3 text-sm text-red-300">
                        <strong class="text-red-200">Auto-generated page</strong> - The release table is automatically updated every Sunday by <code class="bg-red-900/40 px-1.5 py-0.5 rounded text-red-200 text-xs">wiki:check-defrag-releases</code>. Any edits to the release table will be overwritten on next update. You may safely edit the Installation section and notes below the tables.
                    </div>

                    <!-- Title -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            class="w-full bg-gray-900/60 border border-gray-700/50 rounded-lg px-4 py-2 text-gray-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                        />
                        <p v-if="form.errors.title" class="text-red-400 text-sm mt-1">{{ form.errors.title }}</p>
                    </div>

                    <!-- Staff options -->
                    <div v-if="isStaff" class="flex gap-4 mb-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-400 mb-1">Parent Page</label>
                            <select
                                v-model="form.parent_id"
                                class="w-full bg-gray-900/60 border border-gray-700/50 rounded-lg px-4 py-2 text-gray-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            >
                                <option :value="null">None (top level)</option>
                                <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.title }}</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 px-4 py-2 cursor-pointer">
                                <input type="checkbox" v-model="form.is_locked" class="rounded bg-gray-900 border-gray-700 text-blue-500 focus:ring-blue-500" />
                                <span class="text-sm text-gray-400">Locked</span>
                            </label>
                        </div>
                    </div>

                    <!-- Keywords (staff only) -->
                    <div v-if="isStaff" class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Search Keywords</label>
                        <input
                            v-model="form.keywords"
                            type="text"
                            class="w-full bg-gray-900/60 border border-gray-700/50 rounded-lg px-4 py-2 text-gray-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-sm"
                            placeholder="Comma-separated aliases, e.g. GB, ground boost, groundboost"
                        />
                        <p class="text-xs text-gray-600 mt-1">Aliases and abbreviations that help users find this page via search</p>
                    </div>

                    <!-- WYSIWYG Editor -->
                    <div class="flex items-center gap-2 mb-2 px-3 py-2 bg-blue-900/20 border border-blue-800/30 rounded-lg">
                        <svg class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-sm text-blue-300/80">You can paste images directly with <kbd class="px-1.5 py-0.5 text-xs bg-blue-900/40 border border-blue-700/50 rounded">Ctrl+V</kbd> or drag & drop them into the editor.</p>
                    </div>
                    <WikiEditor v-model="form.content" />
                    <p v-if="form.errors.content" class="text-red-400 text-sm mt-1">{{ form.errors.content }}</p>

                    <!-- Edit summary -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Edit summary (optional)</label>
                        <input
                            v-model="form.summary"
                            type="text"
                            class="w-full bg-gray-900/60 border border-gray-700/50 rounded-lg px-4 py-2 text-gray-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none text-sm"
                            placeholder="Briefly describe your changes..."
                        />
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-700/50">
                        <Link :href="route('wiki.show', page.slug)" class="px-4 py-2 bg-gray-700/60 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition">Cancel</Link>
                        <div class="flex items-center gap-3">
                            <span v-show="noChangesMessage" class="text-sm text-yellow-400">No changes to save.</span>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-medium rounded-lg transition"
                            >
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
