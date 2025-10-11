<script setup>
    import { ref } from 'vue';
    import { Head } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import MapCard from '@/Components/MapCard.vue';
    import MapFilters from '@/Components/MapFilters.vue';

    const props = defineProps({
        maps: Object,
        queries: Object,
        profiles: Array
    });

    const showFilters = ref(Object.keys(props.queries ?? {}).length > 0);
</script>

<template>
    <div class="min-h-screen">
        <Head title="Maps" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Maps</h1>
                        <span class="text-sm text-gray-400">{{ maps.total }} total</span>
                    </div>

                    <button
                        @click="showFilters = !showFilters"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm bg-white/5 hover:bg-white/10 border border-white/10 rounded-md font-medium text-white transition-all"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 transition-transform duration-300" :class="{'rotate-180': showFilters}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                        </svg>
                        Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-8 py-6">
            <Transition
                enter-active-class="transition-all duration-500 ease-out"
                leave-active-class="transition-all duration-300 ease-in"
                enter-from-class="opacity-0 max-h-0"
                enter-to-class="opacity-100 max-h-[2000px]"
                leave-from-class="opacity-100 max-h-[2000px]"
                leave-to-class="opacity-0 max-h-0"
            >
                <div v-if="showFilters" class="overflow-hidden">
                    <MapFilters :show="showFilters" :queries="queries ?? {}" :profiles="profiles" />
                </div>
            </Transition>

            <!-- Maps Grid - 5 columns for maximum density -->
            <div v-if="maps.total > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <MapCard v-for="map in maps.data" :map="map" :mapname="map.name" :key="map.id" />
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mb-3 opacity-40">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607z" />
                </svg>
                <p class="font-semibold">No maps found</p>
                <p class="text-sm">Adjust your filters</p>
            </div>

            <!-- Pagination -->
            <div v-if="maps.total > maps.per_page" class="flex justify-center mt-8">
                <Pagination :current_page="maps.current_page" :last_page="maps.last_page" :link="maps.first_page_url" />
            </div>
        </div>
    </div>
</template>