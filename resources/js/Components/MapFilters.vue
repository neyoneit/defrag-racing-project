<script setup>
    import { ref } from 'vue';
    import { Link, useForm } from '@inertiajs/vue3';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SpecialRadio from '@/Components/Basic/SpecialRadio.vue';
    import PlayerSelect from '@/Components/Basic/PlayerSelect.vue';
    import ItemsSelect from '@/Components/Basic/ItemsSelect.vue';

    const props = defineProps({
        show: Boolean,
        queries: Object,
        profiles: Array
    });

    const types = {
        'run': {
            value: 'Run',
            color: 'bg-blue-600'
        },
        'team': {
            value: 'Teamrun',
            color: 'bg-blue-600'
        },
        'freestyle': {
            value: 'Freestyle',
            color: 'bg-blue-600'
        },
        'fastcaps': {
            value: 'Fastcaps',
            color: 'bg-blue-600'
        },
    }

    const physics = {
        'vq3': {
            value: 'VQ3',
            color: 'bg-blue-600'
        },
        'cpm': {
            value: 'CPM',
            color: 'bg-green-600'
        }
    }

    const weapons = [
        {
            'code': 'gauntlet',
            'name': 'Gauntlet'
        },
        {
            'code': 'mg',
            'name': 'Machine Gun'
        },{
            'code': 'sg',
            'name': 'Shot Gun'
        },{
            'code': 'gl',
            'name': 'Grenade Launcher'
        },{
            'code': 'rl',
            'name': 'Rocket Launcher'
        },{
            'code': 'lg',
            'name': 'Lightening Gun'
        },{
            'code': 'rg',
            'name': 'Rail Gun'
        },{
            'code': 'pg',
            'name': 'Plasma Gun'
        },{
            'code': 'bfg',
            'name': 'BFG'
        },{
            'code': 'hook',
            'name': 'Grappling Hook'
        },{
            'code': 'cg',
            'name': 'Chain Gun'
        },{
            'code': 'ng',
            'name': 'Nail Gun'
        },{
            'code': 'pml',
            'name': 'Proximity Mine Launcher'
        },
    ];

    const functions = [
        {
            'code': 'door',
            'name': 'Door'
        },
        {
            'code': 'button',
            'name': 'Button'
        },{
            'code': 'tele',
            'name': 'Teleporter'
        },{
            'code': 'jumppad',
            'name': 'Jump Pad'
        },{
            'code': 'moving',
            'name': 'Moving Object'
        },{
            'code': 'slick',
            'name': 'Slick'
        },{
            'code': 'water',
            'name': 'Water'
        },{
            'code': 'fog',
            'name': 'Fog'
        },{
            'code': 'slime',
            'name': 'Slime'
        },{
            'code': 'lava',
            'name': 'Lava'
        },{
            'code': 'break',
            'name': 'Breakable'
        },{
            'code': 'sound',
            'name': 'Sound'
        },{
            'code': 'timer',
            'name': 'Timer'
        },
    ];

    const items = [
        {
            'code': 'ra',
            'name': 'Red Armor'
        },
        {
            'code': 'ya',
            'name': 'Yellow Armor'
        },{
            'code': 'enviro',
            'name': 'Battle Suit'
        },{
            'code': 'flight',
            'name': 'Flight'
        },{
            'code': 'haste',
            'name': 'Haste'
        },{
            'code': 'health',
            'name': 'Health'
        },{
            'code': 'mega',
            'name': 'Mega Health'
        },{
            'code': 'smallhealth',
            'name': 'Small Health'
        },{
            'code': 'bighealth',
            'name': 'Large Health'
        },{
            'code': 'invis',
            'name': 'Invisibility'
        },{
            'code': 'quad',
            'name': 'Quad Damage'
        },{
            'code': 'regen',
            'name': 'Regeneration'
        },{
            'code': 'medkit',
            'name': 'Medi Kit / Double Jump'
        },{
            'code': 'ptele',
            'name': 'Personal Teleporter'
        }
    ];

    const form = useForm({
        search: props.queries?.search ?? '',
        author: props.queries?.author ?? '',
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
    })
    
    const onFilterSubmit = () => {
        form.get(route('maps.filters'))
    }

    const resetFilters = () => {
        form.search = '';
        form.author = '';
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
        recordsSliderMin.value = 0;
        recordsSliderMax.value = 100;
        lengthSliderMin.value = 0;
        lengthSliderMax.value = 100;
    }

    const RemoveElement = (key, inc, index) => {
        form[key][inc].splice(index, 1);
    }

    // Exponential scaling for sliders
    const sliderToValue = (sliderPos, max) => {
        // Convert linear slider (0-100) to exponential value (0-max)
        const normalized = sliderPos / 100;
        return Math.round(Math.pow(normalized, 2.5) * max);
    }

    const valueToSlider = (value, max) => {
        // Convert exponential value back to linear slider position
        const normalized = value / max;
        return Math.round(Math.pow(normalized, 1/2.5) * 100);
    }

    const recordsSliderMin = ref(valueToSlider(form.records_count[0], 1000));
    const recordsSliderMax = ref(valueToSlider(form.records_count[1], 1000));
    const lengthSliderMin = ref(valueToSlider(form.average_length[0], 1000));
    const lengthSliderMax = ref(valueToSlider(form.average_length[1], 1000));

    const updateRecordsMin = () => {
        const value = sliderToValue(recordsSliderMin.value, 1000);
        form.records_count[0] = value;
        if (form.records_count[0] > form.records_count[1]) {
            form.records_count[0] = form.records_count[1];
            recordsSliderMin.value = valueToSlider(form.records_count[0], 1000);
        }
    }

    const updateRecordsMax = () => {
        const value = sliderToValue(recordsSliderMax.value, 1000);
        form.records_count[1] = value;
        if (form.records_count[1] < form.records_count[0]) {
            form.records_count[1] = form.records_count[0];
            recordsSliderMax.value = valueToSlider(form.records_count[1], 1000);
        }
    }

    const updateLengthMin = () => {
        const value = sliderToValue(lengthSliderMin.value, 1000);
        form.average_length[0] = value;
        if (form.average_length[0] > form.average_length[1]) {
            form.average_length[0] = form.average_length[1];
            lengthSliderMin.value = valueToSlider(form.average_length[0], 1000);
        }
    }

    const updateLengthMax = () => {
        const value = sliderToValue(lengthSliderMax.value, 1000);
        form.average_length[1] = value;
        if (form.average_length[1] < form.average_length[0]) {
            form.average_length[1] = form.average_length[0];
            lengthSliderMax.value = valueToSlider(form.average_length[1], 1000);
        }
    }

</script>

<template>
    <div class="backdrop-blur-xl bg-black/40 rounded-2xl border border-white/10 p-6 mb-8 shadow-2xl">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-500/20 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-white">Filter Maps</h2>
            </div>
            <button @click="resetFilters" class="flex items-center gap-2 px-3 py-1.5 text-sm bg-red-500/20 hover:bg-red-500/30 border border-red-500/30 rounded-lg text-red-400 hover:text-red-300 font-semibold transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
                Reset
            </button>
        </div>

        <!-- Search Section -->
        <div class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-white mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        Map Name
                    </label>
                    <TextInput
                        type="text"
                        v-model="form.search"
                        class="block w-full"
                        placeholder="Type to search..."
                        v-on:keyup.enter="onFilterSubmit"
                    />
                </div>

                <div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-white mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        Author
                    </label>
                    <TextInput
                        type="text"
                        v-model="form.author"
                        placeholder="Filter by author..."
                        v-on:keyup.enter="onFilterSubmit"
                        class="block w-full"
                    />
                </div>
            </div>
        </div>

        <!-- Player Filters -->
        <div class="mb-6 p-4 bg-white/5 rounded-xl border border-white/5">
            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
                Player Records
            </h3>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Has Record</label>
                        <span class="px-2 py-0.5 bg-green-500/20 text-green-400 text-xs font-bold rounded-full">{{ form.has_records.length }}</span>
                    </div>
                    <PlayerSelect
                        :options="profiles"
                        :multi="true"
                        v-model="form.has_records"
                        :values="form.has_records"
                    />
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-xs font-semibold text-gray-400 uppercase tracking-wide">No Record</label>
                        <span class="px-2 py-0.5 bg-red-500/20 text-red-400 text-xs font-bold rounded-full">{{ form.have_no_records.length }}</span>
                    </div>
                    <PlayerSelect
                        :options="profiles"
                        :multi="true"
                        v-model="form.have_no_records"
                        :values="form.have_no_records"
                    />
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="text-xs font-semibold text-gray-400 uppercase tracking-wide">World Record</label>
                        <span class="px-2 py-0.5 bg-yellow-500/20 text-yellow-400 text-xs font-bold rounded-full">{{ form.world_record.length }}</span>
                    </div>
                    <PlayerSelect
                        :options="profiles"
                        :multi="false"
                        v-model="form.world_record"
                        :values="form.world_record"
                    />
                </div>
            </div>
        </div>

        <!-- Map Content Filters -->
        <div class="mb-6 p-4 bg-white/5 rounded-xl border border-white/5">
            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-orange-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                </svg>
                Map Content
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs font-semibold text-gray-400 mb-2 block uppercase tracking-wide">Weapons</label>
                    <ItemsSelect
                        :options="weapons"
                        :multi="false"
                        v-model="form.weapons"
                        :values="form.weapons"
                        placeholder="Add weapons..."
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-400 mb-2 block uppercase tracking-wide">Functions</label>
                    <ItemsSelect
                        :options="functions"
                        :multi="false"
                        v-model="form.functions"
                        :values="form.functions"
                        placeholder="Add functions..."
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-400 mb-2 block uppercase tracking-wide">Items</label>
                    <ItemsSelect
                        :options="items"
                        :multi="false"
                        v-model="form.items"
                        :values="form.items"
                        placeholder="Add items..."
                    />
                </div>
            </div>
        </div>

        <!-- Quick Filters -->
        <div class="mb-6 p-4 bg-white/5 rounded-xl border border-white/5">
            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                </svg>
                Quick Filters
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-semibold text-gray-400 mb-2 block uppercase tracking-wide">Gametype</label>
                    <SpecialRadio
                        :options="types"
                        v-model="form.gametype"
                        :multi="true"
                        :values="form.gametype"
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-400 mb-2 block uppercase tracking-wide">Physics</label>
                    <SpecialRadio
                        :options="physics"
                        v-model="form.physics"
                        :values="form.physics"
                        :multi="true"
                    />
                </div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="mb-6 p-4 bg-white/5 rounded-xl border border-white/5">
            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                </svg>
                Advanced
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="modern-slider-container">
                    <label class="text-xs font-semibold text-gray-400 mb-4 block uppercase tracking-wide flex items-center justify-between">
                        <span>Number of Records</span>
                        <span class="px-3 py-1 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-400 font-mono text-sm">{{ form.records_count[0] }} - {{ form.records_count[1] === 1000 ? '∞' : form.records_count[1] }}</span>
                    </label>
                    <div class="range-slider-wrapper">
                        <input
                            type="range"
                            :min="0"
                            :max="100"
                            v-model="recordsSliderMin"
                            class="range-slider range-slider-min"
                            @input="updateRecordsMin"
                        />
                        <input
                            type="range"
                            :min="0"
                            :max="100"
                            v-model="recordsSliderMax"
                            class="range-slider range-slider-max"
                            @input="updateRecordsMax"
                        />
                    </div>
                </div>

                <div class="modern-slider-container">
                    <label class="text-xs font-semibold text-gray-400 mb-4 block uppercase tracking-wide flex items-center justify-between">
                        <span>Map Length (seconds)</span>
                        <span class="px-3 py-1 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-400 font-mono text-sm">{{ form.average_length[0] }} - {{ form.average_length[1] === 1000 ? '∞' : form.average_length[1] }}</span>
                    </label>
                    <div class="range-slider-wrapper">
                        <input
                            type="range"
                            :min="0"
                            :max="100"
                            v-model="lengthSliderMin"
                            class="range-slider range-slider-min"
                            @input="updateLengthMin"
                        />
                        <input
                            type="range"
                            :min="0"
                            :max="100"
                            v-model="lengthSliderMax"
                            class="range-slider range-slider-max"
                            @input="updateLengthMax"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Apply Button -->
        <div class="flex justify-center pt-2">
            <button
                :disabled="form.processing"
                @click="onFilterSubmit"
                class="relative px-12 py-3 bg-blue-600 hover:bg-blue-500 active:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed rounded-xl font-bold text-base text-white transition-all flex items-center gap-3 shadow-lg hover:shadow-blue-500/50 disabled:shadow-none group overflow-hidden"
            >
                <span class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></span>
                <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 animate-spin relative z-10">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 relative z-10">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <span class="relative z-10">{{ form.processing ? 'Searching...' : 'Search Maps' }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.modern-slider-container {
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.range-slider-wrapper {
    position: relative;
    height: 40px;
    display: flex;
    align-items: center;
}

.range-slider {
    position: absolute;
    width: 100%;
    height: 6px;
    -webkit-appearance: none;
    appearance: none;
    background: transparent;
    pointer-events: none;
    outline: none;
}

.range-slider::-webkit-slider-track {
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.range-slider::-moz-range-track {
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}

.range-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    pointer-events: all;
    width: 20px;
    height: 20px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4), 0 0 0 0 rgba(59, 130, 246, 0.7);
    transition: all 0.2s ease;
    position: relative;
}

.range-slider::-moz-range-thumb {
    pointer-events: all;
    width: 20px;
    height: 20px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4), 0 0 0 0 rgba(59, 130, 246, 0.7);
    transition: all 0.2s ease;
}

.range-slider::-webkit-slider-thumb:hover {
    transform: scale(1.15);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.6), 0 0 0 8px rgba(59, 130, 246, 0.15);
    border-color: rgba(255, 255, 255, 0.4);
}

.range-slider::-moz-range-thumb:hover {
    transform: scale(1.15);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.6), 0 0 0 8px rgba(59, 130, 246, 0.15);
    border-color: rgba(255, 255, 255, 0.4);
}

.range-slider::-webkit-slider-thumb:active {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.8), 0 0 0 12px rgba(59, 130, 246, 0.2);
}

.range-slider::-moz-range-thumb:active {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.8), 0 0 0 12px rgba(59, 130, 246, 0.2);
}

/* Active track between thumbs */
.range-slider-wrapper::before {
    content: '';
    position: absolute;
    height: 6px;
    background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 100%);
    border-radius: 3px;
    left: 0;
    pointer-events: none;
    box-shadow: 0 0 12px rgba(59, 130, 246, 0.5);
}

.range-slider-max {
    z-index: 4;
}

.range-slider-min {
    z-index: 5;
}
</style>