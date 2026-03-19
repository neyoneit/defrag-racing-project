<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    maps: {
        type: Array,
        default: () => [],
    },
});

const physicsOpen = ref(false);
const modeOpen = ref(false);
const currencyOpen = ref(false);
const mapSearch = ref('');
const mapDropdownOpen = ref(false);

const filteredMaps = computed(() => {
    if (!mapSearch.value) return [];
    const search = mapSearch.value.toLowerCase();
    return props.maps.filter(m => m.name.toLowerCase().includes(search)).slice(0, 20);
});

const selectedMap = computed(() => {
    if (!mapSearch.value) return null;
    return props.maps.find(m => m.name === mapSearch.value) || null;
});

const selectMap = (map) => {
    form.mapname = map.name;
    mapSearch.value = map.name;
    mapDropdownOpen.value = false;
};

const physicsOptions = [
    { value: 'vq3', label: 'VQ3' },
    { value: 'cpm', label: 'CPM' },
];

const modeOptions = [
    { value: 'run', label: 'Run' },
    { value: 'strafe', label: 'Strafe' },
    { value: 'freestyle', label: 'Freestyle' },
    { value: 'fastcaps', label: 'Fastcaps' },
    { value: 'any', label: 'Any' },
];

const rewardType = ref('fiat');

const fiatOptions = [
    { value: 'USD', label: 'USD — US Dollar' },
    { value: 'EUR', label: 'EUR — Euro' },
    { value: 'GBP', label: 'GBP — British Pound' },
    { value: 'RUB', label: 'RUB — Russian Ruble' },
    { value: 'CAD', label: 'CAD — Canadian Dollar' },
    { value: 'AUD', label: 'AUD — Australian Dollar' },
];

const cryptoOptions = [
    { value: 'BTC', label: 'BTC — Bitcoin' },
    { value: 'ETH', label: 'ETH — Ethereum' },
    { value: 'USDT', label: 'USDT — Tether' },
    { value: 'USDC', label: 'USDC — USD Coin' },
    { value: 'LTC', label: 'LTC — Litecoin' },
    { value: 'SOL', label: 'SOL — Solana' },
    { value: 'XMR', label: 'XMR — Monero' },
];

const currencyOptions = computed(() => rewardType.value === 'crypto' ? cryptoOptions : fiatOptions);

watch(rewardType, (type) => {
    form.reward_currency = type === 'crypto' ? 'BTC' : 'USD';
});

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
    const timeInput = form.target_time.trim();
    let milliseconds = 0;

    if (timeInput.includes(':')) {
        // Format: MM:SS.mmm
        const [minutes, rest] = timeInput.split(':');
        const [seconds, ms] = rest.split('.');
        milliseconds = (parseInt(minutes) * 60 * 1000) + (parseInt(seconds) * 1000) + parseInt(ms || 0);
    } else {
        const dotCount = (timeInput.match(/\./g) || []).length;
        if (dotCount === 2) {
            // Format: M.SS.mmm (e.g., 1.19.200)
            const [minutes, seconds, ms] = timeInput.split('.');
            milliseconds = (parseInt(minutes) * 60 * 1000) + (parseInt(seconds) * 1000) + parseInt(ms || 0);
        } else {
            // Format: SS.mmm
            const [seconds, ms] = timeInput.split('.');
            milliseconds = (parseInt(seconds) * 1000) + parseInt(ms || 0);
        }
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

                <!-- Disclaimer Banner -->
                <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 mb-6">
                    <div class="flex gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-yellow-400 flex-shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <div class="text-sm text-yellow-300">
                            <strong>Disclaimer:</strong> Defrag Racing is not responsible for the fulfillment of rewards. All challenges and rewards are agreements between the creator and participants. By creating a challenge, you agree to honor the stated rewards. Failure to do so may result in a ban.
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-2xl p-8">
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
                            <div class="relative">
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Map Name *</label>
                                <div class="relative">
                                    <input
                                        v-model="mapSearch"
                                        @input="form.mapname = mapSearch; mapDropdownOpen = true"
                                        @focus="mapDropdownOpen = true"
                                        type="text"
                                        required
                                        autocomplete="off"
                                        placeholder="Start typing map name..."
                                        :class="[
                                            'w-full bg-black/40 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:ring-1 transition-colors',
                                            selectedMap ? 'border border-green-500/50 focus:border-green-500/50 focus:ring-green-500/50' : mapSearch ? 'border border-red-500/30 focus:border-red-500/50 focus:ring-red-500/50' : 'border border-white/10 focus:border-blue-500/50 focus:ring-blue-500/50'
                                        ]"
                                    />
                                    <div v-if="selectedMap" class="absolute right-3 top-1/2 -translate-y-1/2 text-green-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    </div>
                                    <div v-else-if="mapSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-red-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </div>
                                </div>
                                <!-- Map thumbnail preview -->
                                <div v-if="selectedMap" class="mt-2 flex items-center gap-3 bg-green-500/10 border border-green-500/20 rounded-lg p-2">
                                    <img v-if="selectedMap.thumbnail" :src="`/storage/${selectedMap.thumbnail}`" @error="$event.target.style.display='none'" class="w-16 h-10 object-cover rounded" />
                                    <div v-else class="w-16 h-10 bg-black/40 rounded flex items-center justify-center text-gray-600 text-xs">No img</div>
                                    <span class="text-sm text-green-400 font-medium">{{ selectedMap.name }}</span>
                                </div>
                                <div v-if="mapDropdownOpen && filteredMaps.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-y-auto max-h-60 z-50 shadow-2xl">
                                    <button type="button" v-for="map in filteredMaps" :key="map.name" @click="selectMap(map)" :class="form.mapname === map.name ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'" class="w-full px-3 py-2 text-left text-sm transition-colors flex items-center gap-3">
                                        <img v-if="map.thumbnail" :src="`/storage/${map.thumbnail}`" @error="$event.target.style.display='none'" class="w-12 h-8 object-cover rounded flex-shrink-0" />
                                        <div v-else class="w-12 h-8 bg-black/40 rounded flex-shrink-0"></div>
                                        <span>{{ map.name }}</span>
                                    </button>
                                </div>
                                <div v-if="mapDropdownOpen && filteredMaps.length > 0" @click="mapDropdownOpen = false" class="fixed inset-0 z-40"></div>
                                <div v-if="form.errors.mapname" class="text-red-400 text-sm mt-1">{{ form.errors.mapname }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Physics *</label>
                                <div class="relative">
                                    <button type="button" @click="physicsOpen = !physicsOpen" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white text-left flex items-center justify-between hover:border-white/20 transition-colors">
                                        <span>{{ physicsOptions.find(o => o.value === form.physics)?.label }}</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-400 transition-transform" :class="physicsOpen ? 'rotate-180' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                    </button>
                                    <div v-if="physicsOpen" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-hidden z-50 shadow-2xl">
                                        <button type="button" v-for="opt in physicsOptions" :key="opt.value" @click="form.physics = opt.value; physicsOpen = false" :class="form.physics === opt.value ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'" class="w-full px-4 py-2 text-left text-sm transition-colors">{{ opt.label }}</button>
                                    </div>
                                    <div v-if="physicsOpen" @click="physicsOpen = false" class="fixed inset-0 z-40"></div>
                                </div>
                                <div v-if="form.errors.physics" class="text-red-400 text-sm mt-1">{{ form.errors.physics }}</div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">Mode *</label>
                                <div class="relative">
                                    <button type="button" @click="modeOpen = !modeOpen" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white text-left flex items-center justify-between hover:border-white/20 transition-colors">
                                        <span>{{ modeOptions.find(o => o.value === form.mode)?.label }}</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-400 transition-transform" :class="modeOpen ? 'rotate-180' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                    </button>
                                    <div v-if="modeOpen" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-hidden z-50 shadow-2xl">
                                        <button type="button" v-for="opt in modeOptions" :key="opt.value" @click="form.mode = opt.value; modeOpen = false" :class="form.mode === opt.value ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'" class="w-full px-4 py-2 text-left text-sm transition-colors">{{ opt.label }}</button>
                                    </div>
                                    <div v-if="modeOpen" @click="modeOpen = false" class="fixed inset-0 z-40"></div>
                                </div>
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
                                placeholder="e.g., 1:23.456 or 1.23.456 or 45.123"
                                class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                            />
                            <p class="text-xs text-gray-500 mt-1">Format: M:SS.mmm or M.SS.mmm or SS.mmm</p>
                            <div v-if="form.errors.target_time" class="text-red-400 text-sm mt-1">{{ form.errors.target_time }}</div>
                        </div>

                        <!-- Reward Section -->
                        <div class="border-t border-white/10 pt-6">
                            <h3 class="text-xl font-bold text-white mb-4">Reward (Optional)</h3>

                            <div class="space-y-4">
                                <!-- Fiat / Crypto Toggle -->
                                <div class="flex gap-2 mb-2">
                                    <button type="button" @click="rewardType = 'fiat'" :class="rewardType === 'fiat' ? 'bg-blue-600 text-white' : 'bg-black/40 text-gray-400 hover:bg-white/10'" class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors border border-white/10">
                                        Fiat
                                    </button>
                                    <button type="button" @click="rewardType = 'crypto'" :class="rewardType === 'crypto' ? 'bg-orange-600 text-white' : 'bg-black/40 text-gray-400 hover:bg-white/10'" class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors border border-white/10">
                                        Crypto
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-semibold text-gray-300 mb-2">{{ rewardType === 'crypto' ? 'Crypto Reward' : 'Monetary Reward' }}</label>
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
                                        <div class="relative">
                                            <button type="button" @click="currencyOpen = !currencyOpen" class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white text-left flex items-center justify-between hover:border-white/20 transition-colors">
                                                <span>{{ currencyOptions.find(o => o.value === form.reward_currency)?.label || form.reward_currency }}</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-400 transition-transform" :class="currencyOpen ? 'rotate-180' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                            </button>
                                            <div v-if="currencyOpen" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-hidden z-50 shadow-2xl">
                                                <button type="button" v-for="opt in currencyOptions" :key="opt.value" @click="form.reward_currency = opt.value; currencyOpen = false" :class="form.reward_currency === opt.value ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'" class="w-full px-4 py-2 text-left text-sm transition-colors">{{ opt.label }}</button>
                                            </div>
                                            <div v-if="currencyOpen" @click="currencyOpen = false" class="fixed inset-0 z-40"></div>
                                        </div>
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
                                @click="$event.target.showPicker?.()"
                                style="color-scheme: dark"
                                class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 cursor-pointer [&::-webkit-calendar-picker-indicator]:invert [&::-webkit-calendar-picker-indicator]:cursor-pointer"
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
                                        <li>You have a 1-hour grace period after creation to edit your challenge freely. After that, edits require admin approval.</li>
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
