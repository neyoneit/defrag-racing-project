<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CreatorCard from '@/Components/Marketplace/CreatorCard.vue';

const props = defineProps({
    creators: Array,
    filters: Object,
});

const specialtyFilter = ref(props.filters?.specialty || '');
const sortBy = ref(props.filters?.sort || '');
const specialtyDropdownOpen = ref(false);
const sortDropdownOpen = ref(false);

const specialties = [
    { value: '', label: 'All Specialties' },
    { value: 'map', label: 'Mapping' },
    { value: 'player_model', label: 'Player Models' },
    { value: 'weapon_model', label: 'Weapon Models' },
    { value: 'shadow_model', label: 'Shadow Models' },
];

const sorts = [
    { value: '', label: 'Newest' },
    { value: 'rating', label: 'Highest Rated' },
];

const selectedSpecialtyLabel = () => specialties.find(s => s.value === specialtyFilter.value)?.label || 'All Specialties';
const selectedSortLabel = () => sorts.find(s => s.value === sortBy.value)?.label || 'Newest';

const applyFilters = () => {
    router.get(route('marketplace.creators'), {
        specialty: specialtyFilter.value || undefined,
        sort: sortBy.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const selectSpecialty = (value) => {
    specialtyFilter.value = value;
    specialtyDropdownOpen.value = false;
    applyFilters();
};

const selectSort = (value) => {
    sortBy.value = value;
    sortDropdownOpen.value = false;
    applyFilters();
};
</script>

<template>
    <div class="min-h-screen pb-20">
        <Head title="Creator Directory - Marketplace" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <Link :href="route('marketplace.index')" class="text-gray-400 hover:text-white text-sm transition mb-2 inline-flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                            Marketplace
                        </Link>
                        <h1 class="text-4xl md:text-5xl font-black text-gray-300/90 mb-2">Creator Directory</h1>
                        <p class="text-gray-400">Browse community creators available for commissions</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <!-- Filters -->
            <div class="mb-6 bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Specialty Dropdown -->
                    <div class="relative">
                        <button
                            @click="specialtyDropdownOpen = !specialtyDropdownOpen"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white text-left flex items-center justify-between hover:border-white/20 transition-colors focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                        >
                            <span>{{ selectedSpecialtyLabel() }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="specialtyDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                        <div v-if="specialtyDropdownOpen" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-hidden z-50 shadow-2xl">
                            <button
                                v-for="s in specialties"
                                :key="s.value"
                                @click="selectSpecialty(s.value)"
                                :class="specialtyFilter === s.value ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'"
                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                            >
                                {{ s.label }}
                            </button>
                        </div>
                        <div v-if="specialtyDropdownOpen" @click="specialtyDropdownOpen = false" class="fixed inset-0 z-40"></div>
                    </div>

                    <!-- Sort Dropdown -->
                    <div class="relative">
                        <button
                            @click="sortDropdownOpen = !sortDropdownOpen"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2 text-white text-left flex items-center justify-between hover:border-white/20 transition-colors focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                        >
                            <span>{{ selectedSortLabel() }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="sortDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                        <div v-if="sortDropdownOpen" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-hidden z-50 shadow-2xl">
                            <button
                                v-for="s in sorts"
                                :key="s.value"
                                @click="selectSort(s.value)"
                                :class="sortBy === s.value ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'"
                                class="w-full px-4 py-2 text-left text-sm transition-colors"
                            >
                                {{ s.label }}
                            </button>
                        </div>
                        <div v-if="sortDropdownOpen" @click="sortDropdownOpen = false" class="fixed inset-0 z-40"></div>
                    </div>
                </div>
            </div>

            <!-- Creator Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <CreatorCard
                    v-for="creator in creators"
                    :key="creator.id"
                    :creator="creator"
                />
            </div>

            <!-- Empty State -->
            <div v-if="creators.length === 0" class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-600 mx-auto mb-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                <h3 class="text-xl font-bold text-white mb-2">No Creators Listed Yet</h3>
                <p class="text-gray-400">Creators can list themselves in their profile settings.</p>
            </div>
        </div>
    </div>
</template>
