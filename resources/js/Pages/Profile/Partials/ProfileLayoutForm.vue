<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import axios from 'axios';

const page = usePage();
const user = computed(() => page.props.auth.user);

const saving = ref(false);
const recentlySuccessful = ref(false);

const allStatBoxes = [
    { id: 'performance', label: 'Performance', color: 'blue' },
    { id: 'activity', label: 'Activity', color: 'purple' },
    { id: 'record_types', label: 'Record Types', color: 'green' },
    { id: 'map_features', label: 'Map Features', color: 'yellow' },
    { id: 'demos_statistics', label: 'Demos Stats', color: 'pink' },
    { id: 'top_downloaded_demos', label: 'Top Demos', color: 'cyan' },
];

const allSections = [
    { id: 'activity_history', label: 'Activity History' },
    { id: 'records', label: 'VQ3 / CPM Records' },
    { id: 'similar_skill_rivals', label: 'Skill Level & Rivals' },
    { id: 'competitor_comparison', label: 'Competitor Comparison' },
    { id: 'known_aliases', label: 'Known Aliases' },
    { id: 'featured_maplists', label: 'Featured Maplists' },
    { id: 'map_completionist', label: 'Map Completionist' },
];

const defaultStatBoxes = ['performance', 'activity', 'record_types', 'map_features'];
const defaultSections = allSections.map(s => ({ id: s.id, visible: true }));

const savedLayout = user.value.profile_layout;

// Stat boxes as draggable items with selected state
const statBoxItems = ref(
    (() => {
        const saved = savedLayout?.stat_boxes || defaultStatBoxes;
        // Selected first (in saved order), then unselected
        const selected = saved.map(id => allStatBoxes.find(b => b.id === id)).filter(Boolean);
        const unselected = allStatBoxes.filter(b => !saved.includes(b.id));
        return [...selected, ...unselected].map(b => ({ ...b, selected: saved.includes(b.id) }));
    })()
);

const validSectionIds = allSections.map(s => s.id);
const sections = ref(
    (savedLayout?.sections || defaultSections.map(s => ({ ...s })))
        .filter(s => validSectionIds.includes(s.id))
);

// Ensure all sections exist
for (const section of allSections) {
    if (!sections.value.find(s => s.id === section.id)) {
        sections.value.push({ id: section.id, visible: true });
    }
}

const selectedCount = computed(() => statBoxItems.value.filter(b => b.selected).length);

const toggleBox = (id) => {
    const box = statBoxItems.value.find(b => b.id === id);
    if (!box) return;
    if (box.selected && selectedCount.value <= 1) return;
    if (!box.selected && selectedCount.value >= 4) return;
    box.selected = !box.selected;
};

const toggleSection = (sectionId) => {
    const section = sections.value.find(s => s.id === sectionId);
    if (section) section.visible = !section.visible;
};

const getSectionLabel = (id) => allSections.find(s => s.id === id)?.label || id;

const save = async () => {
    saving.value = true;
    try {
        await axios.post(route('settings.profile-layout'), {
            stat_boxes: statBoxItems.value.filter(b => b.selected).map(b => b.id),
            sections: sections.value,
        });
        recentlySuccessful.value = true;
        setTimeout(() => {
            recentlySuccessful.value = false;
            window.location.reload();
        }, 500);
    } catch (error) {
        console.error('Failed to save profile layout:', error);
    } finally {
        saving.value = false;
    }
};

const resetToDefaults = () => {
    const saved = defaultStatBoxes;
    const selected = saved.map(id => allStatBoxes.find(b => b.id === id)).filter(Boolean);
    const unselected = allStatBoxes.filter(b => !saved.includes(b.id));
    statBoxItems.value = [...selected, ...unselected].map(b => ({ ...b, selected: saved.includes(b.id) }));
    sections.value = defaultSections.map(s => ({ ...s }));
};

const colorMap = {
    blue: { bg: 'from-blue-500/20 to-blue-600/20', border: 'border-blue-500/30', text: 'text-blue-400', dot: 'bg-blue-400' },
    purple: { bg: 'from-purple-500/20 to-purple-600/20', border: 'border-purple-500/30', text: 'text-purple-400', dot: 'bg-purple-400' },
    green: { bg: 'from-green-500/20 to-green-600/20', border: 'border-green-500/30', text: 'text-green-400', dot: 'bg-green-400' },
    yellow: { bg: 'from-yellow-500/20 to-yellow-600/20', border: 'border-yellow-500/30', text: 'text-yellow-400', dot: 'bg-yellow-400' },
    pink: { bg: 'from-pink-500/20 to-pink-600/20', border: 'border-pink-500/30', text: 'text-pink-400', dot: 'bg-pink-400' },
    cyan: { bg: 'from-cyan-500/20 to-cyan-600/20', border: 'border-cyan-500/30', text: 'text-cyan-400', dot: 'bg-cyan-400' },
};
</script>

<template>
    <div class="rounded-xl bg-black/60 border border-white/10">
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500/20 to-indigo-600/20 border border-indigo-500/30 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-indigo-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-bold text-white">Profile Layout</h2>
                </div>
                <div class="flex items-center gap-2">
                    <div v-if="recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                        <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span class="text-xs font-medium text-green-400">Saved</span>
                    </div>
                    <button @click="resetToDefaults" class="px-2 py-1 text-xs font-medium text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 rounded transition-all">
                        Reset
                    </button>
                    <button @click="save" :disabled="saving" class="px-3 py-1 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded transition-all disabled:opacity-50">
                        {{ saving ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>

            <!-- Two columns: Stat Boxes left, Sections right -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <!-- Stat Boxes (draggable) -->
                <div>
                    <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Stat Boxes
                        <span :class="selectedCount >= 4 ? 'text-yellow-500' : selectedCount <= 1 ? 'text-red-500' : 'text-gray-600'">({{ selectedCount }}/4)</span>
                        <span v-if="selectedCount >= 4" class="text-yellow-500 ml-1">- max reached</span>
                        <span v-else-if="selectedCount <= 1" class="text-red-500 ml-1">- min 1 required</span>
                    </p>
                    <draggable
                        v-model="statBoxItems"
                        item-key="id"
                        :handle="false"
                        ghost-class="opacity-30"
                        animation="150"
                        class="space-y-1"
                    >
                        <template #item="{ element }">
                            <div
                                :class="[
                                    'flex items-center gap-2 px-2 py-1.5 rounded-lg border transition-all',
                                    element.selected
                                        ? `bg-gradient-to-r ${colorMap[element.color].bg} ${colorMap[element.color].border}`
                                        : selectedCount >= 4
                                            ? 'bg-black/10 border-white/5 opacity-20 cursor-not-allowed'
                                            : `bg-black/20 ${colorMap[element.color].border} border-dashed hover:bg-gradient-to-r hover:${colorMap[element.color].bg} cursor-pointer`
                                ]"
                                @click="!element.selected && selectedCount < 4 && toggleBox(element.id)"
                            >
                                <div class="stat-drag cursor-grab active:cursor-grabbing text-gray-500 hover:text-gray-300">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
                                        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                        <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                    </svg>
                                </div>
                                <div :class="['w-2 h-2 rounded-full', colorMap[element.color].dot]"></div>
                                <span class="flex-1 text-xs font-medium" :class="element.selected ? 'text-white' : 'text-gray-500'">
                                    {{ element.label }}
                                </span>
                                <button
                                    @click.stop="toggleBox(element.id)"
                                    :class="[
                                        'w-5 h-5 rounded flex items-center justify-center transition-all shrink-0',
                                        element.selected && selectedCount <= 1
                                            ? 'bg-white/10 cursor-not-allowed'
                                            : element.selected
                                                ? 'bg-white/20 hover:bg-red-500/30'
                                                : selectedCount >= 4
                                                    ? 'bg-white/5 border border-white/5 cursor-not-allowed'
                                                    : 'bg-white/5 hover:bg-green-500/30 border border-white/10 hover:border-green-500/30'
                                    ]"
                                    :disabled="(element.selected && selectedCount <= 1) || (!element.selected && selectedCount >= 4)"
                                >
                                    <svg v-if="element.selected" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else-if="selectedCount < 4" class="w-3 h-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </draggable>
                </div>

                <!-- Profile Sections (draggable) -->
                <div>
                    <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        Sections <span class="text-gray-600">(drag to reorder)</span>
                    </p>
                    <draggable
                        v-model="sections"
                        item-key="id"
                        :handle="false"
                        ghost-class="opacity-30"
                        animation="150"
                        class="space-y-1"
                    >
                        <template #item="{ element }">
                            <div
                                :class="[
                                    'flex items-center gap-2 px-2 py-1.5 rounded-lg border transition-all',
                                    element.visible
                                        ? 'bg-black/20 border-white/10'
                                        : 'bg-black/10 border-white/5 opacity-40'
                                ]"
                            >
                                <div class="section-drag cursor-grab active:cursor-grabbing text-gray-500 hover:text-gray-300">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
                                        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                                        <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                                    </svg>
                                </div>
                                <span class="flex-1 text-xs font-medium" :class="element.visible ? 'text-gray-200' : 'text-gray-500'">
                                    {{ getSectionLabel(element.id) }}
                                </span>
                                <button
                                    @click="toggleSection(element.id)"
                                    class="p-0.5 rounded hover:bg-white/10 transition-colors"
                                >
                                    <svg v-if="element.visible" class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </draggable>
                </div>
            </div>
        </div>
    </div>
</template>
