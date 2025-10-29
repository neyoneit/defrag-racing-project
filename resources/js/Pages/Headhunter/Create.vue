<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    title: '',
    description: '',
    mapname: '',
    physics: 'vq3',
    mode: 'run',
    target_time: '',
    reward_amount: '',
    reward_currency: 'USD',
    reward_description: '',
    expires_at: '',
});

const submit = () => {
    // Convert time to milliseconds without modifying the form field
    const timeInput = form.target_time;
    let milliseconds = 0;

    if (timeInput.includes(':')) {
        // Format: MM:SS.mmm
        const [minutes, rest] = timeInput.split(':');
        const [seconds, ms] = rest.split('.');
        milliseconds = (parseInt(minutes) * 60 * 1000) + (parseInt(seconds) * 1000) + parseInt(ms || 0);
    } else {
        // Format: SS.mmm
        const [seconds, ms] = timeInput.split('.');
        milliseconds = (parseInt(seconds) * 1000) + parseInt(ms || 0);
    }

    // Use transform to send converted data without modifying the form
    form.transform((data) => ({
        ...data,
        target_time: milliseconds,
    })).post(route('headhunter.store'));
};
</script>

<template>
    <div class="min-h-screen pb-20">
        <Head title="Create Challenge" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pointer-events-auto">
                <!-- Back Button -->
                <Link :href="route('headhunter.index')" class="inline-flex items-center gap-2 text-blue-400 hover:text-blue-300 mb-6 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back to Challenges
                </Link>
            </div>
        </div>

        <!-- Content Section -->
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
                <!-- Form -->
                <div class="backdrop-blur-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8">
                    <h1 class="text-3xl font-black text-white mb-6">Create New Challenge</h1>

                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">Challenge Title *</label>
                            <input
                                v-model="form.title"
                                type="text"
                                required
                                placeholder="Enter a catchy title for your challenge"
                                class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            />
                            <div v-if="form.errors.title" class="text-red-400 text-sm mt-1">{{ form.errors.title }}</div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">Description *</label>
                            <textarea
                                v-model="form.description"
                                rows="4"
                                required
                                placeholder="Describe the challenge, rules, and any special requirements..."
                                class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            ></textarea>
                            <div v-if="form.errors.description" class="text-red-400 text-sm mt-1">{{ form.errors.description }}</div>
                        </div>

                        <!-- Map Details -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Map Name *</label>
                                <input
                                    v-model="form.mapname"
                                    type="text"
                                    required
                                    placeholder="e.g., q3ctf1"
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                />
                                <div v-if="form.errors.mapname" class="text-red-400 text-sm mt-1">{{ form.errors.mapname }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Physics *</label>
                                <select
                                    v-model="form.physics"
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                >
                                    <option value="vq3">VQ3</option>
                                    <option value="cpm">CPM</option>
                                </select>
                                <div v-if="form.errors.physics" class="text-red-400 text-sm mt-1">{{ form.errors.physics }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Mode *</label>
                                <select
                                    v-model="form.mode"
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                >
                                    <option value="run">Run</option>
                                    <option value="strafe">Strafe</option>
                                    <option value="freestyle">Freestyle</option>
                                    <option value="fastcaps">Fastcaps</option>
                                    <option value="any">Any</option>
                                </select>
                                <div v-if="form.errors.mode" class="text-red-400 text-sm mt-1">{{ form.errors.mode }}</div>
                            </div>
                        </div>

                        <!-- Target Time -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">Target Time *</label>
                            <input
                                v-model="form.target_time"
                                type="text"
                                required
                                placeholder="e.g., 1:23.456 or 45.123"
                                class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            />
                            <p class="text-xs text-gray-500 mt-1">Format: MM:SS.mmm (minutes:seconds.milliseconds) or SS.mmm</p>
                            <div v-if="form.errors.target_time" class="text-red-400 text-sm mt-1">{{ form.errors.target_time }}</div>
                        </div>

                        <!-- Reward Section -->
                        <div class="border-t border-white/10 pt-6">
                            <h3 class="text-xl font-bold text-white mb-4">Reward (Optional)</h3>

                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-semibold text-gray-300 mb-2">Monetary Reward</label>
                                        <input
                                            v-model="form.reward_amount"
                                            type="number"
                                            step="0.01"
                                            placeholder="Amount (e.g., 50.00)"
                                            class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                        />
                                        <div v-if="form.errors.reward_amount" class="text-red-400 text-sm mt-1">{{ form.errors.reward_amount }}</div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-300 mb-2">Currency</label>
                                        <select
                                            v-model="form.reward_currency"
                                            class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                        >
                                            <option value="USD">USD</option>
                                            <option value="EUR">EUR</option>
                                            <option value="GBP">GBP</option>
                                            <option value="CAD">CAD</option>
                                            <option value="AUD">AUD</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="text-center text-gray-500 text-sm">OR</div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">Non-Monetary Reward</label>
                                    <input
                                        v-model="form.reward_description"
                                        type="text"
                                        placeholder="e.g., Custom server config, shoutout, etc."
                                        class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                    />
                                    <div v-if="form.errors.reward_description" class="text-red-400 text-sm mt-1">{{ form.errors.reward_description }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Expiration Date -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">Expiration Date (Optional)</label>
                            <input
                                v-model="form.expires_at"
                                type="datetime-local"
                                class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            />
                            <p class="text-xs text-gray-500 mt-1">Leave empty for no expiration</p>
                            <div v-if="form.errors.expires_at" class="text-red-400 text-sm mt-1">{{ form.errors.expires_at }}</div>
                        </div>

                        <!-- Important Notice -->
                        <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                            <div class="flex gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-yellow-400 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                </svg>
                                <div class="text-sm">
                                    <div class="font-semibold text-yellow-400 mb-1">Important:</div>
                                    <ul class="text-yellow-300 space-y-1 list-disc list-inside">
                                        <li>You are responsible for paying any monetary rewards</li>
                                        <li>Failure to pay or respond to disputes within 14 days will result in a ban from creating challenges</li>
                                        <li>False or misleading challenges may result in account penalties</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center gap-4 pt-6">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl"
                            >
                                {{ form.processing ? 'Creating...' : 'Create Challenge' }}
                            </button>

                            <Link
                                :href="route('headhunter.index')"
                                class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-all duration-300"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
</template>
