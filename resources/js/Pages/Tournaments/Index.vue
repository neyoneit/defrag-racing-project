<script setup>
    import { Head, Link } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import TournamentCard from '@/Components/TournamentCard.vue';
    import Tabs from '@/Components/Tabs.vue';

    const props = defineProps({
        tournaments: Object,
        activeTournaments: Object,
        upcomingTournaments: Object,
        pastTournaments: Object,
        records: Number
    });

    const filteredTournaments = ref([]);

    const searchInput = ref('');

    const tabs = [
        {
            name: 'Active Tournaments',
            label: 'Active Tournaments',
            link: false,
        },
        {
            name: 'Upcoming Tournaments',
            label: 'Upcoming Tournaments',
            link: false,
        },
        {
            name: 'Historical Tournaments',
            label: 'Historical Tournaments',
            link: false,
        },
        {
            name: 'Search Tournaments',
            label: 'Search Tournaments',
            link: false,
        }
    ];

    const activeTab = ref('Active Tournaments');

    const toggleTab = (tab) => {
        activeTab.value = tab;
    };

    const onSearchTournament = () => {
        if (searchInput.value.length > 0) {
            filteredTournaments.value = props.tournaments.filter((tournament) => {
                return tournament.name.toLowerCase().includes(searchInput.value.toLowerCase());
            });
        } else {
            filteredTournaments.value = [];
        }
    };
</script>

<template>
    <div class="min-h-screen">
        <Head title="Tournaments" />

        <!-- Top Shadow Gradient -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-16"></div>

        <!-- Modern Tournaments Container -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-4xl font-black text-white mb-2 flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-yellow-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                        </svg>
                        Tournaments
                    </h1>
                    <p class="text-gray-400">Compete in defrag tournaments and climb the rankings</p>
                </div>

                <!-- Create Tournament Button -->
                <Link
                    v-if="records >= 50"
                    :href="route('tournaments.create')"
                    class="flex items-center gap-2 px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-blue-500/20"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create Tournament
                </Link>

                <div
                    v-else
                    class="flex items-center gap-2 px-4 py-3 bg-gray-700/50 text-gray-500 font-bold rounded-lg cursor-not-allowed"
                    title="You need at least 50 records to create tournaments"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create Tournament
                </div>
            </div>

            <!-- Warning Message -->
            <div v-if="records < 50" class="backdrop-blur-xl bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 mb-8">
                <div class="flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-yellow-400 shrink-0 mt-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div class="flex-1">
                        <p class="text-yellow-400 font-bold mb-1">Tournament Creation Restricted</p>
                        <p class="text-yellow-300 text-sm">
                            You need at least 50 records to create tournaments. You currently have {{ records }} records.
                            <span v-if="!$page.props.auth?.user?.mdd_id">
                                Link your account to Q3DF.org from <Link :href="route('profile.show')" class="text-yellow-200 hover:text-white underline font-medium">Settings</Link>.
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tabs and Content -->
            <div class="backdrop-blur-xl bg-black/40 rounded-2xl shadow-2xl border border-white/10 overflow-hidden">
                <!-- Tabs Header -->
                <Tabs :tabs="tabs" :onClick="toggleTab" :activeTab="activeTab" />

                <!-- Tab Content -->
                <div class="p-6">
                    <!-- Active Tournaments -->
                    <div v-show="activeTab === 'Active Tournaments'" id="active-tab" class="space-y-4">
                        <div v-for="(tournament, id) in activeTournaments" :key="id">
                            <TournamentCard :tournament="tournament" />
                        </div>
                        <div v-if="activeTournaments.length === 0" class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-600 mx-auto mb-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                            </svg>
                            <p class="text-gray-400 font-medium">No active tournaments available</p>
                        </div>
                    </div>

                    <!-- Upcoming Tournaments -->
                    <div v-show="activeTab === 'Upcoming Tournaments'" id="upcoming-tab" class="space-y-4">
                        <div v-for="(tournament, id) in upcomingTournaments" :key="id">
                            <TournamentCard :tournament="tournament" />
                        </div>
                        <div v-if="upcomingTournaments.length === 0" class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-600 mx-auto mb-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            <p class="text-gray-400 font-medium">No upcoming tournaments available</p>
                        </div>
                    </div>

                    <!-- Historical Tournaments -->
                    <div v-show="activeTab === 'Historical Tournaments'" id="historical-tab" class="space-y-4">
                        <div class="backdrop-blur-xl bg-blue-500/10 border border-blue-500/30 rounded-xl p-4 mb-6">
                            <p class="text-blue-300 text-sm text-center">
                                Want to submit a historical tournament? Contact <a class="text-blue-200 hover:text-white underline font-medium" :href="route('profile.index', 8)" v-html="q3tohtml('^4[^7gt^4]^7neiT^7.')"></a> on Discord.
                            </p>
                        </div>

                        <div v-for="(tournament, id) in pastTournaments" :key="id">
                            <TournamentCard :tournament="tournament" />
                        </div>
                        <div v-if="pastTournaments.length === 0" class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-600 mx-auto mb-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                            </svg>
                            <p class="text-gray-400 font-medium">No historical tournaments available</p>
                        </div>
                    </div>

                    <!-- Search Tournaments -->
                    <div v-show="activeTab === 'Search Tournaments'" id="search-tab">
                        <div class="mb-6">
                            <input
                                @input="onSearchTournament"
                                v-model="searchInput"
                                placeholder="Search tournaments..."
                                type="text"
                                class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            />
                        </div>

                        <div class="space-y-4">
                            <div v-for="tournament in filteredTournaments" :key="tournament.id">
                                <TournamentCard :tournament="tournament" />
                            </div>
                        </div>

                        <div v-if="filteredTournaments.length === 0 && searchInput.length > 0" class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-600 mx-auto mb-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <p class="text-gray-400 font-medium">No tournaments found</p>
                            <p class="text-gray-500 text-sm mt-1">Try a different search term</p>
                        </div>

                        <div v-if="searchInput.length === 0" class="text-center py-12">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-600 mx-auto mb-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <p class="text-gray-400 font-medium">Start typing to search</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
