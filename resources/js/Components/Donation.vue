<script setup>
    import { Link } from '@inertiajs/vue3';
    import { computed } from 'vue';
    import { currentLocale } from '@/utils/i18n';

    const props = defineProps({
        donation: Object,
        management: {
            type: Boolean,
            default: false
        }
    });

    const getName = computed(() => {
        return props.donation.name
    });

    const getTime = () => {
        return new Date(props.donation.created_at).toLocaleString(currentLocale());
    }
</script>

<template>
    <div class="rounded-md bg-blackop-30 px-5 py-3 mx-2 my-2">
        <div class="flex justify-between">
            <h2 class="text-lg text-gray-300 mb-2">
                <span class="mr-3" v-html="q3tohtml(getName)"></span>
                <span>{{ $t('Donated') }}</span>
                <span class="mx-1 text-blue-500">{{ props.donation.currency }}{{ props.donation.amount }}</span>
                <span>{{ $t('Saying') }}</span>

                <span class="ml-1 italic text-gray-500">{{ props.donation.message }}</span>
            </h2>

            <div class="flex" v-if="management">
                <Link :href="route('tournaments.donations.edit', {tournament: donation.tournament_id, donation: donation.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                    {{ $t('Edit') }}
                </Link>

                <Link :href="route('tournaments.donations.destroy', {tournament: donation.tournament_id, donation: donation.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                    {{ $t('Delete') }}
                </Link>
            </div>
        </div>
        <div class="flex justify-end text-gray-600" v-if="props.donation.created_at">
            {{ $t('At :time', { time: getTime() }) }}
        </div>
    </div>
</template>