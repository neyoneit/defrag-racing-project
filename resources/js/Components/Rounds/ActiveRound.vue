<script setup>
    import { ref } from 'vue';
    import RoundPart from '@/Components/Rounds/RoundPart.vue';
    import RoundResults from '@/Components/Rounds/RoundResults.vue';
    import RoundResultsClans from '@/Components/Rounds/RoundResultsClans.vue';
    import RoundResultsTeams from '@/Components/Rounds/RoundResultsTeams.vue';
    import MySubmissions from '@/Components/Rounds/MySubmissions.vue';
    import CommentSection from '@/Components/Tournament/CommentSection.vue';
    import { useForm } from '@inertiajs/vue3';


    const props = defineProps({
        tournament: Object,
        active: Boolean,
        round: Object,
        type: {
            type: String,
            default: 'single'
        },
        teams: {
            type: Array,
            default: []
        }
    });

    const form = useForm({
        demo: null,
        demo_date: 0,
    })

    const uploadFileName = ref('Select Demo to upload...')

    const changeUploadFileName = (e) => {
        uploadFileName.value = e.target.files[0].name;

        form.demo = e.target.files[0];

        form.demo_date = new Date(form.demo.lastModified);
    }

    const submitForm = () => {
        if (! form.demo) {
            return;
        }

        const url = route('tournaments.rounds.submit', {
            tournament: props.tournament.id,
            round: props.round.id
        })

        form.post(url, {}, {
            forceFormData: true,
        });
    }
</script>

<template>
    <div class="mb-6 backdrop-blur-xl bg-white/5 rounded-xl border border-white/10 overflow-hidden">
        <RoundPart :round="round">
            <template #additional>
                <div class="flex flex-col md:flex-row md:items-center gap-4 p-4 border-t border-white/10" v-if="active">
                    <div class="text-lg font-bold text-gray-300 md:min-w-[150px]">
                        Upload Demo
                    </div>

                    <form @submit.prevent="submitForm" class="flex-grow">
                        <div class="flex gap-2">
                            <input accept=".dm_68" type="file" name="file" id="demo" class="hidden" @change="changeUploadFileName">
                            <label for="demo" class="flex-grow px-4 py-3 bg-white/5 border border-white/10 rounded-lg cursor-pointer hover:bg-white/10 transition-all">
                                <div class="text-white truncate" :title="uploadFileName">
                                    {{ uploadFileName }}
                                </div>
                            </label>

                            <button type="submit" class="px-4 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg transition-all flex items-center justify-center">
                                <svg width="20px" height="20px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <g id="Complete">
                                        <g id="upload">
                                            <g>
                                                <path d="M3,12.3v7a2,2,0,0,0,2,2H19a2,2,0,0,0,2-2v-7" fill="none" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                                                <g>
                                                    <polyline data-name="Right" fill="none" id="Right-2" points="7.9 6.7 12 2.7 16.1 6.7" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                                                    <line fill="none" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" x1="12" x2="12" y1="16.3" y2="4.8"/>
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                </svg>
                            </button>
                        </div>

                        <div class="mt-2 text-red-400 text-sm" v-if="form.errors.demo">
                            {{ form.errors.demo }}
                        </div>
                    </form>
                </div>
            </template>
        </RoundPart>

        <RoundResults
            :tournament="tournament"
            :round="round"
            v-if="round.finished && round.results && type === 'single'"
        />

        <RoundResultsClans
            :round="round"
            :tournament="tournament"
            v-if="round.results && type === 'clans'"
        />

        <RoundResultsTeams
            :round="round"
            :tournament="tournament"
            :teams="teams"
            v-if="round.results && type === 'teams'"
        />

        <MySubmissions :round="round" :tournament="tournament" />

        <CommentSection :tournament="tournament" :comments="round.comments" :url="route('tournaments.rounds.comment', {tournament: tournament.id, round: round.id})" />
    </div>
</template>