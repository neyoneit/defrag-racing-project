<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    default_show_oldtop: user.value.default_show_oldtop || false,
    default_show_offline: user.value.default_show_offline || false,
});

const save = () => {
    form.post(route('settings.map-view-preferences'), {
        preserveScroll: true,
        onSuccess: () => setTimeout(() => window.location.reload(), 500),
    });
};
</script>

<template>
    <div class="rounded-xl bg-black/60 border border-white/10">
        <div class="p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-500/20 to-teal-600/20 border border-teal-500/30 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-teal-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-bold text-white">Map Records Default View</h2>
                </div>
                <div class="flex items-center gap-2">
                    <div v-if="form.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                        <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="text-xs font-medium text-green-400">Saved</span>
                    </div>
                    <button @click="save" :disabled="form.processing" class="px-3 py-1 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded transition-all disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>

            <p class="text-xs text-gray-500 mb-3">Choose which record sources are shown by default on map detail pages.</p>

            <div class="space-y-2">
                <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 cursor-not-allowed opacity-60">
                    <input type="checkbox" checked disabled class="w-4 h-4 rounded bg-white/10 border-white/20 text-blue-600" />
                    <div>
                        <p class="text-sm font-medium text-gray-300">Online Records</p>
                        <p class="text-xs text-gray-500">Always shown</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-colors cursor-pointer">
                    <input v-model="form.default_show_oldtop" type="checkbox" class="w-4 h-4 rounded bg-white/10 border-white/20 text-teal-600" />
                    <div>
                        <p class="text-sm font-medium text-gray-300">Oldtop Records</p>
                        <p class="text-xs text-gray-500">Historical records from q3df.org archive</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-colors cursor-pointer">
                    <input v-model="form.default_show_offline" type="checkbox" class="w-4 h-4 rounded bg-white/10 border-white/20 text-teal-600" />
                    <div>
                        <p class="text-sm font-medium text-gray-300">Demos Top</p>
                        <p class="text-xs text-gray-500">Records from uploaded demo files</p>
                    </div>
                </label>
            </div>
        </div>
    </div>
</template>
