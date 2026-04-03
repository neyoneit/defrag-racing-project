<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);

const defaults = {
    bar_width: 600, bar_height: 48,
    bar_border_color: '#4b5563', bar_border_width: 1, bar_border_radius: 24,
    bar_bg_color: 'rgba(31,41,55,0.8)',
    bar_fill_color: '#2563eb', bar_fill_color2: '#3b82f6',
    bar_animation: 'shimmer',
    bg_type: 'transparent', bg_color: '#00FF00',
    text_player_name: { visible: true, font: 'Inter', size: 20, color: '#ffffff', shadow: true, bold: true },
    text_subtitle: { visible: true, font: 'Inter', size: 12, color: '#9ca3af', shadow: false, bold: false },
    text_count: { visible: true, font: 'Inter', size: 20, color: '#ffffff', shadow: true, bold: true },
    text_label: { visible: true, font: 'Inter', size: 14, color: '#ffffff', shadow: true, bold: true },
    text_percentage: { visible: true, font: 'Inter', size: 24, color: '#ffffff', shadow: true, bold: true },
    text_remaining: { visible: true, font: 'Inter', size: 12, color: '#ffffff', shadow: true, bold: true },
    text_position: 'inside',
};

function mergeSettings(saved) {
    if (!saved) return { ...defaults };
    const m = { ...defaults, ...saved };
    for (const key of ['text_player_name', 'text_subtitle', 'text_count', 'text_label', 'text_percentage', 'text_remaining']) {
        m[key] = { ...defaults[key], ...(saved[key] || {}) };
    }
    return m;
}

const form = useForm(mergeSettings(user.value.widget_settings));

const fonts = ['Inter', 'Roboto', 'Roboto Mono', 'Oswald', 'Bebas Neue', 'Orbitron'];
const animations = [
    { value: 'none', label: 'None' },
    { value: 'shimmer', label: 'Shimmer' },
    { value: 'pulse', label: 'Pulse' },
    { value: 'stripes', label: 'Stripes' },
    { value: 'gradient', label: 'Gradient' },
    { value: 'wave', label: 'Wave' },
];

const textFields = [
    { key: 'text_player_name', label: 'Player Name' },
    { key: 'text_subtitle', label: 'Subtitle' },
    { key: 'text_count', label: 'Count (X / Y)' },
    { key: 'text_label', label: 'Completed Label' },
    { key: 'text_percentage', label: 'Percentage' },
    { key: 'text_remaining', label: 'Remaining' },
];

const widgetUrl = computed(() => {
    return window.location.origin + '/profile/' + user.value.id + '/progress-bar';
});

const copied = ref(false);
function copyUrl() {
    navigator.clipboard.writeText(widgetUrl.value);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
}

const save = () => {
    form.post(route('settings.widget'), { preserveScroll: true });
};

const resetDefaults = () => {
    const d = { ...defaults };
    for (const key of Object.keys(d)) {
        form[key] = typeof d[key] === 'object' ? { ...d[key] } : d[key];
    }
};

const collapsedText = ref({});
function toggleText(key) {
    collapsedText.value[key] = !collapsedText.value[key];
}
</script>

<template>
    <div class="space-y-4">
        <!-- OBS URL -->
        <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-purple-500/20 p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500/20 to-pink-600/20 border border-purple-500/30 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <h2 class="text-sm font-bold text-white">OBS Browser Source URL</h2>
            </div>
            <p class="text-xs text-gray-500 mb-1">Add this as a Browser Source in OBS. Use "transparent" background for overlay.</p>
            <p class="text-xs text-gray-400 mb-2">Recommended OBS size: <span class="font-mono font-bold text-purple-300">{{ form.bar_width + 32 }} x {{ form.bar_height + 80 }}</span> px</p>
            <div class="flex items-center gap-2">
                <code class="flex-1 px-3 py-2 bg-gray-900 rounded text-xs font-mono text-gray-300 break-all select-all border border-white/10">{{ widgetUrl }}</code>
                <button @click="copyUrl" class="px-3 py-2 text-xs font-bold rounded transition-all" :class="copied ? 'bg-green-600 text-white' : 'bg-white/10 text-gray-300 hover:bg-white/20'">
                    {{ copied ? 'Copied!' : 'Copy' }}
                </button>
                <a :href="widgetUrl" target="_blank" class="px-3 py-2 text-xs font-bold rounded bg-purple-600/20 text-purple-300 hover:bg-purple-600/30 transition-all border border-purple-500/30">
                    Preview
                </a>
            </div>
        </div>

        <!-- Progress Bar Settings -->
        <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 p-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-white">Progress Bar</h2>
                <div class="flex items-center gap-2">
                    <button @click="resetDefaults" class="px-2 py-1 text-[10px] font-medium text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 rounded transition-all">Reset Defaults</button>
                    <div v-if="form.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                        <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <span class="text-xs font-medium text-green-400">Saved</span>
                    </div>
                    <button @click="save" :disabled="form.processing" class="px-3 py-1 text-xs font-bold text-white bg-purple-600 hover:bg-purple-700 rounded transition-all disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- Left: Bar settings -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Bar Dimensions</h3>
                        <span class="text-[10px] text-purple-300 font-mono">OBS: {{ form.bar_width + 32 }} x {{ form.bar_height + 80 }} px</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-[10px] text-gray-400">
                            Width (px)
                            <input v-model.number="form.bar_width" type="number" min="200" max="1200" class="mt-0.5 w-full px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white" />
                        </label>
                        <label class="text-[10px] text-gray-400">
                            Height (px)
                            <input v-model.number="form.bar_height" type="number" min="20" max="120" class="mt-0.5 w-full px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white" />
                        </label>
                        <label class="text-[10px] text-gray-400">
                            Border Radius
                            <input v-model.number="form.bar_border_radius" type="number" min="0" max="60" class="mt-0.5 w-full px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white" />
                        </label>
                        <label class="text-[10px] text-gray-400">
                            Border Width
                            <input v-model.number="form.bar_border_width" type="number" min="0" max="8" class="mt-0.5 w-full px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white" />
                        </label>
                    </div>

                    <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider pt-2">Colors</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-[10px] text-gray-400">
                            Fill Color
                            <div class="flex items-center gap-1 mt-0.5">
                                <input v-model="form.bar_fill_color" type="color" class="w-6 h-6 rounded cursor-pointer border-0 bg-transparent" />
                                <input v-model="form.bar_fill_color" type="text" class="flex-1 px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white font-mono" />
                            </div>
                        </label>
                        <label class="text-[10px] text-gray-400">
                            Fill Color 2
                            <div class="flex items-center gap-1 mt-0.5">
                                <input v-model="form.bar_fill_color2" type="color" class="w-6 h-6 rounded cursor-pointer border-0 bg-transparent" />
                                <input v-model="form.bar_fill_color2" type="text" class="flex-1 px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white font-mono" />
                            </div>
                        </label>
                        <label class="text-[10px] text-gray-400">
                            Border Color
                            <div class="flex items-center gap-1 mt-0.5">
                                <input v-model="form.bar_border_color" type="color" class="w-6 h-6 rounded cursor-pointer border-0 bg-transparent" />
                                <input v-model="form.bar_border_color" type="text" class="flex-1 px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white font-mono" />
                            </div>
                        </label>
                        <label class="text-[10px] text-gray-400">
                            Background
                            <input v-model="form.bar_bg_color" type="text" class="mt-0.5 w-full px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white font-mono" />
                        </label>
                    </div>
                </div>

                <!-- Right: Animation + Background -->
                <div class="space-y-3">
                    <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Animation</h3>
                    <div class="grid grid-cols-3 gap-1">
                        <button v-for="anim in animations" :key="anim.value" @click="form.bar_animation = anim.value"
                            class="px-2 py-1.5 text-[10px] font-medium rounded transition-all"
                            :class="form.bar_animation === anim.value ? 'bg-purple-600/30 border-purple-400/50 text-white border' : 'bg-white/5 border border-white/10 text-gray-400 hover:bg-white/10'">
                            {{ anim.label }}
                        </button>
                    </div>

                    <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider pt-2">Page Background (OBS)</h3>
                    <div class="flex gap-1">
                        <button v-for="bg in [{v:'transparent',l:'Transparent'},{v:'chroma',l:'Chroma Green'},{v:'custom',l:'Custom'}]" :key="bg.v" @click="form.bg_type = bg.v"
                            class="px-2 py-1.5 text-[10px] font-medium rounded transition-all flex-1"
                            :class="form.bg_type === bg.v ? 'bg-purple-600/30 border-purple-400/50 text-white border' : 'bg-white/5 border border-white/10 text-gray-400 hover:bg-white/10'">
                            {{ bg.l }}
                        </button>
                    </div>
                    <div v-if="form.bg_type === 'custom'" class="flex items-center gap-2">
                        <input v-model="form.bg_color" type="color" class="w-6 h-6 rounded cursor-pointer border-0 bg-transparent" />
                        <input v-model="form.bg_color" type="text" class="flex-1 px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white font-mono" />
                    </div>

                    <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider pt-2">Text Position</h3>
                    <div class="flex gap-1">
                        <button v-for="pos in [{v:'above',l:'Above Bar'},{v:'inside',l:'Inside Bar'},{v:'below',l:'Below Bar'}]" :key="pos.v" @click="form.text_position = pos.v"
                            class="px-2 py-1.5 text-[10px] font-medium rounded transition-all flex-1"
                            :class="form.text_position === pos.v ? 'bg-purple-600/30 border-purple-400/50 text-white border' : 'bg-white/5 border border-white/10 text-gray-400 hover:bg-white/10'">
                            {{ pos.l }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Text element settings -->
            <div class="mt-4 pt-4 border-t border-white/5">
                <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Text Elements</h3>
                <div class="space-y-1">
                    <div v-for="tf in textFields" :key="tf.key" class="rounded-lg border border-white/5 overflow-hidden">
                        <button @click="toggleText(tf.key)" class="w-full flex items-center justify-between px-3 py-2 text-xs hover:bg-white/5 transition-colors">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" v-model="form[tf.key].visible" @click.stop class="w-3 h-3 rounded border-gray-600 bg-gray-900 text-purple-600" />
                                <span class="font-medium" :class="form[tf.key].visible ? 'text-gray-300' : 'text-gray-600'">{{ tf.label }}</span>
                            </div>
                            <svg class="w-3 h-3 text-gray-500 transition-transform" :class="collapsedText[tf.key] ? '' : 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div v-show="!collapsedText[tf.key]" class="px-3 pb-3 pt-1">
                            <div class="grid grid-cols-4 gap-2">
                                <label class="text-[10px] text-gray-400">
                                    Font
                                    <select v-model="form[tf.key].font" class="mt-0.5 w-full px-1 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white">
                                        <option v-for="f in fonts" :key="f" :value="f">{{ f }}</option>
                                    </select>
                                </label>
                                <label class="text-[10px] text-gray-400">
                                    Size (px)
                                    <input v-model.number="form[tf.key].size" type="number" min="8" max="72" class="mt-0.5 w-full px-2 py-1 text-xs bg-gray-900 border border-white/10 rounded text-white" />
                                </label>
                                <label class="text-[10px] text-gray-400">
                                    Color
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <input v-model="form[tf.key].color" type="color" class="w-5 h-5 rounded cursor-pointer border-0 bg-transparent" />
                                        <input v-model="form[tf.key].color" type="text" class="flex-1 px-1 py-1 text-[10px] bg-gray-900 border border-white/10 rounded text-white font-mono" />
                                    </div>
                                </label>
                                <div class="flex items-end gap-2 pb-1">
                                    <label class="text-[10px] text-gray-400 flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" v-model="form[tf.key].bold" class="w-3 h-3 rounded border-gray-600 bg-gray-900 text-purple-600" />
                                        Bold
                                    </label>
                                    <label class="text-[10px] text-gray-400 flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" v-model="form[tf.key].shadow" class="w-3 h-3 rounded border-gray-600 bg-gray-900 text-purple-600" />
                                        Shadow
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
