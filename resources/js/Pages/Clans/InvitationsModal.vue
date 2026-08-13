<script setup>
    import { useForm } from '@inertiajs/vue3';
    import Modal from '@/Components/Laravel/Modal.vue';
    import { ref } from 'vue';
    import ConfirmAcceptInvitationModal from './ConfirmAcceptInvitationModal.vue';

    const props = defineProps({
        invitations: Array,
        close: Function,
        show: Boolean,
        myClan: Object
    });

    const showAcceptConfirmation = ref(false);

    const chosenInvitation = ref(null);

    const form = useForm({
        _method: 'POST'
    });

    const acceptInvitation = (invitation) => {
        chosenInvitation.value = invitation;
        if (! props.myClan) {
            acceptInvitationConfirmed();
        } else {
            showAcceptConfirmation.value = true;
        }
    };

    const acceptInvitationConfirmed = () => {
        form.post(route('clans.invitation.accept', chosenInvitation.value.id), {
            errorBag: 'submitForm',
            preserveScroll: true,
            onSuccess: () => {
                showAcceptConfirmation.value = false;
                props.close();
            }
        });
    }

    const rejectInvitation = (id) => {
        form.post(route('clans.invitation.reject', id), {
            errorBag: 'submitForm',
            preserveScroll: true,
            onSuccess: () => {
                props.close();
            }
        });
    };
</script>

<template>
    <div>
        <Modal :show="show" maxWidth="2xl" :closeable="true" @close="close">
            <div class="p-5">
                <div class="flex justify-between items-center">
                    <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                        {{ $t('Clan Invitations') }}
                    </h2>

                    <div class="text-gray-200 cursor-pointer rounded-full hover:bg-grayop-700 p-1" @click="close">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <div class="w-full bg-gray-700 my-2" style="height: 1px;"></div>

                <div class="px-3">
                    <div v-for="invitation in invitations" :key="invitation.id" class="">
                        <div class="items-center bg-gray-800 flex justify-between flex-wrap rounded-md p-3">
                            <div>
                                <!-- The clan name goes in as a placeholder rather than cutting the
                                     sentence in two. q3tohtml escapes the name and gives every
                                     character its own colour span, so the text-gray-200 wrapper this
                                     line used to have was doing nothing. -->
                                <span class="text-gray-500" v-html="$t(':clan has invited you to join their clan.', { clan: q3tohtml(invitation.clan.name) })"></span>
                            </div>

                            <div class="flex">
                                <div @click="acceptInvitation(invitation)" :title="$t('Accept')" class="text-white bg-green-700 cursor-pointer hover:bg-green-600 text-center rounded-full flex items-center p-1 mr-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>

                                <div @click="rejectInvitation(invitation.id)" :title="$t('Reject')" class="text-white bg-red-700 cursor-pointer hover:bg-red-600 text-center rounded-full flex items-center p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </div>
                            </div>
                        </div>
    
                        <div class="w-full bg-gray-700 my-2" style="height: 1px;"></div>
                    </div>

                    <div v-if="invitations.length === 0" class="text-gray-200 text-center">
                        {{ $t('There are no clan Invitations.') }}
                    </div>
                </div>
            </div>
        </Modal>

        <div v-if="chosenInvitation">
            <ConfirmAcceptInvitationModal
                :show="showAcceptConfirmation"
                :close="() => showAcceptConfirmation = false"
                :confirm="acceptInvitationConfirmed"
                :clan="chosenInvitation.clan"
                :myClan="myClan"
            />
        </div>
    </div>
</template>