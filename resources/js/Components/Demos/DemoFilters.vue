<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { t } from '@/utils/i18n';
import { countryList } from '@/Components/stubs/countries';

const props = defineProps({
    filters: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    physicsOptions: { type: Array, default: () => [] },
    // { codes: [...], other: n, none: n }
    countries: { type: Object, default: () => ({ codes: [], other: 0, none: 0 }) },
    isAdmin: { type: Boolean, default: false },
    list: { type: String, default: 'all' },
});

const emit = defineEmits(['change']);

const set = (patch) => emit('change', patch);

// --- Tab rows -------------------------------------------------------------

const gametypeTabs = computed(() => [
    { value: 'all', label: t('All'), count: props.counts.all, active: 'bg-blue-600 text-white' },
    { value: 'online', label: t('Online'), count: props.counts.online, active: 'bg-green-600 text-white' },
    { value: 'offline', label: t('Offline'), count: props.counts.offline, active: 'bg-purple-600 text-white' },
]);

// The public list never holds a demo that is still being worked on, so those
// statuses are offered on your own list only. Switching tabs drops the value
// rather than handing you an empty table.
const statusTabs = computed(() => {
    const shared = [
        { value: 'all', label: t('All Status'), count: null },
        { value: 'assigned', label: t('Assigned'), count: props.counts.assigned },
        { value: 'processed', label: t('Processed'), count: props.counts.processed },
        { value: 'failed-validity', label: t('Invalid'), count: props.counts.failed_validity },
        { value: 'failed', label: t('Failed'), count: props.counts.failed },
    ];

    if (props.list !== 'mine') return shared;

    return [
        ...shared,
        { value: 'uploaded', label: t('Waiting'), count: props.counts.uploaded },
        { value: 'unsupported-version', label: t('Unsupported'), count: props.counts.unsupported_version },
    ];
});

// --- Free text with a list to pick from -----------------------------------

const makePicker = (routeName) => {
    const query = ref('');
    const options = ref([]);
    const open = ref(false);
    let timer = null;
    let skip = false;

    const search = async (value) => {
        if (value.trim().length < 2) {
            options.value = [];
            open.value = false;
            return;
        }
        try {
            const { data } = await axios.get(route(routeName), { params: { q: value.trim() } });
            options.value = data;
            open.value = data.length > 0;
        } catch {
            options.value = [];
            open.value = false;
        }
    };

    watch(query, (value) => {
        if (skip) { skip = false; return; }
        clearTimeout(timer);
        timer = setTimeout(() => search(value), 250);
    });

    const take = (value) => {
        skip = true;
        query.value = value;
        options.value = [];
        open.value = false;
    };

    return { query, options, open, take };
};

const mapPicker = makePicker('demos.search-demo-maps');
const playerPicker = makePicker('demos.search-demo-players');
const uploaderPicker = makePicker('demos.search-uploaders');

// take(), not a plain assignment. Writing to the box triggers its own search,
// so a saved link that already carries a map would fire a request and pop the
// suggestion list open before anybody had typed anything.
mapPicker.take(props.filters.map || '');
uploaderPicker.take(props.filters.uploaded_by || '');

const chooseMap = (value) => {
    mapPicker.take(value);
    set({ map: value });
};

const chooseUploader = (value) => {
    uploaderPicker.take(value);
    set({ uploaded_by: value });
};

const players = computed(() => props.filters.players || []);

const addPlayer = (name) => {
    playerPicker.take('');
    playerPicker.query.value = '';
    if (!players.value.includes(name)) {
        set({ players: [...players.value, name] });
    }
};

const removePlayer = (name) => set({ players: players.value.filter((p) => p !== name) });

// --- Physics --------------------------------------------------------------

const physicsOpen = ref(false);
const chosenPhysics = computed(() => props.filters.physics || []);

const togglePhysics = (value) => {
    const next = chosenPhysics.value.includes(value)
        ? chosenPhysics.value.filter((p) => p !== value)
        : [...chosenPhysics.value, value];
    set({ physics: next });
};

// --- Times ----------------------------------------------------------------

// Seconds in the box, milliseconds in the database. A person filtering runs
// thinks in seconds and nobody wants to type five zeroes.
const timeMin = ref(props.filters.time_min != null ? props.filters.time_min / 1000 : '');
const timeMax = ref(props.filters.time_max != null ? props.filters.time_max / 1000 : '');

const applyTimes = () => set({
    time_min: timeMin.value === '' ? null : Number(timeMin.value),
    time_max: timeMax.value === '' ? null : Number(timeMax.value),
});

// --- Country --------------------------------------------------------------

// The server decides which codes are real countries, so that the list and the
// filter agree. Everything it left out is still reachable through the two
// buckets at the bottom: one for a country that is not a country, one for a
// demo that names none.
const COUNTRY_OTHER = '__other__';
const COUNTRY_NONE = '__none__';

const countryOpen = ref(false);
const countrySearch = ref('');

const countryOptions = computed(() => {
    const names = countryList();
    return (props.countries.codes || [])
        .map((code) => ({ code, name: names[code] || code }))
        .sort((a, b) => a.name.localeCompare(b.name));
});

const countryBuckets = computed(() => [
    { code: COUNTRY_OTHER, name: t('Other country'), count: props.countries.other || 0 },
    { code: COUNTRY_NONE, name: t('No country'), count: props.countries.none || 0 },
].filter((bucket) => bucket.count > 0));

const shownCountries = computed(() => {
    const needle = countrySearch.value.trim().toLowerCase();
    if (!needle) return countryOptions.value;
    return countryOptions.value.filter(
        (c) => c.name.toLowerCase().includes(needle) || c.code.toLowerCase().includes(needle)
    );
});

const chosenCountryName = computed(() => {
    const chosen = props.filters.country;
    if (!chosen) return '';
    return countryOptions.value.find((c) => c.code === chosen)?.name
        || countryBuckets.value.find((b) => b.code === chosen)?.name
        || chosen;
});

const isRealCountry = computed(
    () => !!props.filters.country && props.filters.country !== COUNTRY_OTHER && props.filters.country !== COUNTRY_NONE
);

const chooseCountry = (code) => {
    countryOpen.value = false;
    countrySearch.value = '';
    set({ country: code });
};

// --- Rank -----------------------------------------------------------------

const rankMin = ref(props.filters.rank_min ?? '');
const rankMax = ref(props.filters.rank_max ?? '');

const applyRanks = () => set({
    rank_min: rankMin.value === '' ? null : Number(rankMin.value),
    rank_max: rankMax.value === '' ? null : Number(rankMax.value),
});

// --- Panel state ----------------------------------------------------------

const activeCount = computed(() => {
    const f = props.filters;
    let n = 0;
    if (f.search) n++;
    if (f.map) n++;
    if ((f.players || []).length) n++;
    if ((f.physics || []).length) n++;
    if (f.time_min != null || f.time_max != null) n++;
    if (f.country) n++;
    if (f.date_from || f.date_to) n++;
    if (f.uploaded_by) n++;
    if (f.rank_min != null || f.rank_max != null) n++;
    if (f.confidence) n++;
    if (f.other_user_matches) n++;
    return n;
});

const clearAll = () => {
    mapPicker.take('');
    playerPicker.take('');
    uploaderPicker.take('');
    timeMin.value = '';
    timeMax.value = '';
    rankMin.value = '';
    rankMax.value = '';
    set({
        search: '', map: '', players: [], physics: [],
        time_min: null, time_max: null, country: '',
        date_from: '', date_to: '', uploaded_by: '',
        rank_min: null, rank_max: null,
        confidence: '', other_user_matches: false,
    });
};

const closeDropdowns = (event) => {
    if (!event.target.closest('[data-demo-filter-dropdown]')) {
        physicsOpen.value = false;
        countryOpen.value = false;
        mapPicker.open.value = false;
        playerPicker.open.value = false;
        uploaderPicker.open.value = false;
    }
};

onMounted(() => document.addEventListener('click', closeDropdowns));
onUnmounted(() => document.removeEventListener('click', closeDropdowns));

const fieldClass = 'px-2.5 py-1.5 bg-gray-700/50 border border-gray-600/50 rounded-lg text-xs text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent';

// A date box only opened its calendar if you hit the small icon, and the icon
// itself was almost invisible on a dark background. This opens it from
// anywhere in the box. showPicker throws if the browser will not allow it
// without a gesture, and a click is a gesture, so the guard is for the
// browsers that do not have the method at all.
const openDatePicker = (event) => {
    try {
        event.target.showPicker?.();
    } catch {
        // The box still types, which is the fallback.
    }
};
</script>

<template>
    <!-- relative z-50 on the panel itself. The tables below carry
         backdrop-blur, which makes each of them a stacking context of its own,
         so a z-index inside this panel could not reach over them. Raising the
         panel raises everything it contains with it. -->
    <div class="relative z-50 bg-black/40 backdrop-blur-sm rounded-xl p-3 mb-3 shadow-2xl border border-white/5 space-y-2">
        <!-- Online / offline, then how far the demo got. -->
        <div class="flex flex-wrap gap-1.5 items-center">
            <button
                v-for="tab in gametypeTabs"
                :key="tab.value"
                type="button"
                @click="set({ tab: tab.value })"
                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                :class="filters.tab === tab.value ? tab.active : 'bg-gray-700/50 text-gray-300 hover:bg-gray-700 border border-gray-600/50'"
            >
                {{ tab.label }}
                <span v-if="tab.count != null" class="opacity-75">({{ tab.count.toLocaleString() }})</span>
            </button>

            <span class="border-l border-gray-600/50 mx-1 self-stretch"></span>

            <button
                v-for="tab in statusTabs"
                :key="tab.value"
                type="button"
                @click="set({ status: tab.value })"
                class="px-2.5 py-1 rounded text-xs font-medium transition-all"
                :class="filters.status === tab.value ? 'bg-gray-600 text-white' : 'bg-gray-700/30 text-gray-400 hover:bg-gray-700/50 border border-gray-600/30'"
            >
                {{ tab.label }}
                <span v-if="tab.count != null" class="opacity-75">({{ tab.count.toLocaleString() }})</span>
            </button>

            <button
                v-if="activeCount > 0"
                type="button"
                @click="clearAll"
                class="ml-auto px-2.5 py-1 rounded text-xs font-medium bg-gray-700/50 border border-gray-600/50 text-gray-300 hover:bg-gray-700 transition-colors inline-flex items-center gap-1.5"
            >
                {{ $t('Clear filters') }}
                <span class="bg-white/20 rounded px-1">{{ activeCount }}</span>
            </button>
        </div>

        <!-- Always on screen. It wraps on its own at any window width. -->
        <div class="flex flex-wrap gap-2 items-start pt-1">
            <!-- Map -->
            <div class="relative" data-demo-filter-dropdown>
                <input
                    v-model="mapPicker.query.value"
                    type="text"
                    :placeholder="$t('Map...')"
                    class="w-36"
                    :class="fieldClass"
                    @keyup.enter="chooseMap(mapPicker.query.value)"
                />
                <div v-if="mapPicker.open.value" class="absolute z-50 mt-1 w-64 max-h-72 overflow-y-auto bg-gray-800 border border-gray-600 rounded-lg shadow-2xl">
                    <button
                        v-for="option in mapPicker.options.value"
                        :key="option.name"
                        type="button"
                        @click="chooseMap(option.name)"
                        class="flex items-center gap-2 w-full text-left px-2 py-1.5 text-xs text-gray-200 hover:bg-gray-700"
                    >
                        <img
                            v-if="option.thumbnail"
                            :src="'/storage/' + option.thumbnail"
                            alt=""
                            loading="lazy"
                            class="w-10 h-7 object-cover rounded flex-shrink-0 bg-gray-900"
                        />
                        <span v-else class="w-10 h-7 rounded flex-shrink-0 bg-gray-900/80 border border-gray-700"></span>
                        <span class="truncate">{{ option.name }}</span>
                    </button>
                </div>
            </div>

            <!-- Players. Several at once, which is the whole point. -->
            <div class="relative" data-demo-filter-dropdown>
                <div class="flex flex-wrap gap-1 items-center">
                    <span
                        v-for="name in players"
                        :key="name"
                        class="inline-flex items-center gap-1 bg-blue-600/30 border border-blue-500/40 rounded px-1.5 py-0.5 text-xs text-blue-100"
                    >
                        {{ name }}
                        <button type="button" @click="removePlayer(name)" class="hover:text-white">&times;</button>
                    </span>
                    <input
                        v-model="playerPicker.query.value"
                        type="text"
                        :placeholder="players.length ? $t('and...') : $t('Player...')"
                        class="w-32"
                        :class="fieldClass"
                    />
                </div>
                <div v-if="playerPicker.open.value" class="absolute z-50 mt-1 w-56 max-h-60 overflow-y-auto bg-gray-800 border border-gray-600 rounded-lg shadow-2xl">
                    <button
                        v-for="name in playerPicker.options.value"
                        :key="name"
                        type="button"
                        @click="addPlayer(name)"
                        class="block w-full text-left px-3 py-1.5 text-xs text-gray-200 hover:bg-gray-700"
                    >{{ name }}</button>
                </div>
            </div>

            <!-- Physics -->
            <div class="relative" data-demo-filter-dropdown>
                <button
                    type="button"
                    @click="physicsOpen = !physicsOpen"
                    class="w-32 text-left"
                    :class="fieldClass"
                >
                    {{ chosenPhysics.length ? chosenPhysics.join(', ') : $t('Physics') }}
                </button>
                <div v-if="physicsOpen" class="absolute z-50 mt-1 w-40 max-h-60 overflow-y-auto bg-gray-800 border border-gray-600 rounded-lg shadow-2xl p-1">
                    <label
                        v-for="value in physicsOptions"
                        :key="value"
                        class="flex items-center gap-2 px-2 py-1 text-xs text-gray-200 hover:bg-gray-700 rounded cursor-pointer"
                    >
                        <input type="checkbox" :checked="chosenPhysics.includes(value)" @change="togglePhysics(value)" class="rounded bg-gray-700 border-gray-600" />
                        {{ value }}
                    </label>
                </div>
            </div>

            <!-- Run time, in seconds -->
            <div class="flex items-center gap-1">
                <input v-model="timeMin" type="number" min="0" step="0.1" :placeholder="$t('from s')" class="w-20" :class="fieldClass" @change="applyTimes" @keyup.enter="applyTimes" />
                <span class="text-gray-500 text-xs">-</span>
                <input v-model="timeMax" type="number" min="0" step="0.1" :placeholder="$t('to s')" class="w-20" :class="fieldClass" @change="applyTimes" @keyup.enter="applyTimes" />
            </div>

            <!-- Rank on the map. Only a demo tied to a record has one, so
                 this narrows to those; the label says so. -->
            <div class="flex items-center gap-1" :title="$t('Only demos linked to a record have a rank.')">
                <span class="text-xs text-gray-500">#</span>
                <input v-model="rankMin" type="number" min="1" step="1" :placeholder="$t('rank from')" class="w-24" :class="fieldClass" @change="applyRanks" @keyup.enter="applyRanks" />
                <span class="text-gray-500 text-xs">-</span>
                <input v-model="rankMax" type="number" min="1" step="1" :placeholder="$t('rank to')" class="w-24" :class="fieldClass" @change="applyRanks" @keyup.enter="applyRanks" />
            </div>

            <!-- Country -->
            <div class="relative" data-demo-filter-dropdown>
                <button
                    type="button"
                    @click="countryOpen = !countryOpen"
                    class="w-40 text-left flex items-center gap-2"
                    :class="fieldClass"
                >
                    <img
                        v-if="isRealCountry"
                        :src="`/images/flags/${filters.country}.png`"
                        alt=""
                        class="w-4 h-3 rounded-sm flex-shrink-0"
                        onerror="this.style.visibility='hidden'"
                    />
                    <span class="truncate">{{ chosenCountryName || $t('Country') }}</span>
                    <svg class="w-3 h-3 ml-auto flex-shrink-0 text-gray-400 transition-transform" :class="countryOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div v-if="countryOpen" class="absolute z-50 mt-1 w-56 bg-gray-800 border border-gray-600 rounded-lg shadow-2xl overflow-hidden">
                    <div class="p-1.5 border-b border-gray-700">
                        <input
                            v-model="countrySearch"
                            type="text"
                            :placeholder="$t('Search...')"
                            class="w-full"
                            :class="fieldClass"
                            @click.stop
                        />
                    </div>
                    <div class="max-h-60 overflow-y-auto">
                        <button
                            type="button"
                            @click="chooseCountry('')"
                            class="flex items-center gap-2 w-full text-left px-2.5 py-1.5 text-xs hover:bg-gray-700"
                            :class="filters.country ? 'text-gray-400' : 'text-blue-300'"
                        >
                            <span class="w-4 h-3 flex-shrink-0"></span>
                            {{ $t('Any country') }}
                        </button>
                        <button
                            v-for="option in shownCountries"
                            :key="option.code"
                            type="button"
                            @click="chooseCountry(option.code)"
                            class="flex items-center gap-2 w-full text-left px-2.5 py-1.5 text-xs hover:bg-gray-700"
                            :class="filters.country === option.code ? 'bg-blue-600/30 text-blue-100' : 'text-gray-200'"
                        >
                            <img
                                :src="`/images/flags/${option.code}.png`"
                                alt=""
                                loading="lazy"
                                class="w-4 h-3 rounded-sm flex-shrink-0"
                                onerror="this.style.visibility='hidden'"
                            />
                            <span class="truncate">{{ option.name }}</span>
                        </button>

                        <!-- Everything the list above cannot name, still
                             reachable rather than quietly dropped. -->
                        <div v-if="!countrySearch && countryBuckets.length" class="border-t border-gray-700 mt-1 pt-1">
                            <button
                                v-for="bucket in countryBuckets"
                                :key="bucket.code"
                                type="button"
                                @click="chooseCountry(bucket.code)"
                                class="flex items-center gap-2 w-full text-left px-2.5 py-1.5 text-xs hover:bg-gray-700"
                                :class="filters.country === bucket.code ? 'bg-blue-600/30 text-blue-100' : 'text-gray-300'"
                            >
                                <span class="w-4 h-3 flex-shrink-0 rounded-sm bg-gray-700/60"></span>
                                <span class="truncate">{{ bucket.name }}</span>
                                <span class="ml-auto text-gray-500">{{ bucket.count.toLocaleString() }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- When it was uploaded -->
            <div class="flex items-center gap-1">
                <input
                    :value="filters.date_from"
                    type="date"
                    class="w-36 cursor-pointer [color-scheme:dark]"
                    :class="fieldClass"
                    @click="openDatePicker"
                    @change="set({ date_from: $event.target.value })"
                />
                <span class="text-gray-500 text-xs">-</span>
                <input
                    :value="filters.date_to"
                    type="date"
                    class="w-36 cursor-pointer [color-scheme:dark]"
                    :class="fieldClass"
                    @click="openDatePicker"
                    @change="set({ date_to: $event.target.value })"
                />
            </div>

            <!-- Filename. It takes whatever room is left on its line, because
                 a demo filename is long and a fixed box showed a third of it.
                 min-w keeps it usable when there is little room left, and it
                 wraps to its own line rather than squashing. -->
            <input
                :value="filters.search"
                type="text"
                :placeholder="$t('Filename...')"
                class="flex-1 min-w-[16rem]"
                :class="fieldClass"
                @keyup.enter="set({ search: $event.target.value })"
                @change="set({ search: $event.target.value })"
            />

            <!-- Who sent it in -->
            <div class="relative" data-demo-filter-dropdown>
                <input
                    v-model="uploaderPicker.query.value"
                    type="text"
                    :placeholder="$t('Uploaded by...')"
                    class="w-36"
                    :class="fieldClass"
                    @keyup.enter="chooseUploader(uploaderPicker.query.value)"
                />
                <div v-if="uploaderPicker.open.value" class="absolute z-50 mt-1 w-56 max-h-60 overflow-y-auto bg-gray-800 border border-gray-600 rounded-lg shadow-2xl">
                    <button
                        v-for="name in uploaderPicker.options.value"
                        :key="name"
                        type="button"
                        @click="chooseUploader(name)"
                        class="block w-full text-left px-3 py-1.5 text-xs text-gray-200 hover:bg-gray-700 truncate"
                    >{{ name }}</button>
                </div>
            </div>

            <!-- Staff, and only on your own list. -->
            <template v-if="isAdmin && list === 'mine'">
                <select :value="filters.confidence" @change="set({ confidence: $event.target.value })" class="w-36" :class="fieldClass">
                    <option value="">{{ $t('Name confidence') }}</option>
                    <option value="90-99">90-99%</option>
                    <option value="80-89">80-89%</option>
                    <option value="70-79">70-79%</option>
                    <option value="60-69">60-69%</option>
                    <option value="50-59">50-59%</option>
                    <option value="below-50">{{ $t('below 50%') }}</option>
                </select>
                <label class="flex items-center gap-1.5 text-xs text-gray-300 px-2 py-1.5">
                    <input type="checkbox" :checked="filters.other_user_matches" @change="set({ other_user_matches: $event.target.checked })" class="rounded bg-gray-700 border-gray-600" />
                    {{ $t('100% match on another account') }}
                </label>
            </template>
        </div>
    </div>
</template>
