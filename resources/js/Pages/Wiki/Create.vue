<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import WikiEditor from '@/Components/WikiEditor.vue';

const props = defineProps({
    parents: Array,
});

const form = useForm({
    title: '',
    slug: '',
    content: '',
    parent_id: null,
});

const autoSlug = ref(true);

watch(() => form.title, (val) => {
    if (autoSlug.value) {
        form.slug = val.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    }
});

const submit = () => {
    form.post(route('wiki.store'));
};
</script>

<template>
    <div class="pb-4">
        <Head title="Create Wiki Page" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <Link :href="route('wiki.index')" class="hover:text-gray-300 transition">Wiki</Link>
                    <span>/</span>
                    <span class="text-gray-400">New Page</span>
                </div>
                <h1 class="text-3xl md:text-4xl font-black text-gray-300/90">Create Wiki Page</h1>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <form @submit.prevent="submit" class="space-y-4">
                <div class="bg-gray-800/60 border border-gray-700/50 rounded-xl p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Title</label>
                            <input
                                v-model="form.title"
                                type="text"
                                class="w-full bg-gray-900/60 border border-gray-700/50 rounded-lg px-4 py-2 text-gray-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                            />
                            <p v-if="form.errors.title" class="text-red-400 text-sm mt-1">{{ form.errors.title }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">
                                Slug
                                <button type="button" @click="autoSlug = !autoSlug" class="ml-2 text-xs text-blue-400 hover:text-blue-300">
                                    {{ autoSlug ? '(auto - click to edit manually)' : '(manual - click for auto)' }}
                                </button>
                            </label>
                            <input
                                v-model="form.slug"
                                type="text"
                                :disabled="autoSlug"
                                class="w-full bg-gray-900/60 border border-gray-700/50 rounded-lg px-4 py-2 text-gray-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none disabled:opacity-50"
                            />
                            <p v-if="form.errors.slug" class="text-red-400 text-sm mt-1">{{ form.errors.slug }}</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Parent Page</label>
                        <select
                            v-model="form.parent_id"
                            class="w-full bg-gray-900/60 border border-gray-700/50 rounded-lg px-4 py-2 text-gray-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                        >
                            <option :value="null">None (top level)</option>
                            <option v-for="parent in parents" :key="parent.id" :value="parent.id">{{ parent.title }}</option>
                        </select>
                    </div>

                    <!-- WYSIWYG Editor -->
                    <WikiEditor v-model="form.content" />
                    <p v-if="form.errors.content" class="text-red-400 text-sm mt-1">{{ form.errors.content }}</p>

                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-700/50">
                        <Link :href="route('wiki.index')" class="text-gray-400 hover:text-gray-200 text-sm transition">Cancel</Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-medium rounded-lg transition"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Page' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
