<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import InputLabel from '@/Components/Laravel/InputLabel.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    _method: 'POST',
    color: user.value.color || '#1F2937',
    avatar_effect: user.value.avatar_effect || 'none',
    name_effect: user.value.name_effect || 'none',
    avatar_border_color: user.value.avatar_border_color || '#6b7280'
});

const save = () => {
    form.post(route('settings.preferences'), {
        preserveScroll: true,
        onSuccess: () => setTimeout(() => window.location.reload(), 500),
    });
};

const avatarEffects = [
    { id: 'none', name: 'None', icon: '⭕' },
    { id: 'glow', name: 'Glow', icon: '✨' },
    { id: 'pulse', name: 'Pulse', icon: '💓' },
    { id: 'spin', name: 'Spin', icon: '🌀' },
    { id: 'bounce', name: 'Bounce', icon: '⬆️' },
    { id: 'shake', name: 'Shake', icon: '📳' },
    { id: 'ring', name: 'Ring', icon: '💍' },
    { id: 'fire', name: 'Fire', icon: '🔥' },
    { id: 'electric', name: 'Electric', icon: '⚡' },
    { id: 'rainbow', name: 'Rainbow', icon: '🌈' },
    { id: 'portal', name: 'Portal', icon: '🌌' },
    { id: 'hologram', name: 'Hologram', icon: '👾' },
    { id: 'glitch', name: 'Glitch', icon: '📺' },
    { id: 'frost', name: 'Frost', icon: '❄️' },
    { id: 'neon', name: 'Neon', icon: '💡' },
    { id: 'orbit', name: 'Orbit', icon: '🪐' },
    { id: 'matrix', name: 'Matrix', icon: '🟢' },
    { id: 'vortex', name: 'Vortex', icon: '🌪️' },
    { id: 'quantum', name: 'Quantum', icon: '⚛️' },
    { id: 'nebula', name: 'Nebula', icon: '☁️' },
    { id: 'supernova', name: 'Supernova', icon: '💥' },
    { id: 'digital', name: 'Digital', icon: '💾' },
    { id: 'cosmic', name: 'Cosmic', icon: '🌠' },
    { id: 'plasma', name: 'Plasma', icon: '🔮' }
];

const nameEffects = [
    { id: 'none', name: 'None', icon: '⭕' },
    { id: 'wave', name: 'Wave', icon: '🌊' },
    { id: 'bounce', name: 'Bounce', icon: '⬆️' },
    { id: 'shake', name: 'Shake', icon: '📳' },
    { id: 'glitch', name: 'Glitch', icon: '📺' },
    { id: 'rainbow', name: 'Rainbow', icon: '🌈' },
    { id: 'neon', name: 'Neon', icon: '💡' },
    { id: 'typewriter', name: 'Typewriter', icon: '⌨️' },
    { id: 'slide', name: 'Slide', icon: '➡️' },
    { id: 'fade', name: 'Fade', icon: '👻' },
    { id: 'zoom', name: 'Zoom', icon: '🔍' },
    { id: 'flip', name: 'Flip', icon: '🔄' },
    { id: 'shadow', name: 'Shadow', icon: '🌑' },
    { id: 'gradient', name: 'Gradient', icon: '🎨' },
    { id: 'electric', name: 'Electric', icon: '⚡' },
    { id: 'fire', name: 'Fire Text', icon: '🔥' },
    { id: 'matrix', name: 'Matrix', icon: '🟢' },
    { id: 'cyber', name: 'Cyber', icon: '🤖' },
    { id: 'vortex', name: 'Vortex', icon: '🌪️' },
];
</script>

<template>
    <div class="rounded-xl bg-black/60 border border-white/10">
        <div class="p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500/20 to-purple-600/20 border border-purple-500/30 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-bold text-white">Avatar & Name Effects</h2>
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

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="qe_color" value="Effect Color" />
                            <input v-model="form.color" id="qe_color" type="color" class="mt-1 w-full h-8 rounded border-2 border-gray-700 bg-gray-900 cursor-pointer" />
                        </div>
                        <div>
                            <InputLabel for="qe_border" value="Border Color" />
                            <input v-model="form.avatar_border_color" id="qe_border" type="color" class="mt-1 w-full h-8 rounded border-2 border-gray-700 bg-gray-900 cursor-pointer" />
                        </div>
                    </div>

                    <div>
                        <InputLabel>Avatar Effect <span class="text-xs text-purple-400">({{ avatarEffects.find(e => e.id === form.avatar_effect)?.name }})</span></InputLabel>
                        <div class="mt-1 grid grid-cols-8 gap-1">
                            <button v-for="effect in avatarEffects" :key="effect.id" type="button" @click="form.avatar_effect = effect.id"
                                :class="form.avatar_effect === effect.id ? 'border-purple-500 bg-purple-500/20 shadow-md shadow-purple-500/40' : 'border-white/10 bg-black/20 hover:border-purple-500/50'"
                                class="w-12 h-12 rounded border transition-all hover:scale-105" :title="effect.name">
                                <span class="flex items-center justify-center text-2xl">{{ effect.icon }}</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <InputLabel>Name Effect <span class="text-xs text-pink-400">({{ nameEffects.find(e => e.id === form.name_effect)?.name }})</span></InputLabel>
                        <div class="mt-1 grid grid-cols-8 gap-1">
                            <button v-for="effect in nameEffects" :key="effect.id" type="button" @click="form.name_effect = effect.id"
                                :class="form.name_effect === effect.id ? 'border-pink-500 bg-pink-500/20 shadow-md shadow-pink-500/40' : 'border-white/10 bg-black/20 hover:border-pink-500/50'"
                                class="w-12 h-12 rounded border transition-all hover:scale-105" :title="effect.name">
                                <span class="flex items-center justify-center text-2xl">{{ effect.icon }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <InputLabel value="Preview" />
                    <div class="mt-1 p-4 rounded-lg bg-gradient-to-br from-black/40 to-black/20 border border-white/5 h-full flex items-center justify-center">
                        <div class="flex flex-col items-center gap-3">
                            <div :class="'avatar-effect-' + form.avatar_effect" :style="`--effect-color: ${form.color}; --border-color: ${form.avatar_border_color}`">
                                <img :src="user.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'" class="w-24 h-24 rounded-full object-cover border-4" :style="`border-color: ${form.avatar_border_color}`" />
                            </div>
                            <div :class="'name-effect-' + form.name_effect" :style="`--effect-color: ${form.color}`">
                                <div class="text-lg font-bold text-white" v-html="q3tohtml(user.name)"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
