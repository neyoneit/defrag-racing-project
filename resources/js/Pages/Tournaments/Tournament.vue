<script setup>
    import { Head, usePage } from '@inertiajs/vue3';
    import Tabs2 from '@/Components/Tabs2.vue';
    import { onMounted, getCurrentInstance, ref } from 'vue';
    import OverviewNew from '@/Components/Tournament/OverviewNew.vue';

    const props = defineProps({
        tournament: Object,
        tab: String
    });

    const page = usePage()

    const pinnedNews = ref(page.props.pinnedNews)

    const tabs = [
        {
            name: 'Overview',
            label: 'Overview',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'Rounds',
            label: 'Rounds',
            link: true,
            route: 'tournaments.rounds.index',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'News',
            label: 'News',
            link: true,
            route: 'tournaments.news.index',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'Standings',
            label: 'Standings',
            link: true,
            route: 'tournaments.standings.index',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'Rules',
            label: 'Rules',
            link: true,
            route: 'tournaments.rules',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'Teams',
            label: 'Teams',
            link: true,
            route: 'tournaments.teams.index',
            params: { tournament: props.tournament.id },
            condition: props.tournament.has_teams
        },
        {
            name: 'ManageTeams',
            label: 'Manage Team',
            link: true,
            route: 'tournaments.teams.manage',
            params: { tournament: props.tournament.id },
            condition: props.tournament.has_teams
        },
        {
            name: 'Donations',
            label: 'Donations',
            link: true,
            route: 'tournaments.donations',
            params: { tournament: props.tournament.id },
            condition: props.tournament.has_donations
        },
        {
            name: 'FAQs',
            label: 'FAQs',
            link: true,
            route: 'tournaments.faqs',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'ManageTournament',
            label: 'Manage Tournament',
            link: true,
            route: 'tournaments.manage',
            params: { tournament: props.tournament.id },
            condition: props.tournament.isOrganizer || props.tournament.isValidator
        },

    ];

    const instance = getCurrentInstance();
    const globalProperties = instance.appContext.config.globalProperties;

    onMounted(() => {
        // globalProperties.$state.globalBackgroundImage = '/storage/' + props.tournament.image;
    });
</script>

<template>
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Tournaments" />

        <!-- Modern Tournament Container -->
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                <h1 class="text-4xl font-black text-white flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-yellow-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                    </svg>
                    {{ tournament.name }}
                </h1>

                <!-- Donate Button -->
                <a
                    v-if="tournament.donation_link"
                    :href="tournament.donation_link"
                    target="_blank"
                    class="flex items-center gap-2 px-4 py-3 bg-yellow-500 hover:bg-yellow-400 text-black font-bold rounded-lg transition-all transform hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-yellow-500/20"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Donate
                </a>
            </div>

            <!-- Main Content Card -->
            <div class="backdrop-blur-xl bg-black/40 rounded-2xl shadow-2xl border border-white/10 overflow-hidden">
                <!-- Tabs -->
                <Tabs2 :tabs="tabs" :activeTab="tab" />

                <!-- Content -->
                <div class="p-6">
                    <!-- Pinned News -->
                    <OverviewNew v-if="pinnedNews" :comments="false" :key="pinnedNews.id" :item="pinnedNews" :tournament="tournament" />

                    <!-- Slot Content -->
                    <slot></slot>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
    .image-hover-effect {
        background: radial-gradient(circle, #ff6347, #ffd700);
        opacity: 0.5
    }
</style>