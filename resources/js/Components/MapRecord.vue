<script setup>
    import { Link } from '@inertiajs/vue3';
    import { computed } from 'vue';

    const props = defineProps({
        record: Object,
        cpmrecord: Object,
        vq3record: Object,
        physics: String,
        oldtop: {
            type: Boolean,
            default: false
        }
    });

    const bestrecordCountry = computed(() => {
        let country = props.record.user?.country ?? props.record.country;

        return (country == 'XX') ? '_404' : country;
    });

    const timeDiff =  computed(() => {
        if (! props.record.besttime === -1) {
            return null;
        }

        return Math.abs(props.record.besttime - props.record.time)
    });

    const getRoute = computed(() => {
        if (props.record.user) {
            return route('profile.index', props.record.user.id);
        }

        if (props.record.mdd_id) {
            return route('profile.mdd', props.record.mdd_id);
        }

        return '#'
    })

</script>

<template>
    <div>
        <div class="flex justify-between rounded-md px-2 py-1 items-center" :class="{'bg-blackop-30 opacity-70': record.oldtop}" :title="record.oldtop ? 'Old Top Record' : ''">
            <div class="mr-4 flex items-center">
                <div class="font-bold mr-3 text-white text-lg w-11" :class="{'text-orange-400': record.oldtop}">{{ record.rank }}</div>
                <img class="h-10 w-10 rounded-full object-cover" :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'" :alt="record.user?.name ?? record.name">
                
                <div class="ml-4">
                    <Link class="flex rounded-md" :href="getRoute">
                        <div class="flex justify-between items-center">
                            <div>
                                <img :src="`/images/flags/${bestrecordCountry}.png`" class="w-5 inline mr-2" onerror="this.src='/images/flags/_404.png'" :title="bestrecordCountry">
                                <Link class="font-bold text-white" :href="getRoute" v-html="q3tohtml(record.user?.name ?? record.name)"></Link>
                            </div>
                        </div>
                    </Link>
    
                    <div class="text-gray-400 text-xs mt-2" :title="record.date_set" :class="{'text-orange-400': record.oldtop}"> {{ timeSince(record.date_set) }} ago</div>
                </div>
            </div>

            <div class="flex items-center">
                <div class="text-right">
                    <div class="text-lg font-bold text-gray-300" :class="{'text-orange-400': record.oldtop}">{{  formatTime(record.time) }}</div>
                    <div class="text-xs text-red-500" v-if="timeDiff">- {{  formatTime(timeDiff) }}</div>
                    <div v-if="record.uploaded_demos && record.uploaded_demos.length > 0" class="text-xs text-green-400 mt-1">
                        <a :href="`/demos/${record.uploaded_demos[0].id}/download`" class="flex items-center hover:text-green-300" title="Download demo">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/>
                            </svg>
                            Demo
                        </a>
                    </div>
                </div>

                <div class="ml-5">
                    <div class="text-white rounded-full text-xs px-2 py-0.5 uppercase font-bold" :class="{'bg-green-700': record.gametype.includes('cpm'), 'bg-blue-600': !record.gametype.includes('cpm')}">
                        <div>{{ physics }}</div>
                    </div>

                    <!-- <div class="rounded-full text-xs px-2 py-0.5 uppercase font-bold border-2 border-gray-500 text-gray-500 mt-1">
                        <div>{{ record.mode }}</div>
                    </div> -->
                </div>
            </div>
        </div>
    
        <hr class="my-1 text-gray-700 border-gray-700 bg-gray-700">
    </div>
</template>