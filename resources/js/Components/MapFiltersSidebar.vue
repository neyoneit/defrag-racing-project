<script setup>
    import { ref, onMounted, computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SpecialRadio from '@/Components/Basic/SpecialRadio.vue';
    import PlayerSelect from '@/Components/Basic/PlayerSelect.vue';
    import ItemsSelect from '@/Components/Basic/ItemsSelect.vue';
    import axios from 'axios';

    const props = defineProps({
        queries: Object,
    });

    const profiles = ref([]);

    onMounted(async () => {
        try {
            const response = await axios.get('/api/maps/profiles');
            profiles.value = response.data;
        } catch (error) {
            console.error('Error fetching profiles:', error);
        }
    });

    const difficulties = {
        '1': { value: 'Beginner', color: 'bg-green-600' },
        '2': { value: 'Easy', color: 'bg-lime-600' },
        '3': { value: 'Medium', color: 'bg-yellow-600' },
        '4': { value: 'Hard', color: 'bg-orange-600' },
        '5': { value: 'Extreme', color: 'bg-red-600' },
    };

    const types = {
        'run': { value: 'Run', color: 'bg-blue-600' },
        'team': { value: 'Team', color: 'bg-blue-600' },
        'freestyle': { value: 'FS', color: 'bg-blue-600' },
        'fastcaps': { value: 'FC', color: 'bg-blue-600' },
    };

    const physics = {
        'vq3': { value: 'VQ3', color: 'bg-blue-600' },
        'cpm': { value: 'CPM', color: 'bg-green-600' },
    };

    const weapons = [
        { code: 'gauntlet', name: 'Gauntlet' },
        { code: 'mg', name: 'Machine Gun' },
        { code: 'sg', name: 'Shot Gun' },
        { code: 'gl', name: 'Grenade Launcher' },
        { code: 'rl', name: 'Rocket Launcher' },
        { code: 'lg', name: 'Lightening Gun' },
        { code: 'rg', name: 'Rail Gun' },
        { code: 'pg', name: 'Plasma Gun' },
        { code: 'bfg', name: 'BFG' },
        { code: 'hook', name: 'Grappling Hook' },
        { code: 'cg', name: 'Chain Gun' },
        { code: 'ng', name: 'Nail Gun' },
        { code: 'pml', name: 'Proximity Mine Launcher' },
    ];

    const functions = [
        { code: 'door', name: 'Door' },
        { code: 'button', name: 'Button' },
        { code: 'tele', name: 'Teleporter' },
        { code: 'jumppad', name: 'Jump Pad' },
        { code: 'moving', name: 'Moving Object' },
        { code: 'slick', name: 'Slick' },
        { code: 'water', name: 'Water' },
        { code: 'fog', name: 'Fog' },
        { code: 'slime', name: 'Slime' },
        { code: 'lava', name: 'Lava' },
        { code: 'break', name: 'Breakable' },
        { code: 'sound', name: 'Sound' },
        { code: 'timer', name: 'Timer' },
    ];

    const items = [
        { code: 'ra', name: 'Red Armor' },
        { code: 'ya', name: 'Yellow Armor' },
        { code: 'enviro', name: 'Battle Suit' },
        { code: 'flight', name: 'Flight' },
        { code: 'haste', name: 'Haste' },
        { code: 'health', name: 'Health' },
        { code: 'mega', name: 'Mega Health' },
        { code: 'smallhealth', name: 'Small Health' },
        { code: 'bighealth', name: 'Large Health' },
        { code: 'invis', name: 'Invisibility' },
        { code: 'quad', name: 'Quad Damage' },
        { code: 'regen', name: 'Regeneration' },
        { code: 'medkit', name: 'Medi Kit / Double Jump' },
        { code: 'ptele', name: 'Personal Teleporter' },
    ];

    const form = useForm({
        search: props.queries?.search ?? '',
        author: props.queries?.author ?? '',
        difficulty: props.queries?.difficulty ?? [],
        gametype: props.queries?.gametype ?? [],
        physics: props.queries?.physics ?? [],
        has_records: props.queries?.has_records ?? [],
        have_no_records: props.queries?.have_no_records ?? [],
        world_record: props.queries?.world_record ?? [],
        items: props.queries?.items ?? {},
        weapons: props.queries?.weapons ?? {},
        functions: props.queries?.functions ?? {},
        records_count: props.queries?.records_count ?? [0, 1000],
        average_length: props.queries?.average_length ?? [0, 1000],
        rank_min: props.queries?.rank_min ?? 1,
        rank_max: props.queries?.rank_max ?? 999,
    });

    const emit = defineEmits(['search']);

    const onFilterSubmit = () => {
        emit('search', form.data());
    };

    const resetFilters = () => {
        form.search = '';
        form.author = '';
        form.difficulty = [];
        form.gametype = [];
        form.physics = [];
        form.has_records = [];
        form.have_no_records = [];
        form.world_record = [];
        form.items = {};
        form.weapons = {};
        form.functions = {};
        form.records_count = [0, 1000];
        form.average_length = [0, 1000];
        form.rank_min = 1;
        form.rank_max = 999;
        recordsSliderMin.value = 0;
        recordsSliderMax.value = 100;
        lengthSliderMin.value = 0;
        lengthSliderMax.value = 100;
        rankSliderMin.value = 1;
        rankSliderMax.value = 100;
    };

    const openSections = ref({
        search: true,
        gametype: true,
        players: false,
        content: false,
        advanced: false,
    });

    const toggleSection = (key) => {
        openSections.value[key] = !openSections.value[key];
    };

    const activeFilterCount = computed(() => {
        let count = 0;
        if (form.search) count++;
        if (form.author) count++;
        if (form.difficulty.length) count += form.difficulty.length;
        if (form.gametype.length) count += form.gametype.length;
        if (form.physics.length) count += form.physics.length;
        if (form.has_records.length) count++;
        if (form.have_no_records.length) count++;
        if (form.world_record.length) count++;
        if (form.weapons?.include?.length || form.weapons?.exclude?.length) count++;
        if (form.functions?.include?.length || form.functions?.exclude?.length) count++;
        if (form.items?.include?.length || form.items?.exclude?.length) count++;
        if (form.records_count[0] > 0 || form.records_count[1] < 1000) count++;
        if (form.average_length[0] > 0 || form.average_length[1] < 1000) count++;
        if (form.rank_min > 1 || form.rank_max < 999) count++;
        return count;
    });

    const sliderToValue = (sliderPos, max) => {
        if (sliderPos <= 0) return 0;
        const normalized = sliderPos / 100;
        return Math.max(1, Math.round(Math.pow(normalized, 2.5) * max));
    };

    const valueToSlider = (value, max) => {
        if (value <= 0) return 0;
        const normalized = value / max;
        return Math.max(1, Math.round(Math.pow(normalized, 1/2.5) * 100));
    };

    const recordsSliderMin = ref(valueToSlider(form.records_count[0], 1000));
    const recordsSliderMax = ref(valueToSlider(form.records_count[1], 1000));
    const recordsMinOnTop = ref(Number(recordsSliderMin.value) > 50);
    const lengthSliderMin = ref(valueToSlider(form.average_length[0], 1000));
    const lengthSliderMax = ref(valueToSlider(form.average_length[1], 1000));
    const lengthMinOnTop = ref(Number(lengthSliderMin.value) > 50);

    const rankToSlider = (rank) => {
        if (rank <= 1) return 1;
        return Math.max(1, Math.round(Math.pow(rank / 999, 1/2.5) * 100));
    };
    const sliderToRank = (pos) => {
        if (pos <= 1) return 1;
        return Math.max(1, Math.round(Math.pow(pos / 100, 2.5) * 999));
    };
    const rankSliderMin = ref(rankToSlider(form.rank_min));
    const rankSliderMax = ref(rankToSlider(form.rank_max));
    const rankMinOnTop = ref(Number(rankSliderMin.value) > 50);

    const updateRankMin = () => {
        if (Number(rankSliderMin.value) > Number(rankSliderMax.value)) {
            rankSliderMin.value = rankSliderMax.value;
        }
        form.rank_min = sliderToRank(rankSliderMin.value);
    };
    const updateRankMax = () => {
        if (Number(rankSliderMax.value) < Number(rankSliderMin.value)) {
            rankSliderMax.value = rankSliderMin.value;
        }
        form.rank_max = sliderToRank(rankSliderMax.value);
    };

    const updateRecordsMin = () => {
        if (Number(recordsSliderMin.value) > Number(recordsSliderMax.value)) {
            recordsSliderMin.value = recordsSliderMax.value;
        }
        form.records_count[0] = sliderToValue(recordsSliderMin.value, 1000);
    };

    const updateRecordsMax = () => {
        if (Number(recordsSliderMax.value) < Number(recordsSliderMin.value)) {
            recordsSliderMax.value = recordsSliderMin.value;
        }
        form.records_count[1] = sliderToValue(recordsSliderMax.value, 1000);
    };

    const updateLengthMin = () => {
        if (Number(lengthSliderMin.value) > Number(lengthSliderMax.value)) {
            lengthSliderMin.value = lengthSliderMax.value;
        }
        form.average_length[0] = sliderToValue(lengthSliderMin.value, 1000);
    };

    const updateLengthMax = () => {
        if (Number(lengthSliderMax.value) < Number(lengthSliderMin.value)) {
            lengthSliderMax.value = lengthSliderMin.value;
        }
        form.average_length[1] = sliderToValue(lengthSliderMax.value, 1000);
    };

</script>

<template>
    <div class="sidebar-root">
        <!-- Header -->
        <div class="sidebar-header">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                </svg>
                <span class="text-sm font-bold text-white">Filter Maps</span>
                <span v-if="activeFilterCount > 0" class="bg-blue-500 text-white text-[11px] font-bold rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0">{{ activeFilterCount }}</span>
            </div>
            <button v-if="activeFilterCount > 0" @click="resetFilters" class="text-xs text-red-400 hover:text-red-300 font-medium transition flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                Reset
            </button>
        </div>

        <!-- Scrollable sections -->
        <div class="sidebar-body">

            <!-- Search inputs (always visible, no accordion) -->
            <div class="px-3 py-2.5 space-y-2 border-b border-white/5">
                <TextInput type="text" v-model="form.search" class="block w-full" placeholder="Map name..." v-on:keyup.enter="onFilterSubmit" />
                <TextInput type="text" v-model="form.author" class="block w-full" placeholder="Author..." v-on:keyup.enter="onFilterSubmit" />
            </div>

            <!-- Difficulty, Gametype & Physics -->
            <div class="sidebar-section">
                <div class="space-y-2 px-3 py-2">
                    <div>
                        <label class="field-label">Difficulty</label>
                        <SpecialRadio :options="difficulties" v-model="form.difficulty" :multi="true" :values="form.difficulty" />
                    </div>
                    <div>
                        <label class="field-label">Gametype</label>
                        <SpecialRadio :options="types" v-model="form.gametype" :multi="true" :values="form.gametype" />
                    </div>
                    <div>
                        <label class="field-label">Physics</label>
                        <SpecialRadio :options="physics" v-model="form.physics" :values="form.physics" :multi="true" />
                    </div>
                </div>
            </div>

            <!-- Player Records -->
            <div class="sidebar-section">
                <button @click="toggleSection('players')" class="accordion-btn">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                        <span>Player Records</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span v-if="form.has_records.length + form.have_no_records.length + form.world_record.length > 0"
                            class="bg-purple-500/30 text-purple-300 text-[11px] font-bold rounded-full px-1.5 leading-5">
                            {{ form.has_records.length + form.have_no_records.length + form.world_record.length }}
                        </span>
                        <svg class="chevron" :class="{ open: openSections.players }" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                    </div>
                </button>
                <div v-show="openSections.players" class="accordion-content">
                    <div class="space-y-2">
                        <div>
                            <label class="field-label">Has Record</label>
                            <PlayerSelect :options="profiles" :multi="true" v-model="form.has_records" :values="form.has_records" />
                        </div>
                        <div>
                            <label class="field-label">No Record</label>
                            <PlayerSelect :options="profiles" :multi="true" v-model="form.have_no_records" :values="form.have_no_records" />
                        </div>
                        <div>
                            <label class="field-label">World Record</label>
                            <PlayerSelect :options="profiles" :multi="false" v-model="form.world_record" :values="form.world_record" />
                        </div>
                        <div class="slider-group pt-2 border-t border-white/5">
                            <div class="flex items-center justify-between mb-1">
                                <label class="field-label !mb-0">Rank Range</label>
                                <span class="slider-value">{{ form.rank_min }} - {{ form.rank_max === 999 ? '&infin;' : form.rank_max }}</span>
                            </div>
                            <div class="slider-track">
                                <input type="range" :min="1" :max="100" v-model="rankSliderMin" class="slider" :class="rankMinOnTop ? 'slider-top' : 'slider-bottom'" @input="updateRankMin" @mousedown="rankMinOnTop = true" @touchstart="rankMinOnTop = true" />
                                <input type="range" :min="1" :max="100" v-model="rankSliderMax" class="slider" :class="rankMinOnTop ? 'slider-bottom' : 'slider-top'" @input="updateRankMax" @mousedown="rankMinOnTop = false" @touchstart="rankMinOnTop = false" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Content -->
            <div class="sidebar-section">
                <button @click="toggleSection('content')" class="accordion-btn">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-orange-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>
                        <span>Map Content</span>
                    </div>
                    <svg class="chevron" :class="{ open: openSections.content }" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                </button>
                <div v-show="openSections.content" class="accordion-content">
                    <div class="space-y-2">
                        <ItemsSelect :options="weapons" :multi="false" v-model="form.weapons" :values="form.weapons" placeholder="Weapons" />
                        <ItemsSelect :options="functions" :multi="false" v-model="form.functions" :values="form.functions" placeholder="Functions" />
                        <ItemsSelect :options="items" :multi="false" v-model="form.items" :values="form.items" placeholder="Items" />
                    </div>
                </div>
            </div>

            <!-- Advanced -->
            <div class="sidebar-section sidebar-section-last">
                <button @click="toggleSection('advanced')" class="accordion-btn">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-cyan-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <span>Advanced</span>
                    </div>
                    <svg class="chevron" :class="{ open: openSections.advanced }" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                </button>
                <div v-show="openSections.advanced" class="accordion-content">
                    <div class="space-y-5">
                        <div class="slider-group">
                            <div class="flex items-center justify-between mb-1">
                                <label class="field-label !mb-0">Number of Records</label>
                                <span class="slider-value">{{ form.records_count[0] }} - {{ form.records_count[1] === 1000 ? '&infin;' : form.records_count[1] }}</span>
                            </div>
                            <div class="slider-track">
                                <input type="range" :min="0" :max="100" v-model="recordsSliderMin" class="slider" :class="recordsMinOnTop ? 'slider-top' : 'slider-bottom'" @input="updateRecordsMin" @mousedown="recordsMinOnTop = true" @touchstart="recordsMinOnTop = true" />
                                <input type="range" :min="0" :max="100" v-model="recordsSliderMax" class="slider" :class="recordsMinOnTop ? 'slider-bottom' : 'slider-top'" @input="updateRecordsMax" @mousedown="recordsMinOnTop = false" @touchstart="recordsMinOnTop = false" />
                            </div>
                        </div>
                        <div class="slider-group">
                            <div class="flex items-center justify-between mb-1">
                                <label class="field-label !mb-0">Map Length (sec)</label>
                                <span class="slider-value">{{ form.average_length[0] }} - {{ form.average_length[1] === 1000 ? '&infin;' : form.average_length[1] }}</span>
                            </div>
                            <div class="slider-track">
                                <input type="range" :min="0" :max="100" v-model="lengthSliderMin" class="slider" :class="lengthMinOnTop ? 'slider-top' : 'slider-bottom'" @input="updateLengthMin" @mousedown="lengthMinOnTop = true" @touchstart="lengthMinOnTop = true" />
                                <input type="range" :min="0" :max="100" v-model="lengthSliderMax" class="slider" :class="lengthMinOnTop ? 'slider-bottom' : 'slider-top'" @input="updateLengthMax" @mousedown="lengthMinOnTop = false" @touchstart="lengthMinOnTop = false" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Button -->
        <div class="sidebar-footer">
            <button :disabled="form.processing" @click="onFilterSubmit" class="search-btn">
                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                {{ form.processing ? 'Searching...' : 'Search Maps' }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.sidebar-root {
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.75rem;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 7.5rem);
    position: sticky;
    top: 7rem;
    overflow: hidden;
}

/* Header */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.02);
}

/* Scrollable body */
.sidebar-body {
    overflow-y: auto;
    flex: 1;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.06) transparent;
}

.sidebar-body::-webkit-scrollbar { width: 5px; }
.sidebar-body::-webkit-scrollbar-track { background: transparent; }
.sidebar-body::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.08); border-radius: 3px; }

/* Sections */
.sidebar-section {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.sidebar-section-last {
    border-bottom: none;
}

/* Accordion button */
.accordion-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.75);
    transition: all 0.15s;
    cursor: pointer;
}
.accordion-btn:hover {
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.9);
}

/* Chevron */
.chevron {
    width: 1.125rem;
    height: 1.125rem;
    color: rgba(255, 255, 255, 0.35);
    transition: transform 0.2s ease, color 0.15s;
    flex-shrink: 0;
}
.accordion-btn:hover .chevron {
    color: rgba(255, 255, 255, 0.6);
}
.chevron.open {
    transform: rotate(180deg);
}

/* Accordion content */
.accordion-content {
    padding: 0 1rem 1rem;
}

/* Field labels */
.field-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.45);
    margin-bottom: 0.375rem;
}

/* Footer */
.sidebar-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.3);
}

.search-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    background: #2563eb;
    color: white;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: 0.5rem;
    transition: background 0.15s;
}
.search-btn:hover { background: #3b82f6; }
.search-btn:active { background: #1d4ed8; }
.search-btn:disabled { background: #374151; cursor: not-allowed; }

/* Slider group */
.slider-group {
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 0.5rem;
}

.slider-value {
    font-size: 0.75rem;
    font-family: ui-monospace, monospace;
    color: #60a5fa;
    background: rgba(59, 130, 246, 0.1);
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
}

/* Dual-thumb range slider */
.slider-track {
    position: relative;
    height: 24px;
    display: flex;
    align-items: center;
}


.slider {
    position: absolute;
    width: 100%;
    height: 3px;
    -webkit-appearance: none;
    appearance: none;
    background: transparent;
    pointer-events: none;
    outline: none;
    margin: 0;
}

.slider::-webkit-slider-runnable-track {
    height: 3px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 2px;
}

.slider::-moz-range-track {
    height: 3px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 2px;
}

.slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    pointer-events: all;
    width: 14px;
    height: 14px;
    margin-top: -5.5px;
    background: #3b82f6;
    border: 2px solid #bfdbfe;
    border-radius: 50%;
    cursor: pointer;
    transition: box-shadow 0.15s;
}

.slider::-moz-range-thumb {
    pointer-events: all;
    width: 14px;
    height: 14px;
    background: #3b82f6;
    border: 2px solid #bfdbfe;
    border-radius: 50%;
    cursor: pointer;
    transition: box-shadow 0.15s;
}

.slider::-webkit-slider-thumb:hover {
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
}

.slider::-moz-range-thumb:hover {
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
}

.slider-top { z-index: 3; }
.slider-bottom { z-index: 2; }
</style>
