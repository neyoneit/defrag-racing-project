<script setup>
    import { Head, router, usePage } from '@inertiajs/vue3';
    import MapCardLine from '@/Components/MapCardLine.vue';
    import MapRecord from '@/Components/MapRecord.vue';
    import MapRecordSmall from '@/Components/MapRecordSmall.vue';
    import MapCardLineSmall from '@/Components/MapCardLineSmall.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import ToggleButton from '@/Components/Basic/ToggleButton.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import { watchEffect, ref, onMounted, onUnmounted, computed } from 'vue';

    const props = defineProps({
        map: Object,
        cpmRecords: Object,
        vq3Records: Object,
        cpmOldRecords: Object,
        vq3OldRecords: Object,
        my_cpm_record: Object,
        my_vq3_record: Object,
        gametypeStats: Object,
        showOldtop: {
            type: Boolean,
            default: false
        }
    });

    const page = usePage();

    const order = ref('ASC');
    const column = ref('time');
    const gametype = ref('run');

    const gametypes = [
        'run',
        'ctf1',
        'ctf2',
        'ctf3',
        'ctf4',
        'ctf5',
        'ctf6',
        'ctf7'
    ];

    const screenWidth = ref(window.innerWidth);

    const sortByDate = () => {
        if (column.value === 'date_set') {
            order.value = (order.value == 'ASC') ? 'DESC' : 'ASC';
        }

        column.value = 'date_set';

        router.reload({
            data: {
                sort: column.value,
                order: order.value
            }
        })
    }

    const sortByTime = () => {
        // Toggle between sorting by time (fastest) and date (when set)
        if (column.value === 'time') {
            // Switch to date sorting
            column.value = 'date_set';
            order.value = 'DESC'; // Newest first by default
        } else {
            // Switch to time sorting
            column.value = 'time';
            order.value = 'ASC'; // Fastest first by default
        }

        router.reload({
            data: {
                sort: column.value,
                order: order.value
            }
        })
    }

    const sortByGametype = (gt) => {
        gametype.value = gt;

        router.reload({
            data: {
                gametype: gt,
                sort: 'time',
                order: 'ASC'
            }
        })
    }

    watchEffect(() => {
        order.value = route().params['order'] ?? 'ASC';
        column.value = route().params['sort'] ?? 'time';
        gametype.value = route().params['gametype'] ?? 'run';
    });

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    };

    const onChangeOldtop = (value) => {
        router.reload({
            data: {
                showOldtop: value
            }
        })
    }

    const getVq3Records = computed(() => {
        if (! props.showOldtop ) {
            return props.vq3Records
        }

        let oldtop_data = props.vq3OldRecords.data.map((item) => {
            item['oldtop'] = true

            return item
        });

        let result = {
            total: Math.max(props.vq3Records.total, props.vq3OldRecords.total),
            data: [...props.vq3Records.data, ...oldtop_data],
            first_page_url: props.vq3Records.first_page_url,
            current_page: props.vq3Records.current_page,
            last_page: (props.vq3Records.total > props.vq3OldRecords.total) ? props.vq3Records.last_page : props.vq3OldRecords.last_page,
            per_page: props.vq3Records.per_page
        }

        result.data.sort((a, b) => a.time - b.time)

        return result
    })

    const getCpmRecords = computed(() => {
        if (! props.showOldtop ) {
            return props.cpmRecords
        }

        let oldtop_data = props.cpmOldRecords.data.map((item) => {
            item['oldtop'] = true

            return item
        });

        let result = {
            total: Math.max(props.cpmRecords.total, props.cpmOldRecords.total),
            data: [...props.cpmRecords.data, ...oldtop_data],
            first_page_url: props.cpmRecords.first_page_url,
            current_page: props.cpmRecords.current_page,
            last_page: (props.cpmRecords.total > props.cpmOldRecords.total) ? props.cpmRecords.last_page : props.cpmOldRecords.last_page,
            per_page: props.cpmRecords.per_page
        }

        result.data.sort((a, b) => a.time - b.time)

        return result
    })

    onMounted(() => {
        window.addEventListener("resize", resizeScreen);
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
    });
</script>

<template>
    <div>
        <Head :title="map.name" />

        <!-- Extended Background with Map Image -->
        <div class="relative pb-10">
            <!-- Map thumbnail background - responsive height -->
            <div class="absolute top-0 left-0 right-0 h-[100vh] max-h-[1200px] pointer-events-none overflow-hidden">
                <img
                    v-if="map.thumbnail"
                    :src="`/storage/${map.thumbnail}`"
                    :alt="map.name"
                    class="w-full h-full object-cover object-top blur-sm"
                    @error="$event.target.style.display='none'"
                />
                <!-- Fade effect: gradient that fades the image to page background -->
                <div class="absolute inset-0" style="background: linear-gradient(to bottom, rgba(17, 24, 39, 0) 0%, rgba(17, 24, 39, 0) 70%, rgba(17, 24, 39, 0.7) 90%, rgba(17, 24, 39, 1) 100%);"></div>
            </div>
            <!-- Dark overlay for readability on top portion only -->
            <div class="absolute top-0 left-0 right-0 h-[600px] bg-gradient-to-b from-black/50 via-black/60 via-40% to-transparent pointer-events-none"></div>

            <!-- Hero Content (compact) -->
            <div class="relative max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pt-10 pb-6">
                <div class="w-full max-w-4xl mx-auto backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-6 shadow-2xl">
                    <!-- Map Title -->
                    <h1 class="text-4xl md:text-5xl font-bold text-white mb-2 text-center">{{ map.name }}</h1>
                    <p class="text-gray-300 text-center mb-6">
                        <span v-if="map.author">{{ map.author }}</span>
                        <span v-if="map.date_added"> • {{ new Date(map.date_added).getFullYear() }}</span>
                    </p>

                    <!-- Gametype Tabs -->
                    <div class="flex gap-2 flex-wrap justify-center mb-4">
                        <button
                            v-for="gt in gametypes"
                            :key="gt"
                            v-show="gametypeStats[gt] && gametypeStats[gt] > 0"
                            @click="sortByGametype(gt)"
                            :class="[
                                'px-4 py-2 rounded-lg font-bold text-sm transition-all backdrop-blur-sm',
                                gametype === gt
                                    ? 'bg-blue-500/80 text-white shadow-lg ring-2 ring-blue-400'
                                    : 'bg-white/10 text-gray-300 hover:bg-white/20'
                            ]"
                        >
                            <span class="uppercase">{{ gt }}</span>
                            <span class="ml-2 text-xs opacity-75">({{ gametypeStats[gt] }})</span>
                        </button>
                    </div>

                    <!-- Physics & Controls -->
                    <div class="flex flex-wrap gap-4 justify-center items-center text-sm">
                        <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2">
                            <span class="text-gray-300">⚡ VQ3:</span>
                            <span class="text-blue-400 font-bold">{{ vq3Records.total }}</span>
                        </div>
                        <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2">
                            <span class="text-gray-300">🚀 CPM:</span>
                            <span class="text-blue-400 font-bold">{{ cpmRecords.total }}</span>
                        </div>
                        <button
                            @click="sortByTime"
                            class="flex items-center gap-2 bg-white/10 backdrop-blur-sm hover:bg-white/20 rounded-lg px-4 py-2 text-gray-300 transition-all"
                            :title="column === 'time' ? 'Currently sorting by fastest time' : 'Currently sorting by date set'"
                        >
                            <span>{{ column === 'time' ? '⚡ Fastest' : '📅 Newest' }}</span>
                            <svg v-if="order === 'ASC'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                            </svg>
                        </button>
                        <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2">
                            <span class="text-gray-300">Old Top:</span>
                            <ToggleButton :isActive="showOldtop" @setIsActive="onChangeOldtop" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leaderboards Section (on top of background) -->
            <div class="relative max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="md:flex gap-4 justify-center">
                    <!-- VQ3 Leaderboard -->
                    <div class="flex-1 backdrop-blur-xl bg-grayop-700/80 rounded-xl overflow-hidden shadow-xl border border-gray-700/50">
                    <!-- VQ3 Header -->
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/20 border-b border-blue-500/30 p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                                ⚡ VQ3
                                <span class="text-sm text-gray-400 font-normal">({{ vq3Records.total }} records)</span>
                            </h3>
                            <div v-if="my_vq3_record" class="text-right">
                                <div class="text-xs text-gray-400">Your Best</div>
                                <div class="text-lg font-bold text-blue-400">
                                    {{ (my_vq3_record.time / 1000).toFixed(3) }}s
                                    <span class="text-sm text-gray-500">#{{ my_vq3_record.rank }}</span>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-500">No record</div>
                        </div>
                    </div>

                    <!-- VQ3 Records List -->
                    <div class="p-3">
                    <div v-if="getVq3Records.total > 0">
                        <div class="flex-grow" v-if="screenWidth > 640">
                            <MapRecord v-for="record in getVq3Records.data" physics="VQ3" :oldtop="record.oldtop" :key="record.id" :record="record" />
                        </div>

                        <div class="flex-grow" v-else>
                            <MapRecordSmall v-for="record in getVq3Records.data" :key="record.id" :record="record" />
                        </div>
                    </div>

                    <div v-else class="flex items-center justify-center mt-20 text-gray-500 text-lg">
                        <div>
                            <div class="flex items-center justify-center mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div>There are no VQ3 Records</div>
                        </div>
                    </div>

                    <div class="flex justify-center mt-4" v-if="getVq3Records.total > getVq3Records.per_page">
                        <Pagination pageName="vq3Page" :last_page="getVq3Records.last_page" :current_page="getVq3Records.current_page" :link="getVq3Records.first_page_url" />
                    </div>
                    </div>
                </div>

                <!-- CPM Leaderboard -->
                <div class="flex-1 backdrop-blur-xl bg-grayop-700/80 rounded-xl overflow-hidden shadow-xl border border-gray-700/50 mt-5 md:mt-0">
                    <!-- CPM Header -->
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/20 border-b border-purple-500/30 p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                                🚀 CPM
                                <span class="text-sm text-gray-400 font-normal">({{ cpmRecords.total }} records)</span>
                            </h3>
                            <div v-if="my_cpm_record" class="text-right">
                                <div class="text-xs text-gray-400">Your Best</div>
                                <div class="text-lg font-bold text-purple-400">
                                    {{ (my_cpm_record.time / 1000).toFixed(3) }}s
                                    <span class="text-sm text-gray-500">#{{ my_cpm_record.rank }}</span>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-500">No record</div>
                        </div>
                    </div>

                    <!-- CPM Records List -->
                    <div class="p-3">
                    <div v-if="getCpmRecords.total > 0">
                        <div class="flex-grow" v-if="screenWidth > 640">
                            <MapRecord v-for="record in getCpmRecords.data" physics="CPM" :key="record.id" :record="record" />
                        </div>

                        <div class="flex-grow" v-else>
                            <MapRecordSmall v-for="record in getCpmRecords.data" :key="record.id" :record="record" />
                        </div>
                    </div>

                    <div v-else class="flex items-center justify-center mt-20 text-gray-500 text-lg">
                        <div>
                            <div class="flex items-center justify-center mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div>There are no CPM Records</div>
                        </div>
                    </div>

                    <div class="flex justify-center mt-4" v-if="getCpmRecords.total > getCpmRecords.per_page">
                        <Pagination pageName="cpmPage" :last_page="getCpmRecords.last_page" :current_page="getCpmRecords.current_page" :link="getCpmRecords.first_page_url" />
                    </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</template>
