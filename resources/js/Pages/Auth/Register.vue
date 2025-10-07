<script setup>
    import { Head, Link, useForm } from '@inertiajs/vue3';
    import CountrySelect from '@/Components/Basic/CountrySelect.vue';

    const form = useForm({
        username: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms: false,
        country: "_404",
    });

    const submit = () => {
        form.post(route('register'), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    const setCountry = (country) => {
        form.country = country
    };
</script>

<template>
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Register" />

        <!-- Modern Register Card -->
        <div class="w-full max-w-lg">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-black text-white mb-2">Create Account</h1>
                <p class="text-gray-400">Join the defrag racing community</p>
            </div>

            <!-- Register Card -->
            <div class="backdrop-blur-xl bg-black/40 rounded-2xl p-8 shadow-2xl border border-white/10">
                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-bold text-gray-300 mb-2">Username</label>
                        <input
                            id="username"
                            v-model="form.username"
                            type="text"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="Choose a username"
                        />
                        <p v-if="form.errors.username" class="mt-2 text-sm text-red-400">{{ form.errors.username }}</p>
                    </div>

                    <!-- Display Name Field -->
                    <div>
                        <label for="name" class="block text-sm font-bold text-gray-300 mb-2">
                            Display Name: <span v-if="form.name" v-html="q3tohtml(form.name)"></span>
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            autocomplete="name"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="Your display name"
                        />
                        <p class="mt-1 text-xs text-gray-500">You can use Quake3 color codes: ^1Red^2Green</p>
                        <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
                    </div>

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-sm font-bold text-gray-300 mb-2">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="your@email.com"
                        />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
                    </div>

                    <!-- Country Field -->
                    <div>
                        <label for="country" class="block text-sm font-bold text-gray-300 mb-2">Country</label>
                        <CountrySelect
                            id="country"
                            :setCountry="setCountry"
                            class="w-full"
                            required
                        />
                        <p v-if="form.errors.country" class="mt-2 text-sm text-red-400">{{ form.errors.country }}</p>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-bold text-gray-300 mb-2">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="Create a strong password"
                        />
                        <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
                    </div>

                    <!-- Confirm Password Field -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-bold text-gray-300 mb-2">Confirm Password</label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 focus:bg-white/10 transition-all"
                            placeholder="Confirm your password"
                        />
                        <p v-if="form.errors.password_confirmation" class="mt-2 text-sm text-red-400">{{ form.errors.password_confirmation }}</p>
                    </div>

                    <!-- Terms & Privacy -->
                    <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="pt-2">
                        <label class="flex items-start cursor-pointer group">
                            <input
                                id="terms"
                                v-model="form.terms"
                                type="checkbox"
                                name="terms"
                                required
                                class="w-4 h-4 mt-0.5 rounded bg-white/5 border border-white/10 text-blue-600 focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-0 cursor-pointer shrink-0"
                            />
                            <span class="ml-3 text-sm text-gray-400 group-hover:text-gray-300 transition">
                                I agree to the
                                <a target="_blank" :href="route('terms.show')" class="text-blue-400 hover:text-blue-300 underline">
                                    Terms of Service and Privacy Policy
                                </a>
                            </span>
                        </label>
                        <p v-if="form.errors.terms" class="mt-2 text-sm text-red-400">{{ form.errors.terms }}</p>
                    </div>

                    <!-- Register Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-600/50 text-white font-bold rounded-lg transition-all transform hover:scale-[1.02] active:scale-[0.98] disabled:cursor-not-allowed disabled:transform-none shadow-lg shadow-blue-500/20"
                    >
                        <span v-if="!form.processing">Create Account</span>
                        <span v-else class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Creating account...
                        </span>
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-white/10"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-black/40 text-gray-400">Already have an account?</span>
                    </div>
                </div>

                <!-- Login Link -->
                <Link
                    :href="route('login')"
                    class="block w-full text-center py-3 px-4 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 text-white font-medium rounded-lg transition-all"
                >
                    Sign In
                </Link>
            </div>

            <!-- Footer Links -->
            <div class="mt-8 text-center">
                <Link :href="route('home')" class="text-sm text-gray-400 hover:text-white transition">
                    ← Back to Home
                </Link>
            </div>
        </div>
    </div>
</template>
