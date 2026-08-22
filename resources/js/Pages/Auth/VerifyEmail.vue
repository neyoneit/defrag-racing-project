<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <div>
        <Head :title="$t('Email Verification')" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-2xl md:text-3xl font-black text-white mb-2">{{ $t('Verify Email') }}</h1>
                    <p class="text-gray-400">{{ $t('One more step to get started') }}</p>
                </div>
            </div>
        </div>

        <div class="flex justify-center px-4 sm:px-6 lg:px-8" style="margin-top: -22rem;">
            <div class="w-full max-w-md">
                <div class="bg-black/40 backdrop-blur-sm rounded-2xl p-8 shadow-2xl border border-white/10">
                    <div class="mb-4 text-sm text-gray-400">
                        {{ $t('Before continuing, could you verify your email address by clicking on the link I just emailed to you? If you didn\'t receive the email, I will gladly send you another.') }}
                    </div>

                    <div v-if="verificationLinkSent" class="mb-4 p-4 rounded-lg bg-green-500/10 border border-green-500/30">
                        <p class="text-sm font-medium text-green-400">{{ $t('A new verification link has been sent to the email address you provided in your profile settings.') }}</p>
                    </div>

                    <form @submit.prevent="submit">
                        <div class="mt-4 flex items-center justify-between">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                {{ $t('Resend Verification Email') }}
                            </PrimaryButton>

                            <div class="flex items-center gap-3">
                                <Link
                                    :href="route('settings.show')"
                                    class="text-sm text-gray-400 hover:text-white transition-colors"
                                >
                                    {{ $t('Edit Profile') }}
                                </Link>

                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="text-sm text-gray-400 hover:text-white transition-colors"
                                >
                                    {{ $t('Log Out') }}
                                </Link>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
