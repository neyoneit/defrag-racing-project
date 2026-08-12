<script setup>
    import { useForm } from '@inertiajs/vue3';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import ConfirmationModal from '@/Components/Laravel/ConfirmationModal.vue';

    const props = defineProps({
        close: Function,
        show: Boolean,
        submit: Function,
        player: Object,
        clan: Object
    });

    const form = useForm({
        _method: 'POST'
    });

    const submitForm = () => {
        props.submit();
    };
</script>

<template>
    <div>
        <ConfirmationModal :show="show" maxWidth="xl" :closeable="true" @close="close">
            <template #title>
                <div class="flex justify-between items-center">
                    <div>{{ $t('Are you sure?') }}</div>

                    <div class="text-gray-200 cursor-pointer rounded-full hover:bg-grayop-700 p-1" @click="close">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
            </template>

            <template #content>
                <!-- Same as the accept-invitation modal: names as placeholders,
                     and no colour wrapper, because q3tohtml colours every
                     character itself. -->
                <span v-html="$t('Are you sure you want to transfer the clan [:clan] to the player [:player]? This action is irreversible!!!', { clan: q3tohtml(clan.name), player: q3tohtml(player.name) })"></span>
            </template>
                
            <template #footer>
                <div class="flex w-full justify-between">
                    <PrimaryButton @click="submitForm" class="bg-red-600 hover:bg-red-500">
                        {{ $t('Transfer Ownership') }}
                    </PrimaryButton>
    
                    <PrimaryButton @click="props.close" class="bg-gray-600 hover:bg-gray-500">
                        {{ $t('Cancel') }}
                    </PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </div>
</template>