<script setup>
    import { ref, reactive, onMounted } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import moment from 'moment';

    const props = defineProps({
        round: Object,
        title: String
    });

    let seconds = ref(0);

    const showCountdown = ref(false);

    const countdownData = reactive({
        days: 0,
        hours: 0,
        minutes: 0,
        seconds: 0
    })

    const countdownTimer = ref(null);

    const initCountdown = () => {
        if (props.round.active) {
            seconds.value = props.round.seconds_till_finish ?? 0;
            countdown();
            showCountdown.value = true;

            setInterval(() => {
                seconds.value--;
                countdown();
            }, 1000);
        }
    }

    onMounted(() => {
        initCountdown();
    });

    const displayTime = ref('UTC')

    const getDate = (date) => {
        moment.tz.setDefault("UTC");
        const inputDate = moment(date, 'YYYY-MM-DD HH:mm:ss');

        const localDate = inputDate.clone().local();

        return displayTime.value === 'Local' ? localDate.format("DD MMM YYYY [ ] HH:mm ") : inputDate.format("DD MMM YYYY [ ] HH:mm");
    }

    function countdown() {
        let secs = seconds.value;

        if (seconds.value < 0) {
            countdownData.days = 0;
            countdownData.hours = 0;
            countdownData.minutes = 0;
            countdownData.seconds = 0;
            return;
        }

        const oneMinute = 60;
        const oneHour = oneMinute * 60;
        const oneDay = oneHour * 24;

        const days = Math.floor(secs / oneDay);
        secs %= oneDay;

        const hours = Math.floor(secs / oneHour);
        secs %= oneHour;

        const minutes = Math.floor(secs / oneMinute);
        secs %= oneMinute;

        countdownData.days = days;
        countdownData.hours = hours;
        countdownData.minutes = minutes;
        countdownData.seconds = secs;
    }

    const changeDisplayTime = () => {
        displayTime.value = displayTime.value === 'Local' ? 'UTC' : 'Local';
    }
</script>

<template>
    <div class="p-6">
        <!-- Round Header -->
        <div class="mb-6">
            <h2 class="text-3xl font-black text-white">{{ round.name }}</h2>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <!-- Map Image Section -->
            <div class="flex flex-col items-center">
                <div class="w-full">
                    <img :src="`/storage/${round.image}`" class="rounded-lg border-2 border-white/10 w-full">
                </div>
                <div class="mt-4 text-center">
                    <div class="font-bold text-lg text-white">{{ round.mapname }}</div>
                    <div class="text-sm text-gray-400 mt-1">
                        By <Link :href="route('maps.filters', { author: round.author })" class="text-blue-400 hover:text-blue-300 transition" v-html="q3tohtml(round.author)"></Link>
                    </div>
                </div>
            </div>

            <!-- Round Info Section -->
            <div class="space-y-4">
                <!-- Countdown Timer -->
                <div v-if="showCountdown" class="backdrop-blur-xl bg-blue-500/10 border border-blue-500/30 rounded-xl p-6 mb-4">
                    <div class="text-center mb-4">
                        <div class="font-bold text-sm text-blue-300 uppercase tracking-wider">Finishes In</div>
                    </div>
                    <div class="grid grid-cols-4 gap-4 text-center">
                        <div class="flex flex-col">
                            <span class="font-mono text-4xl font-black text-white">{{ countdownData.days }}</span>
                            <span class="text-xs text-gray-400 mt-1">DAYS</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-mono text-4xl font-black text-white">{{ countdownData.hours }}</span>
                            <span class="text-xs text-gray-400 mt-1">HOURS</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-mono text-4xl font-black text-white">{{ countdownData.minutes }}</span>
                            <span class="text-xs text-gray-400 mt-1">MIN</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-mono text-4xl font-black text-white">{{ countdownData.seconds }}</span>
                            <span class="text-xs text-gray-400 mt-1">SEC</span>
                        </div>
                    </div>
                </div>

                <!-- Round Details -->
                <div class="space-y-3">
                    <!-- Start Time -->
                    <div class="flex items-center justify-between py-3 border-b border-white/10">
                        <div class="font-medium text-gray-400">Start</div>
                        <div class="flex items-center gap-2 text-white">
                            <span>{{ getDate(round.start_date) }}</span>
                            <div class="h-3 w-3 rounded-full bg-blue-600"></div>
                            <span @click="changeDisplayTime" class="px-2 py-1 text-sm rounded-lg text-gray-400 hover:text-white hover:bg-white/5 cursor-pointer transition">
                                {{ displayTime }}
                            </span>
                        </div>
                    </div>

                    <!-- Finish Time -->
                    <div class="flex items-center justify-between py-3 border-b border-white/10">
                        <div class="font-medium text-gray-400">Finish</div>
                        <div class="flex items-center gap-2 text-white">
                            <span>{{ getDate(round.end_date) }}</span>
                            <div class="h-3 w-3 rounded-full bg-blue-600"></div>
                            <span @click="changeDisplayTime" class="px-2 py-1 text-sm rounded-lg text-gray-400 hover:text-white hover:bg-white/5 cursor-pointer transition">
                                {{ displayTime }}
                            </span>
                        </div>
                    </div>

                    <!-- Type -->
                    <div class="flex items-center justify-between py-3 border-b border-white/10">
                        <div class="font-medium text-gray-400">Type</div>
                        <div class="text-white">{{ round.category }} Map</div>
                    </div>

                    <!-- Download -->
                    <div class="flex items-start justify-between py-3 border-b border-white/10">
                        <div class="font-medium text-gray-400">Download</div>
                        <div class="flex flex-wrap gap-2 justify-end">
                            <div v-for="pk3 in round.maps" :key="pk3.id">
                                <div v-if="pk3.external" class="flex items-center gap-2">
                                    <Link :href="`/maps/${encodeURIComponent(pk3.name)}`" class="text-blue-400 hover:text-blue-300 transition">
                                        {{ pk3.name }}
                                    </Link>
                                    <a :download="pk3.download_name" :href="'https://dl.defrag.racing/downloads/maps/' + pk3.pk3.split('/').pop()" class="text-blue-400 hover:text-blue-300 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                    </a>
                                </div>
                                <a v-else target="_blank" :href="route('tournaments.maps.download', pk3.id)" class="text-blue-400 hover:text-blue-300 transition">
                                    {{ pk3.name }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="flex items-start justify-between py-3 border-b border-white/10">
                        <div class="font-medium text-gray-400">Details</div>
                        <div class="flex flex-wrap gap-2 justify-end">
                            <div v-if="round.weapons.length > 0" class="flex flex-wrap gap-1">
                                <div v-for="item in round.weapons.split(',')" :key="item" :class="`sprite-items sprite-${item} w-5 h-5`" :title="item"></div>
                            </div>
                            <div v-if="round.items.length > 0" class="flex flex-wrap gap-1">
                                <div v-for="item in round.items.split(',')" :key="item" :class="`sprite-items sprite-${item} w-5 h-5`" :title="item"></div>
                            </div>
                            <div v-if="round.functions.length > 0" class="flex flex-wrap gap-1">
                                <div v-for="item in round.functions.split(',')" :key="item" :class="`sprite-items sprite-${item} w-5 h-5`" :title="item"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <slot name="additional" />
            </div>
        </div>
    </div>
</template>

<style scoped>
    .tech-line-overview {
        background: linear-gradient(90deg,#ffffff42,#2f90ff,#ffffff42);
        box-shadow: 0 0 50px 4px #0a7cffb2;
        height: 1px;
        max-width: 100%;
    }
</style>