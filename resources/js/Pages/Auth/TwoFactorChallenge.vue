<script setup>
import { nextTick, ref, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);

onMounted(() => {
    nextTick(() => codeInput.value?.focus());
});

const toggleRecovery = async () => {
    recovery.value ^= true;

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value.focus();
        form.code = '';
    } else {
        codeInput.value.focus();
        form.recovery_code = '';
    }
};

const submit = () => {
    form.post(route('two-factor.login'));
};
</script>

<template>
    <div>
        <Head title="Two-Factor Authentication" />

        <!-- Header Section with Gradient Shadow -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-4xl font-black text-white mb-2">Two-Factor Authentication</h1>
                    <p class="text-gray-400">Confirm your identity to continue</p>
                </div>
            </div>
        </div>

        <div class="flex justify-center px-4 sm:px-6 lg:px-8" style="margin-top: -22rem;">
            <div class="w-full max-w-md">

            <!-- 2FA Card -->
            <div class="bg-black/40 backdrop-blur-sm rounded-2xl p-8 shadow-2xl border border-white/10">
                <!-- Description -->
                <div class="mb-6 text-sm text-gray-400">
                    <template v-if="!recovery">
                        Please confirm access to your account by entering the authentication code provided by your authenticator application.
                    </template>
                    <template v-else>
                        Please confirm access to your account by entering one of your emergency recovery codes.
                    </template>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Code Field -->
                    <div v-if="!recovery">
                        <label for="code" class="block text-sm font-bold text-gray-300 mb-2">Code</label>
                        <input
                            id="code"
                            ref="codeInput"
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            autofocus
                            autocomplete="one-time-code"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="Enter your 6-digit code"
                        />
                        <p v-if="form.errors.code" class="mt-2 text-sm text-red-400">{{ form.errors.code }}</p>
                    </div>

                    <!-- Recovery Code Field -->
                    <div v-else>
                        <label for="recovery_code" class="block text-sm font-bold text-gray-300 mb-2">Recovery Code</label>
                        <input
                            id="recovery_code"
                            ref="recoveryCodeInput"
                            v-model="form.recovery_code"
                            type="text"
                            autocomplete="one-time-code"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="Enter your recovery code"
                        />
                        <p v-if="form.errors.recovery_code" class="mt-2 text-sm text-red-400">{{ form.errors.recovery_code }}</p>
                    </div>

                    <!-- Toggle & Submit -->
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            class="text-sm text-blue-400 hover:text-blue-300 transition cursor-pointer"
                            @click.prevent="toggleRecovery"
                        >
                            <template v-if="!recovery">Use a recovery code</template>
                            <template v-else>Use an authentication code</template>
                        </button>
                    </div>

                    <!-- Login Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-600/50 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] active:scale-[0.98] disabled:cursor-not-allowed disabled:transform-none shadow-lg shadow-blue-500/20"
                    >
                        <span v-if="!form.processing">Log In</span>
                        <span v-else class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Verifying...
                        </span>
                    </button>
                </form>
            </div>

                <!-- Footer Links -->
                <div class="mt-8 text-center">
                    <Link :href="route('login')" class="text-sm text-gray-400 hover:text-white transition">
                        ← Back to Login
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
