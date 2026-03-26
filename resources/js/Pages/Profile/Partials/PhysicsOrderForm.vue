<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    default_physics_order: user.value.default_physics_order || 'vq3_first',
});

const save = () => {
    form.post(route('settings.physics-order'), {
        preserveScroll: true,
        onSuccess: () => setTimeout(() => window.location.reload(), 500),
    });
};
</script>

<template>
    <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
        <div class="p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500/20 to-purple-600/20 border border-blue-500/30 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-bold text-white">Physics Column Order</h2>
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

            <p class="text-xs text-gray-500 mb-3">Choose which physics mode appears on the left side across all pages.</p>

            <div class="space-y-2">
                <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-colors cursor-pointer" :class="form.default_physics_order === 'vq3_first' ? 'border-blue-500/30 bg-blue-500/10' : ''">
                    <input v-model="form.default_physics_order" type="radio" value="vq3_first" class="w-4 h-4 bg-white/10 border-white/20 text-blue-600" />
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black text-blue-400 uppercase bg-blue-400/20 border border-blue-400/30 px-2 py-0.5 rounded">VQ3</span>
                        <span class="text-xs text-gray-500">/</span>
                        <span class="text-xs font-black text-purple-400 uppercase bg-purple-400/20 border border-purple-400/30 px-2 py-0.5 rounded">CPM</span>
                        <span class="text-xs text-gray-500 ml-1">(default)</span>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-colors cursor-pointer" :class="form.default_physics_order === 'cpm_first' ? 'border-purple-500/30 bg-purple-500/10' : ''">
                    <input v-model="form.default_physics_order" type="radio" value="cpm_first" class="w-4 h-4 bg-white/10 border-white/20 text-purple-600" />
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black text-purple-400 uppercase bg-purple-400/20 border border-purple-400/30 px-2 py-0.5 rounded">CPM</span>
                        <span class="text-xs text-gray-500">/</span>
                        <span class="text-xs font-black text-blue-400 uppercase bg-blue-400/20 border border-blue-400/30 px-2 py-0.5 rounded">VQ3</span>
                    </div>
                </label>
            </div>
        </div>
    </div>
</template>
