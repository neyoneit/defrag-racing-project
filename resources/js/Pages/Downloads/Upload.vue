<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CategorySelect from '@/Components/Downloads/CategorySelect.vue';

import EnglishOnlyNotice from '@/Components/EnglishOnlyNotice.vue';
const props = defineProps({
    categories: Array,
    maxFileMb: Number,
    maxTotalMb: Number,
    allowedExtensions: Array,
});

const form = useForm({
    name: '',
    category_id: '',
    description: '',
    youtube_url: '',
    defrag_only: false,
    files: [],
    screenshots: [],
});

const fileInput = ref(null);
const shotInput = ref(null);

const pickFiles = (e) => { form.files = Array.from(e.target.files); };
const pickShots = (e) => { form.screenshots = Array.from(e.target.files); };

const dropFile = (i) => {
    form.files = form.files.filter((_, idx) => idx !== i);
    if (fileInput.value) fileInput.value.value = '';
};
const dropShot = (i) => {
    form.screenshots = form.screenshots.filter((_, idx) => idx !== i);
    if (shotInput.value) shotInput.value.value = '';
};

const formatSize = (bytes) => {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB';
    return bytes + ' B';
};

const totalBytes = computed(() =>
    [...form.files, ...form.screenshots].reduce((sum, f) => sum + f.size, 0)
);

// The whole POST has to fit under post_max_size, so warn before the server
// silently drops an oversized request rather than after.
const overTotal = computed(() => totalBytes.value > props.maxTotalMb * 1024 * 1024);

const accept = computed(() => props.allowedExtensions.map((e) => '.' + e).join(','));

// Laravel keys per-item failures as files.0, files.1, screenshots.2, ... -
// collect every key under its block so no error renders invisibly.
const errorsFor = (prefix) =>
    Object.entries(form.errors)
        .filter(([key]) => key === prefix || key.startsWith(prefix + '.'))
        .map(([, message]) => message);

const submit = () => {
    form.post('/downloads/upload', { forceFormData: true });
};
</script>

<template>
    <div>
        <Head :title="$t('Upload')" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-4xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center gap-2 text-xs text-gray-400 mb-2">
                    <Link href="/downloads" class="hover:text-cyan-400 transition-colors">{{ $t('Downloads') }}</Link>
                    <span class="text-gray-700">/</span>
                    <span class="text-gray-500">{{ $t('Upload') }}</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">{{ $t('Upload') }}</h1>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -22rem;">
            <form @submit.prevent="submit" class="space-y-3">

                <!-- Basics -->
                <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5 space-y-4">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-500 font-semibold mb-1.5">{{ $t('Name') }}</label>
                        <input
                            v-model="form.name"
                            type="text"
                            maxlength="150"
                            :placeholder="$t('What is it?')"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-cyan-500/50 focus:ring-0 transition-colors" />
                        <p v-if="form.errors.name" class="text-xs text-red-400 mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-500 font-semibold mb-1.5">{{ $t('Category') }}</label>
                        <CategorySelect
                            v-model="form.category_id"
                            :categories="categories"
                            :has-error="!!form.errors.category_id" />
                        <p v-if="form.errors.category_id" class="text-xs text-red-400 mt-1">{{ form.errors.category_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-500 font-semibold mb-1.5 [&_span]:text-gray-700 [&_span]:normal-case [&_span]:tracking-normal"
                               v-html="$t('Description <span>(optional)</span>')"></label>
                        <EnglishOnlyNotice />
                        <textarea
                            v-model="form.description"
                            rows="5"
                            maxlength="5000"
                            :placeholder="$t('What it does, how to install it, who made it...')"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-cyan-500/50 focus:ring-0 transition-colors resize-y"></textarea>
                        <p class="text-[11px] text-gray-600 mt-1">{{ form.description.length }} / 5000</p>
                        <p v-if="form.errors.description" class="text-xs text-red-400 mt-1">{{ form.errors.description }}</p>
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-500 font-semibold mb-1.5 [&_span]:text-gray-700 [&_span]:normal-case [&_span]:tracking-normal"
                               v-html="$t('YouTube URL <span>(optional)</span>')"></label>
                        <input
                            v-model="form.youtube_url"
                            type="url"
                            placeholder="https://www.youtube.com/watch?v=..."
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-cyan-500/50 focus:ring-0 transition-colors" />
                        <p class="text-[11px] text-gray-600 mt-1">{{ $t('Embeds a player on the detail page.') }}</p>
                        <p v-if="form.errors.youtube_url" class="text-xs text-red-400 mt-1">{{ form.errors.youtube_url }}</p>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="form.defrag_only" type="checkbox"
                               class="rounded bg-black/40 border-white/20 text-cyan-500 focus:ring-0 focus:ring-offset-0" />
                        <span class="text-sm text-gray-400">{{ $t('Only works with the DeFRaG mod') }}</span>
                    </label>
                </div>

                <!-- Files -->
                <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5 space-y-3">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-500 font-semibold mb-1.5">{{ $t('Files') }}</label>
                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            :accept="accept"
                            @change="pickFiles"
                            class="block w-full text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-cyan-500/30 file:text-xs file:font-semibold file:bg-cyan-500/15 file:text-cyan-300 hover:file:bg-cyan-500/25 file:cursor-pointer" />
                        <p class="text-[11px] text-gray-600 mt-1.5">
                            {{ $t('Up to 10 files, :max MB each, :total MB in total.', { max: maxFileMb, total: maxTotalMb }) }}
                            {{ $t('Allowed: :list', { list: allowedExtensions.join(', ') }) }}
                        </p>
                        <p v-for="(msg, i) in errorsFor('files')" :key="'fe' + i" class="text-xs text-red-400 mt-1">{{ msg }}</p>
                    </div>

                    <ul v-if="form.files.length" class="space-y-1">
                        <li v-for="(f, i) in form.files" :key="i"
                            class="flex items-center justify-between gap-3 bg-black/40 border border-white/5 rounded-lg px-3 py-2">
                            <span class="text-xs text-gray-300 truncate">{{ f.name }}</span>
                            <span class="flex items-center gap-2 flex-shrink-0">
                                <span class="text-[11px] text-gray-600">{{ formatSize(f.size) }}</span>
                                <button type="button" @click="dropFile(i)"
                                        class="text-gray-600 hover:text-red-400 transition-colors text-xs">{{ $t('remove') }}</button>
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Screenshots -->
                <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5 space-y-3">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-gray-500 font-semibold mb-1.5 [&_span]:text-gray-700 [&_span]:normal-case [&_span]:tracking-normal"
                               v-html="$t('Screenshots <span>(optional)</span>')"></label>
                        <input
                            ref="shotInput"
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.webp,.gif"
                            @change="pickShots"
                            class="block w-full text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border file:border-white/10 file:text-xs file:font-semibold file:bg-white/5 file:text-gray-400 hover:file:bg-white/10 file:cursor-pointer" />
                        <p class="text-[11px] text-gray-600 mt-1.5">{{ $t('Up to 6 images, 5 MB each.') }}</p>
                        <p v-for="(msg, i) in errorsFor('screenshots')" :key="'se' + i" class="text-xs text-red-400 mt-1">{{ msg }}</p>
                    </div>

                    <ul v-if="form.screenshots.length" class="space-y-1">
                        <li v-for="(f, i) in form.screenshots" :key="i"
                            class="flex items-center justify-between gap-3 bg-black/40 border border-white/5 rounded-lg px-3 py-2">
                            <span class="text-xs text-gray-300 truncate">{{ f.name }}</span>
                            <span class="flex items-center gap-2 flex-shrink-0">
                                <span class="text-[11px] text-gray-600">{{ formatSize(f.size) }}</span>
                                <button type="button" @click="dropShot(i)"
                                        class="text-gray-600 hover:text-red-400 transition-colors text-xs">{{ $t('remove') }}</button>
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Submit -->
                <div class="bg-black/45 backdrop-blur-xl rounded-xl border border-white/[0.08] p-5">
                    <div v-if="overTotal"
                         class="mb-3 bg-red-500/10 border border-red-500/30 rounded-lg px-3 py-2 text-xs text-red-300">
                        {{ $t('Total is :size, over the :limit MB limit. Remove something first.', { size: formatSize(totalBytes), limit: maxTotalMb }) }}
                    </div>

                    <div v-if="form.progress" class="mb-3">
                        <div class="h-1.5 bg-white/5 rounded-full overflow-hidden">
                            <div class="h-full bg-cyan-500 transition-all" :style="{ width: form.progress.percentage + '%' }"></div>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-1">{{ $t('Uploading :percent%', { percent: form.progress.percentage }) }}</p>
                    </div>

                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <p class="text-xs text-gray-600">
                            <span v-if="totalBytes">{{ $t(':size total', { size: formatSize(totalBytes) }) }}</span>
                        </p>
                        <div class="flex items-center gap-2">
                            <Link href="/downloads"
                                  class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-500 hover:text-white transition-colors">
                                {{ $t('Cancel') }}
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing || overTotal || !form.files.length"
                                class="px-5 py-2 rounded-lg bg-cyan-500/15 border border-cyan-500/30 text-sm font-bold text-cyan-300 hover:bg-cyan-500/25 transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                                {{ form.processing ? $t('Uploading...') : $t('Publish') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="h-4"></div>
    </div>
</template>
