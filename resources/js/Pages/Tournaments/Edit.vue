<script setup>
    import { Head } from '@inertiajs/vue3';
    import TournamentForm from './TournamentForm.vue';

    const props = defineProps({
        error: {
            type: String,
            default: '',
        },
        tournament: Object
    });
</script>

<template>
    <div>
        <Head title="Edit Tournament" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                Edit Tournament [{{ tournament.name }}]
            </h2>
        </div>

        <div>
            <div class="max-w-8xl mx-auto py-10 sm:px-6 lg:px-8 mb-10">
                <div v-if="error" class="mb-5 p-4 rounded-lg bg-red-600/20 border border-red-500/30 text-red-300">
                    <div class="font-bold text-sm">An Error Occurred!</div>
                    <div class="text-sm mt-1">{{ error }}</div>
                </div>

                <TournamentForm :tournament="tournament" url="tournaments.update" />
            </div>
        </div>
    </div>
</template>
