<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    avatar_effects_intensity: user.value.avatar_effects_intensity ?? 100,
    avatar_effects_speed: user.value.avatar_effects_speed ?? 100,
    name_effects_intensity: user.value.name_effects_intensity ?? 100,
    name_effects_speed: user.value.name_effects_speed ?? 100,
});

const applyEffects = () => {
    document.body.classList.remove('avatar-fx-off', 'avatar-speed-off', 'avatar-speed-reduced', 'name-fx-off', 'name-speed-off', 'name-speed-reduced');

    if (form.avatar_effects_intensity === 0) document.body.classList.add('avatar-fx-off');
    if (form.avatar_effects_speed === 0) document.body.classList.add('avatar-speed-off');
    else if (form.avatar_effects_speed <= 50) document.body.classList.add('avatar-speed-reduced');

    if (form.name_effects_intensity === 0) document.body.classList.add('name-fx-off');
    if (form.name_effects_speed === 0) document.body.classList.add('name-speed-off');
    else if (form.name_effects_speed <= 50) document.body.classList.add('name-speed-reduced');
};

let saveTimer = null;
const autoSave = () => {
    applyEffects();
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        form.post(route('settings.effects-intensity'), { preserveScroll: true });
    }, 600);
};

watch(() => [form.avatar_effects_intensity, form.avatar_effects_speed, form.name_effects_intensity, form.name_effects_speed], autoSave);

const speedLabel = (val) => {
    if (val === 0) return 'Paused';
    if (val <= 50) return 'Slow';
    return 'Normal';
};

const speedBadgeClass = (val) => {
    if (val === 0) return 'text-red-400 bg-red-500/10 border border-red-500/20';
    if (val <= 50) return 'text-amber-400 bg-amber-500/10 border border-amber-500/20';
    return 'text-green-400 bg-green-500/10 border border-green-500/20';
};

const toggleAvatar = () => {
    form.avatar_effects_intensity = form.avatar_effects_intensity === 0 ? 100 : 0;
};

const toggleName = () => {
    form.name_effects_intensity = form.name_effects_intensity === 0 ? 100 : 0;
};
</script>

<template>
    <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
        <div class="p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500/20 to-orange-600/20 border border-amber-500/30 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-amber-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-bold text-white">Effects Settings</h2>
                </div>
                <div class="flex items-center gap-2">
                    <div v-if="form.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                        <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="text-xs font-medium text-green-400">Saved</span>
                    </div>
                    <div v-else-if="form.processing" class="flex items-center gap-1.5 px-2 py-1 rounded bg-blue-500/10 border border-blue-500/20">
                        <span class="text-xs font-medium text-blue-400">Saving...</span>
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-500 mb-4">Control how you see other players' effects. This only affects your view.</p>

            <div class="grid grid-cols-2 gap-4">
                <!-- Avatar Effects -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-purple-400 uppercase tracking-wider">Avatar Effects</h3>

                    <!-- Avatar On/Off -->
                    <div class="flex items-center justify-between p-2 rounded-lg bg-white/5 border border-white/10">
                        <span class="text-xs font-medium text-gray-300">Show Effects</span>
                        <button @click="toggleAvatar" class="relative w-10 h-5 rounded-full transition-colors" :class="form.avatar_effects_intensity > 0 ? 'bg-purple-600' : 'bg-gray-700'">
                            <span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform" :class="form.avatar_effects_intensity > 0 ? 'translate-x-5' : ''"></span>
                        </button>
                    </div>

                    <!-- Avatar Animation Speed -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-gray-300">Animation Speed</label>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="speedBadgeClass(form.avatar_effects_speed)">{{ speedLabel(form.avatar_effects_speed) }}</span>
                        </div>
                        <input type="range" v-model.number="form.avatar_effects_speed" min="0" max="100" step="10"
                            class="w-full h-1.5 rounded-lg appearance-none cursor-pointer accent-purple-500"
                            :style="`background: linear-gradient(to right, rgb(168 85 247) ${form.avatar_effects_speed}%, rgb(55 65 81) ${form.avatar_effects_speed}%)`"
                        />
                        <div class="flex justify-between text-[10px] text-gray-600 mt-0.5">
                            <span>Paused</span>
                            <span>Slow</span>
                            <span>Normal</span>
                        </div>
                    </div>
                </div>

                <!-- Name Effects -->
                <div class="space-y-3">
                    <h3 class="text-xs font-bold text-pink-400 uppercase tracking-wider">Name Effects</h3>

                    <!-- Name On/Off -->
                    <div class="flex items-center justify-between p-2 rounded-lg bg-white/5 border border-white/10">
                        <span class="text-xs font-medium text-gray-300">Show Effects</span>
                        <button @click="toggleName" class="relative w-10 h-5 rounded-full transition-colors" :class="form.name_effects_intensity > 0 ? 'bg-pink-600' : 'bg-gray-700'">
                            <span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white transition-transform" :class="form.name_effects_intensity > 0 ? 'translate-x-5' : ''"></span>
                        </button>
                    </div>

                    <!-- Name Animation Speed -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-gray-300">Animation Speed</label>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="speedBadgeClass(form.name_effects_speed)">{{ speedLabel(form.name_effects_speed) }}</span>
                        </div>
                        <input type="range" v-model.number="form.name_effects_speed" min="0" max="100" step="10"
                            class="w-full h-1.5 rounded-lg appearance-none cursor-pointer accent-pink-500"
                            :style="`background: linear-gradient(to right, rgb(236 72 153) ${form.name_effects_speed}%, rgb(55 65 81) ${form.name_effects_speed}%)`"
                        />
                        <div class="flex justify-between text-[10px] text-gray-600 mt-0.5">
                            <span>Paused</span>
                            <span>Slow</span>
                            <span>Normal</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
