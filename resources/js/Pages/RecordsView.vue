<script setup>
    import { Head, router } from '@inertiajs/vue3';
    import Record from '@/Components/Record.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import { watchEffect, ref } from 'vue';

    const props = defineProps({
        records: Object
    });

    const physics = ref('all');
    const mode = ref('all');

    const sortByPhysics = (newPhysics) => {
        physics.value = newPhysics

        router.reload({
            data: {
                physics: physics.value,
                mode: mode.value
            }
        })
    }

    const sortByMode = (newMode) => {
        mode.value = newMode

        router.reload({
            data: {
                physics: physics.value,
                mode: mode.value
            }
        })
    }

    watchEffect(() => {
        const url = new URL(window.location.href);
        physics.value = url.searchParams.get('physics') ?? 'all';
        mode.value = url.searchParams.get('mode') ?? 'all';
    });
</script>

<template>
    <div class="min-h-screen">
        <Head title="Records" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-20">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <!-- Title -->
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Latest Records</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                            <span class="text-sm font-semibold">{{ records.total }} total records</span>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div class="flex flex-wrap gap-3">
                        <!-- Gamemode Filter -->
                        <div class="flex gap-2 bg-black/20 backdrop-blur-sm rounded-lg p-1 border border-white/5">
                            <button
                                @click="sortByMode('all')"
                                :class="[
                                    'px-4 py-2 rounded-md text-sm font-bold transition-all',
                                    mode === 'all'
                                        ? 'bg-white/20 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-white/10'
                                ]">
                                ALL
                            </button>
                            <button
                                @click="sortByMode('run')"
                                :class="[
                                    'px-4 py-2 rounded-md text-sm font-bold transition-all',
                                    mode === 'run'
                                        ? 'bg-green-600/80 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-white/10'
                                ]">
                                RUN
                            </button>
                            <button
                                @click="sortByMode('ctf')"
                                :class="[
                                    'px-4 py-2 rounded-md text-sm font-bold transition-all',
                                    mode === 'ctf'
                                        ? 'bg-red-600/80 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-white/10'
                                ]">
                                CTF
                            </button>
                        </div>

                        <!-- Physics Filter -->
                        <div class="flex gap-2 bg-black/20 backdrop-blur-sm rounded-lg p-1 border border-white/5">
                            <button
                                @click="sortByPhysics('all')"
                                :class="[
                                    'px-4 py-2 rounded-md text-sm font-bold transition-all',
                                    physics === 'all'
                                        ? 'bg-white/20 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-white/10'
                                ]">
                                ALL
                            </button>
                            <button
                                @click="sortByPhysics('vq3')"
                                :class="[
                                    'px-4 py-2 rounded-md text-sm font-bold transition-all flex items-center gap-1.5',
                                    physics === 'vq3'
                                        ? 'bg-blue-600/80 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-white/10'
                                ]">
                                <img src="/images/modes/vq3-icon.svg" class="w-4 h-4" alt="VQ3" />
                                VQ3
                            </button>
                            <button
                                @click="sortByPhysics('cpm')"
                                :class="[
                                    'px-4 py-2 rounded-md text-sm font-bold transition-all flex items-center gap-1.5',
                                    physics === 'cpm'
                                        ? 'bg-purple-600/80 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-white/10'
                                ]">
                                <img src="/images/modes/cpm-icon.svg" class="w-4 h-4" alt="CPM" />
                                CPM
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Records List -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-10">
            <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-white/5">
                <div class="px-4 py-2">
                    <Record v-for="record in records.data" :key="record.id" :record="record" />
                </div>

                <!-- Pagination -->
                <div v-if="records.total > records.per_page" class="border-t border-white/5 bg-transparent p-6">
                    <Pagination :last_page="records.last_page" :current_page="records.current_page" :link="records.first_page_url" />
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>
