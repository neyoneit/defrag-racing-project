<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    donations: Array,
    selfRaisedMoney: Array,
    goal: Object,
    allGoals: Object,
    currentYear: Number,
    exchangeRates: Object,
});

// Selected currency
const selectedCurrency = ref('USD');

// Highlight operational costs section
const highlightOperationalCosts = () => {
    const element = document.getElementById('operational-costs');
    if (element) {
        element.classList.add('highlight-flash');
        setTimeout(() => {
            element.classList.remove('highlight-flash');
        }, 1000);
    }
};

// Track which years are expanded (only current year expanded by default)
const expandedYears = ref(new Set([props.currentYear]));

// Toggle year expansion
const toggleYear = (year) => {
    if (expandedYears.value.has(year)) {
        expandedYears.value.delete(year);
    } else {
        expandedYears.value.add(year);
    }
};

// Check if year is expanded
const isYearExpanded = (year) => {
    return expandedYears.value.has(year);
};

// Convert amount to selected currency
const convertCurrency = (amount, fromCurrency) => {
    if (fromCurrency === selectedCurrency.value) {
        return parseFloat(amount);
    }

    // Convert to EUR first
    const amountInEUR = fromCurrency === 'EUR'
        ? parseFloat(amount)
        : parseFloat(amount) / props.exchangeRates[fromCurrency];

    // Then convert to selected currency
    return selectedCurrency.value === 'EUR'
        ? amountInEUR
        : amountInEUR * props.exchangeRates[selectedCurrency.value];
};

// Calculate total in selected currency
const totalRaised = computed(() => {
    let total = 0;

    props.donations.forEach(donation => {
        total += convertCurrency(donation.amount, donation.currency);
    });

    props.selfRaisedMoney.forEach(money => {
        total += convertCurrency(money.amount, money.currency);
    });

    return total.toFixed(2);
});

// Calculate all-time donations total (crowd-raised only)
const allTimeDonationsTotal = computed(() => {
    let total = 0;

    props.donations.forEach(donation => {
        total += convertCurrency(donation.amount, donation.currency);
    });

    return total;
});

// Calculate all-time self-raised total
const allTimeSelfRaisedTotal = computed(() => {
    let total = 0;

    props.selfRaisedMoney.forEach(money => {
        total += convertCurrency(money.amount, money.currency);
    });

    return total;
});

// Calculate current year total
const currentYearTotal = computed(() => {
    let total = 0;

    props.donations.forEach(donation => {
        const year = new Date(donation.donation_date).getFullYear();
        if (year === props.currentYear) {
            total += convertCurrency(donation.amount, donation.currency);
        }
    });

    props.selfRaisedMoney.forEach(money => {
        const year = new Date(money.earned_date).getFullYear();
        if (year === props.currentYear) {
            total += convertCurrency(money.amount, money.currency);
        }
    });

    return total.toFixed(2);
});

// Calculate current year donations total (crowd-raised only)
const currentYearDonationsTotal = computed(() => {
    let total = 0;

    props.donations.forEach(donation => {
        const year = new Date(donation.donation_date).getFullYear();
        if (year === props.currentYear) {
            total += convertCurrency(donation.amount, donation.currency);
        }
    });

    return total;
});

// Calculate current year self-raised total
const currentYearSelfRaisedTotal = computed(() => {
    let total = 0;

    props.selfRaisedMoney.forEach(money => {
        const year = new Date(money.earned_date).getFullYear();
        if (year === props.currentYear) {
            total += convertCurrency(money.amount, money.currency);
        }
    });

    return total;
});

// Calculate goal in selected currency
const goalAmount = computed(() => {
    return convertCurrency(props.goal.yearly_goal, props.goal.currency).toFixed(2);
});

const monthlyGoal = computed(() => {
    return (goalAmount.value / 12).toFixed(2);
});

// Calculate progress percentage
const progressPercentage = computed(() => {
    const percentage = (currentYearTotal.value / goalAmount.value) * 100;
    return Math.min(percentage, 100).toFixed(1);
});

// Group donations by year/month
const groupedByYear = computed(() => {
    const groups = {};

    props.donations.forEach(donation => {
        const year = new Date(donation.donation_date).getFullYear();
        if (!groups[year]) groups[year] = { donations: [], selfRaised: [], total: 0, donationsTotal: 0, selfRaisedTotal: 0 };
        groups[year].donations.push(donation);
        const amount = convertCurrency(donation.amount, donation.currency);
        groups[year].donationsTotal += amount;
        groups[year].total += amount;
    });

    props.selfRaisedMoney.forEach(money => {
        const year = new Date(money.earned_date).getFullYear();
        if (!groups[year]) groups[year] = { donations: [], selfRaised: [], total: 0, donationsTotal: 0, selfRaisedTotal: 0 };
        groups[year].selfRaised.push(money);
        const amount = convertCurrency(money.amount, money.currency);
        groups[year].selfRaisedTotal += amount;
        groups[year].total += amount;
    });

    return Object.keys(groups)
        .sort((a, b) => b - a)
        .map(year => ({
            year,
            ...groups[year]
        }));
});

// Format date
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Currency symbols
const currencySymbol = computed(() => {
    const symbols = {
        USD: '$',
        EUR: '€',
        CZK: 'Kč'
    };
    return symbols[selectedCurrency.value] || selectedCurrency.value;
});

// Get goal for a specific year
const getYearGoal = (year) => {
    // Convert year to number to match the key
    const yearKey = parseInt(year);
    if (props.allGoals && props.allGoals[yearKey]) {
        return {
            amount: convertCurrency(props.allGoals[yearKey].yearly_goal, props.allGoals[yearKey].currency),
            exists: true
        };
    }
    return { amount: 0, exists: false };
};

// Calculate progress percentage for a year
const getYearProgress = (year, yearTotal) => {
    const yearGoal = getYearGoal(year);
    if (!yearGoal.exists || yearGoal.amount === 0) {
        return null;
    }
    const percentage = (yearTotal / yearGoal.amount) * 100;
    return {
        percentage: Math.min(percentage, 100).toFixed(1),
        goal: yearGoal.amount.toFixed(2)
    };
};
</script>

<template>
    <Head title="Support Defrag Racing" />

    <div>
        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/60 via-black/30 to-transparent pt-6 pb-96">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="text-center">
                    <h1 class="text-4xl font-bold text-white mb-4">Support Defrag Racing</h1>
                    <p class="text-xl text-gray-400">Help to keep defrag.racing projects running and the community thriving!</p>
                </div>
            </div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 pb-12" style="margin-top: -22rem;">

            <!-- Current Year Progress -->
            <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5 mb-5 mx-auto">
                <div class="relative mb-2">
                    <h2 class="text-2xl font-bold text-white text-center">{{ currentYear }} Progress</h2>

                    <!-- Currency Selector -->
                    <div class="absolute right-0 top-0 flex gap-2">
                        <button
                            v-for="currency in ['USD', 'EUR']"
                            :key="currency"
                            @click="selectedCurrency = currency"
                            :class="[
                                'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                selectedCurrency === currency
                                    ? 'bg-blue-600 text-white'
                                    : 'backdrop-blur-xl bg-black/60 text-gray-300 hover:bg-black/70 border border-white/10'
                            ]"
                        >
                            {{ currency }}
                        </button>
                    </div>
                </div>

                <p class="text-xs text-gray-500 text-center mb-4 italic">Manually updated sporadically, not in real-time</p>

                <div class="max-w-3xl mx-auto">
                    <!-- Progress Bar -->
                    <div class="relative h-12 bg-black/30 rounded-full overflow-hidden mb-4 border border-white/10">
                        <!-- Crowd-raised portion (green) -->
                        <div
                            v-if="currentYearDonationsTotal > 0"
                            class="absolute h-full bg-green-500 transition-all duration-1000"
                            :style="{ width: ((currentYearDonationsTotal / goalAmount) * 100) + '%' }"
                        ></div>
                        <!-- Self-raised portion (purple) -->
                        <div
                            v-if="currentYearSelfRaisedTotal > 0"
                            class="absolute h-full bg-purple-500 transition-all duration-1000"
                            :style="{ left: ((currentYearDonationsTotal / goalAmount) * 100) + '%', width: ((currentYearSelfRaisedTotal / goalAmount) * 100) + '%' }"
                        ></div>
                        <!-- Percentage label -->
                        <div class="absolute inset-0 flex items-center justify-end pr-4">
                            <span class="text-white font-bold text-sm">{{ progressPercentage }}%</span>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                        <div>
                            <div class="text-3xl font-bold text-green-400">{{ currencySymbol }}{{ currentYearTotal }}</div>
                            <div class="text-sm text-gray-400 mt-1">Raised This Year</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-blue-400">{{ currencySymbol }}{{ goalAmount }}</div>
                            <div class="text-sm text-gray-400 mt-1">Yearly Goal</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-orange-400">{{ currencySymbol }}{{ monthlyGoal }}</div>
                            <div class="text-sm text-gray-400 mt-1">Monthly Goal</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Where Donations Go -->
            <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5 mb-5 mx-auto">
                <h2 class="text-2xl font-bold text-white mb-4 text-center">Where Your Support Goes</h2>
                <div class="max-w-3xl mx-auto space-y-4 text-gray-300">
                    <div id="operational-costs">
                        <h3 class="text-lg font-semibold text-white mb-2">Operational Costs (~€1,200/year)</h3>
                        <p class="text-sm leading-relaxed">
                            Donations help cover: servers, hosting, vps's, backblaze, domains, DemoMe, DefragLive, isp, electricity.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-white mb-2">A Brief History</h3>
                        <p class="text-sm leading-relaxed">
                            All developers who worked on these projects since 2021 were fairly compensated. In early 2024, the last paid developer unexpectedly departed without response,
                            leaving everything as-is. At that point, I decided to stop paying for development as it felt like progress had stalled. With Batawi's help, I made defrag-racing and related repositories <a href="https://github.com/Defrag-racing/" target="_blank" class="text-blue-400 hover:text-blue-300 underline">open-source</a>, inviting the community to contribute.
                            Development has been in limbo since, as no one had the availability or free time to contribute. Around August 2025, I've taken it upon myself with AI assistance to continue development independently.
                            Mind you, I never had any prior knowledge with coding, but I made it work and I am learning everyday as I go, bringing you a fully functional <a href="https://twitch.tv/defraglive" target="_blank" class="text-purple-400 hover:text-purple-300 underline">DefragLive</a> with extension, improved defrag.racing, and more updates coming soon.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-white mb-2">What I Cover Out-of-Pocket</h3>
                        <p class="text-sm leading-relaxed">
                            Hardware costs (dedicated PC for DemoMe+DefragLive, EU server PC) and countless other expenses remain out-of-pocket.
                            Your donations help offset <a href="#operational-costs" @click.prevent="highlightOperationalCosts" class="text-blue-400 hover:text-blue-300 underline cursor-pointer">operational costs</a>.
                        </p>
                    </div>

                    <div class="border-t border-white/10 pt-4">
                        <h3 class="text-lg font-semibold text-blue-300 mb-3">How You Can Help Without Donating Money</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex gap-2">
                                <span class="text-blue-400">•</span>
                                <span><strong>Allow DefragLive to spectate you</strong> - The more viewers it gets, the less I have to pay from my own pocket</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="text-blue-400">•</span>
                                <span><strong>Spread the word about DefragLegends YouTube channel</strong> - We're missing 1,600 yearly watch hours to get it monetized. Playing it in the background could help!</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Donate Button -->
            <div class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5 mb-5 mx-auto text-center">
                <h2 class="text-2xl font-bold text-white mb-4">Make a Donation</h2>
                <p class="text-gray-400 mb-6">Your support helps us cover server costs and continue improving the platform!</p>

                <!-- PayPal Button Container - You'll add the embed code here -->
                <div id="paypal-button-container" class="max-w-md mx-auto">
                    <div class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-lg inline-block cursor-pointer transition-colors">
                        Donate via PayPal
                    </div>
                    <p class="text-sm text-gray-500 mt-4">Secure payment through PayPal</p>
                </div>
            </div>

            <!-- Donation History by Year -->
            <div class="space-y-4">
                <h2 class="text-3xl font-bold text-white text-center mb-6">Donation History</h2>

                <div v-for="yearData in groupedByYear" :key="yearData.year" class="backdrop-blur-xl bg-black/40 rounded-xl p-4 shadow-2xl border border-white/5 mb-5 mx-auto">
                    <div
                        class="cursor-pointer hover:bg-white/5 rounded-lg p-2 -m-2 transition-colors"
                        @click="toggleYear(yearData.year)"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-3">
                                <svg
                                    class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                    :class="{ 'rotate-90': isYearExpanded(yearData.year) }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                <h3 class="text-2xl font-bold text-white">{{ yearData.year }}</h3>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <div class="text-2xl font-bold text-green-400">{{ currencySymbol }}{{ yearData.total.toFixed(2) }}</div>
                                <div class="flex gap-3 text-xs text-gray-400">
                                    <span v-if="yearData.donationsTotal > 0" class="flex items-center gap-1">
                                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                        {{ currencySymbol }}{{ yearData.donationsTotal.toFixed(2) }} crowd
                                    </span>
                                    <span v-if="yearData.selfRaisedTotal > 0" class="flex items-center gap-1">
                                        <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                                        {{ currencySymbol }}{{ yearData.selfRaisedTotal.toFixed(2) }} self
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Year Progress Bar (always visible if goal exists) -->
                    <div v-if="getYearProgress(yearData.year, yearData.total)" class="mb-3 mt-2">
                        <div class="relative h-8 bg-black/30 rounded-full overflow-hidden border border-white/10">
                            <!-- Crowd-raised portion (green) -->
                            <div
                                v-if="yearData.donationsTotal > 0"
                                class="absolute h-full bg-green-500 transition-all duration-1000"
                                :style="{ width: ((yearData.donationsTotal / getYearGoal(yearData.year).amount) * 100) + '%' }"
                            ></div>
                            <!-- Self-raised portion (purple) -->
                            <div
                                v-if="yearData.selfRaisedTotal > 0"
                                class="absolute h-full bg-purple-500 transition-all duration-1000"
                                :style="{ left: ((yearData.donationsTotal / getYearGoal(yearData.year).amount) * 100) + '%', width: ((yearData.selfRaisedTotal / getYearGoal(yearData.year).amount) * 100) + '%' }"
                            ></div>
                            <!-- Percentage label -->
                            <div class="absolute inset-0 flex items-center justify-end pr-3">
                                <span class="text-white font-bold text-xs">{{ getYearProgress(yearData.year, yearData.total).percentage }}%</span>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 mt-1 text-center">
                            Goal: {{ currencySymbol }}{{ getYearProgress(yearData.year, yearData.total).goal }}
                        </div>
                    </div>

                    <!-- Expandable Content -->
                    <div v-if="isYearExpanded(yearData.year)">
                        <!-- Donations -->
                        <div v-if="yearData.donations.length > 0" class="mb-4">
                            <h4 class="text-sm font-bold text-gray-300 mb-2">Donations</h4>
                            <div class="space-y-1.5">
                                <div
                                    v-for="donation in yearData.donations"
                                    :key="donation.id"
                                    class="flex items-center justify-between p-2 bg-black/20 rounded-lg border border-green-500/20"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-medium text-white">{{ donation.donor_name || 'Anonymous Donor' }}</span>
                                        <span class="text-xs text-gray-500">{{ formatDate(donation.donation_date) }}</span>
                                    </div>
                                    <div class="text-sm font-bold text-green-400">
                                        {{ currencySymbol }}{{ convertCurrency(donation.amount, donation.currency).toFixed(2) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Self-Raised Money -->
                        <div v-if="yearData.selfRaised.length > 0">
                            <h4 class="text-sm font-bold text-gray-300 mb-2">Self-Raised (YouTube/Twitch)</h4>
                            <div class="space-y-1.5">
                                <div
                                    v-for="money in yearData.selfRaised"
                                    :key="money.id"
                                    class="flex items-center justify-between p-2 bg-black/20 rounded-lg border border-purple-500/20"
                                >
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-medium text-white">{{ money.source }}</span>
                                        <span class="text-xs text-gray-500">{{ formatDate(money.earned_date) }}</span>
                                    </div>
                                    <div class="text-sm font-bold text-purple-400">
                                        {{ currencySymbol }}{{ convertCurrency(money.amount, money.currency).toFixed(2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All-Time Total -->
            <div class="mt-12 backdrop-blur-xl bg-black/60 border border-white/10 rounded-xl p-8 text-center">
                <h2 class="text-2xl font-bold text-white mb-4">All-Time Total</h2>

                <!-- Progress Bar showing split -->
                <div class="max-w-2xl mx-auto mb-6">
                    <div class="relative h-10 bg-black/30 rounded-full overflow-hidden border border-white/10">
                        <!-- Crowd-raised portion (green) -->
                        <div
                            v-if="allTimeDonationsTotal > 0"
                            class="absolute h-full bg-green-500 transition-all duration-1000"
                            :style="{ width: ((allTimeDonationsTotal / parseFloat(totalRaised)) * 100) + '%' }"
                        ></div>
                        <!-- Self-raised portion (purple) -->
                        <div
                            v-if="allTimeSelfRaisedTotal > 0"
                            class="absolute h-full bg-purple-500 transition-all duration-1000"
                            :style="{ left: ((allTimeDonationsTotal / parseFloat(totalRaised)) * 100) + '%', width: ((allTimeSelfRaisedTotal / parseFloat(totalRaised)) * 100) + '%' }"
                        ></div>
                    </div>
                    <div class="flex justify-center gap-4 mt-2 text-xs text-gray-400">
                        <span v-if="allTimeDonationsTotal > 0" class="flex items-center gap-1">
                            <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                            {{ currencySymbol }}{{ allTimeDonationsTotal.toFixed(2) }} crowd
                        </span>
                        <span v-if="allTimeSelfRaisedTotal > 0" class="flex items-center gap-1">
                            <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                            {{ currencySymbol }}{{ allTimeSelfRaisedTotal.toFixed(2) }} self
                        </span>
                    </div>
                </div>

                <div class="text-5xl font-bold text-green-400">{{ currencySymbol }}{{ totalRaised }}</div>
                <p class="text-gray-400 mt-4">Thank you to everyone who has supported defrag.racing projects!</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes flash-highlight {
    0%, 100% {
        background-color: transparent;
    }
    50% {
        background-color: rgba(59, 130, 246, 0.3);
    }
}

.highlight-flash {
    animation: flash-highlight 1s ease-in-out;
    border-radius: 0.75rem;
}
</style>
