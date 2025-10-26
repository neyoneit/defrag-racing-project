<script setup>
    import { Head, router } from '@inertiajs/vue3';
    import Record from '@/Components/Record.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import { watchEffect, ref } from 'vue';

    const props = defineProps({
        vq3Records: Object,
        cpmRecords: Object
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
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <!-- Title -->
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-2">Latest Records</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                            <span class="text-sm font-semibold">{{ vq3Records.total + cpmRecords.total }} total records</span>
                        </div>
                    </div>

                    <!-- Gamemode Filter -->
                    <div class="flex flex-wrap gap-3">
                        <!-- All Modes -->
                        <div class="flex gap-2 bg-black/30 backdrop-blur-sm rounded-lg p-1 border border-white/10">
                            <button
                                @click="sortByMode('all')"
                                :class="[
                                    'px-4 py-2 rounded-md text-sm font-bold transition-all',
                                    mode === 'all'
                                        ? 'bg-gradient-to-r from-white/30 to-white/20 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-white/10'
                                ]">
                                ALL MODES
                            </button>
                        </div>

                        <!-- Run Mode -->
                        <div class="flex gap-2 bg-black/30 backdrop-blur-sm rounded-lg p-1 border border-green-500/20">
                            <button
                                @click="sortByMode('run')"
                                :class="[
                                    'px-4 py-2 rounded-md text-sm font-bold transition-all',
                                    mode === 'run'
                                        ? 'bg-gradient-to-r from-green-600/80 to-green-500/60 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-green-600/20'
                                ]">
                                RUN
                            </button>
                        </div>

                        <!-- CTF Modes -->
                        <div class="flex gap-1 bg-black/30 backdrop-blur-sm rounded-lg p-1 border border-red-500/20">
                            <button
                                @click="sortByMode('ctf')"
                                :class="[
                                    'px-4 py-2 rounded-md text-sm font-bold transition-all',
                                    mode === 'ctf'
                                        ? 'bg-gradient-to-r from-red-600/80 to-red-500/60 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-red-600/20'
                                ]">
                                ALL CTF
                            </button>
                            <div class="w-px bg-red-500/20"></div>
                            <button
                                v-for="i in 7" :key="'ctf' + i"
                                @click="sortByMode('ctf' + i)"
                                :class="[
                                    'px-3 py-2 rounded-md text-sm font-bold transition-all',
                                    mode === 'ctf' + i
                                        ? 'bg-gradient-to-r from-red-600/80 to-red-500/60 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white hover:bg-red-600/20'
                                ]">
                                {{ i }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Records List - Two Tables -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8" style="margin-top: -22rem;">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- VQ3 Records -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-blue-500/20">
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <img src="/images/modes/vq3-icon.svg" class="w-5 h-5" alt="VQ3" />
                            <h2 class="text-lg font-bold text-blue-400">VQ3 Records</h2>
                        </div>
                    </div>
                    <div class="px-4 py-2">
                        <Record v-for="record in vq3Records.data" :key="record.id" :record="record" />
                    </div>
                    <!-- VQ3 Pagination -->
                    <div v-if="vq3Records.total > vq3Records.per_page" class="border-t border-blue-500/20 bg-transparent p-4">
                        <Pagination :last_page="vq3Records.last_page" :current_page="vq3Records.current_page" :link="vq3Records.first_page_url" />
                    </div>
                </div>

                <!-- CPM Records -->
                <div class="backdrop-blur-xl bg-black/40 rounded-xl overflow-hidden shadow-2xl border border-purple-500/20">
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <img src="/images/modes/cpm-icon.svg" class="w-5 h-5" alt="CPM" />
                            <h2 class="text-lg font-bold text-purple-400">CPM Records</h2>
                        </div>
                    </div>
                    <div class="px-4 py-2">
                        <Record v-for="record in cpmRecords.data" :key="record.id" :record="record" />
                    </div>
                    <!-- CPM Pagination -->
                    <div v-if="cpmRecords.total > cpmRecords.per_page" class="border-t border-purple-500/20 bg-transparent p-4">
                        <Pagination :last_page="cpmRecords.last_page" :current_page="cpmRecords.current_page" :link="cpmRecords.first_page_url" />
                    </div>
                </div>
            </div>
        </div>

        <div class="h-20"></div>
    </div>
</template>
