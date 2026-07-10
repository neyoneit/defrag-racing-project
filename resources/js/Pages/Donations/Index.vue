<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Teleport } from 'vue';

const props = defineProps({
    donations: Array,
    selfRaisedMoney: Array,
    goal: Object,
    allGoals: Object,
    currentYear: Number,
    exchangeRates: Object,
});

// Currency names mapping
const currencyNames = {
    'USD': 'US Dollar',
    'EUR': 'Euro',
    'JPY': 'Japanese Yen',
    'GBP': 'British Pound',
    'CNY': 'Chinese Yuan',
    'AUD': 'Australian Dollar',
    'CAD': 'Canadian Dollar',
    'CHF': 'Swiss Franc',
    'HKD': 'Hong Kong Dollar',
    'SEK': 'Swedish Krona',
    'NZD': 'New Zealand Dollar',
    'SGD': 'Singapore Dollar',
    'NOK': 'Norwegian Krone',
    'KRW': 'South Korean Won',
    'MXN': 'Mexican Peso',
    'INR': 'Indian Rupee',
    'RUB': 'Russian Ruble',
    'BRL': 'Brazilian Real',
    'ZAR': 'South African Rand',
    'DKK': 'Danish Krone',
    'CZK': 'Czech Koruna',
    'PLN': 'Polish Złoty',
    'TRY': 'Turkish Lira',
    'AED': 'UAE Dirham',
    'AFN': 'Afghan Afghani',
    'ALL': 'Albanian Lek',
    'AMD': 'Armenian Dram',
    'ANG': 'Netherlands Antillean Guilder',
    'AOA': 'Angolan Kwanza',
    'ARS': 'Argentine Peso',
    'AWG': 'Aruban Florin',
    'AZN': 'Azerbaijani Manat',
    'BAM': 'Bosnia-Herzegovina Convertible Mark',
    'BBD': 'Barbadian Dollar',
    'BDT': 'Bangladeshi Taka',
    'BGN': 'Bulgarian Lev',
    'BHD': 'Bahraini Dinar',
    'BIF': 'Burundian Franc',
    'BMD': 'Bermudan Dollar',
    'BND': 'Brunei Dollar',
    'BOB': 'Bolivian Boliviano',
    'BSD': 'Bahamian Dollar',
    'BTN': 'Bhutanese Ngultrum',
    'BWP': 'Botswanan Pula',
    'BYN': 'Belarusian Ruble',
    'BZD': 'Belize Dollar',
    'CDF': 'Congolese Franc',
    'CLF': 'Chilean Unit of Account',
    'CLP': 'Chilean Peso',
    'COP': 'Colombian Peso',
    'CRC': 'Costa Rican Colón',
    'CUC': 'Cuban Convertible Peso',
    'CUP': 'Cuban Peso',
    'CVE': 'Cape Verdean Escudo',
    'CYP': 'Cypriot Pound',
    'DJF': 'Djiboutian Franc',
    'DOP': 'Dominican Peso',
    'DZD': 'Algerian Dinar',
    'EGP': 'Egyptian Pound',
    'ERN': 'Eritrean Nakfa',
    'ETB': 'Ethiopian Birr',
    'FJD': 'Fijian Dollar',
    'FKP': 'Falkland Islands Pound',
    'GEL': 'Georgian Lari',
    'GGP': 'Guernsey Pound',
    'GHS': 'Ghanaian Cedi',
    'GIP': 'Gibraltar Pound',
    'GMD': 'Gambian Dalasi',
    'GNF': 'Guinean Franc',
    'GTQ': 'Guatemalan Quetzal',
    'GYD': 'Guyanaese Dollar',
    'HNL': 'Honduran Lempira',
    'HRK': 'Croatian Kuna',
    'HTG': 'Haitian Gourde',
    'HUF': 'Hungarian Forint',
    'IDR': 'Indonesian Rupiah',
    'ILS': 'Israeli New Shekel',
    'IMP': 'Isle of Man Pound',
    'IQD': 'Iraqi Dinar',
    'IRR': 'Iranian Rial',
    'ISK': 'Icelandic Króna',
    'JEP': 'Jersey Pound',
    'JMD': 'Jamaican Dollar',
    'JOD': 'Jordanian Dinar',
    'KES': 'Kenyan Shilling',
    'KGS': 'Kyrgystani Som',
    'KHR': 'Cambodian Riel',
    'KMF': 'Comorian Franc',
    'KPW': 'North Korean Won',
    'KWD': 'Kuwaiti Dinar',
    'KYD': 'Cayman Islands Dollar',
    'KZT': 'Kazakhstani Tenge',
    'LAK': 'Laotian Kip',
    'LBP': 'Lebanese Pound',
    'LKR': 'Sri Lankan Rupee',
    'LRD': 'Liberian Dollar',
    'LSL': 'Lesotho Loti',
    'LYD': 'Libyan Dinar',
    'MAD': 'Moroccan Dirham',
    'MDL': 'Moldovan Leu',
    'MGA': 'Malagasy Ariary',
    'MKD': 'Macedonian Denar',
    'MMK': 'Myanmar Kyat',
    'MNT': 'Mongolian Tugrik',
    'MOP': 'Macanese Pataca',
    'MRO': 'Mauritanian Ouguiya (pre-2018)',
    'MRU': 'Mauritanian Ouguiya',
    'MUR': 'Mauritian Rupee',
    'MVR': 'Maldivian Rufiyaa',
    'MWK': 'Malawian Kwacha',
    'MYR': 'Malaysian Ringgit',
    'MZN': 'Mozambican Metical',
    'NAD': 'Namibian Dollar',
    'NGN': 'Nigerian Naira',
    'NIO': 'Nicaraguan Córdoba',
    'NPR': 'Nepalese Rupee',
    'OMR': 'Omani Rial',
    'PAB': 'Panamanian Balboa',
    'PEN': 'Peruvian Nuevo Sol',
    'PGK': 'Papua New Guinean Kina',
    'PHP': 'Philippine Peso',
    'PKR': 'Pakistani Rupee',
    'PYG': 'Paraguayan Guarani',
    'QAR': 'Qatari Rial',
    'RON': 'Romanian Leu',
    'RSD': 'Serbian Dinar',
    'RWF': 'Rwandan Franc',
    'SAR': 'Saudi Riyal',
    'SBD': 'Solomon Islands Dollar',
    'SCR': 'Seychellois Rupee',
    'SDG': 'Sudanese Pound',
    'SHP': 'Saint Helena Pound',
    'SLL': 'Sierra Leonean Leone',
    'SOS': 'Somali Shilling',
    'SRD': 'Surinamese Dollar',
    'SSP': 'South Sudanese Pound',
    'STD': 'São Tomé and Príncipe Dobra (pre-2018)',
    'STN': 'São Tomé and Príncipe Dobra',
    'SVC': 'Salvadoran Colón',
    'SYP': 'Syrian Pound',
    'SZL': 'Swazi Lilangeni',
    'THB': 'Thai Baht',
    'TJS': 'Tajikistani Somoni',
    'TMT': 'Turkmenistani Manat',
    'TND': 'Tunisian Dinar',
    'TOP': 'Tongan Paʻanga',
    'TTD': 'Trinidad and Tobago Dollar',
    'TWD': 'New Taiwan Dollar',
    'TZS': 'Tanzanian Shilling',
    'UAH': 'Ukrainian Hryvnia',
    'UGX': 'Ugandan Shilling',
    'UYU': 'Uruguayan Peso',
    'UZS': 'Uzbekistan Som',
    'VEF': 'Venezuelan Bolívar Fuerte (Old)',
    'VES': 'Venezuelan Bolívar Soberano',
    'VND': 'Vietnamese Dong',
    'VUV': 'Vanuatu Vatu',
    'WST': 'Samoan Tala',
    'XAF': 'CFA Franc BEAC',
    'XAG': 'Silver Ounce',
    'XAU': 'Gold Ounce',
    'XCD': 'East Caribbean Dollar',
    'XDR': 'Special Drawing Rights',
    'XOF': 'CFA Franc BCEAO',
    'XPD': 'Palladium Ounce',
    'XPF': 'CFP Franc',
    'XPT': 'Platinum Ounce',
    'YER': 'Yemeni Rial',
    'ZMW': 'Zambian Kwacha',
    'ZWL': 'Zimbabwean Dollar'
};

// Most popular currencies (shown first)
const popularCurrencies = [
    'USD', 'EUR', 'JPY', 'GBP', 'CNY', 'AUD', 'CAD', 'CHF', 'HKD', 'SEK',
    'NZD', 'SGD', 'NOK', 'KRW', 'MXN', 'INR', 'RUB', 'BRL', 'ZAR', 'DKK',
    'CZK', 'PLN', 'TRY'
];

// Get available currencies sorted (popular first, then alphabetical)
const availableCurrencies = computed(() => {
    const allCurrencies = Object.keys(props.exchangeRates);
    const popular = allCurrencies.filter(c => popularCurrencies.includes(c)).sort((a, b) => {
        return popularCurrencies.indexOf(a) - popularCurrencies.indexOf(b);
    });
    const others = allCurrencies.filter(c => !popularCurrencies.includes(c)).sort();

    return [...popular, ...others];
});

// Selected currency (default to USD if available, otherwise first currency)
const selectedCurrency = ref(availableCurrencies.value.includes('USD') ? 'USD' : availableCurrencies.value[0]);

// Dropdown state
const isDropdownOpen = ref(false);
const dropdownButton = ref(null);
const dropdownPosition = ref({ top: 0, left: 0, width: 0 });

// Update dropdown position
const updateDropdownPosition = () => {
    if (dropdownButton.value) {
        const rect = dropdownButton.value.getBoundingClientRect();
        dropdownPosition.value = {
            top: rect.bottom + window.scrollY + 8,
            left: rect.left + window.scrollX,
            width: rect.width
        };
    }
};

// Toggle dropdown and update position
const toggleDropdown = () => {
    isDropdownOpen.value = !isDropdownOpen.value;
    if (isDropdownOpen.value) {
        updateDropdownPosition();
    }
};

// Close dropdown when clicking outside
const closeDropdown = (event) => {
    const dropdown = document.querySelector('.currency-dropdown');
    if (dropdown && !dropdown.contains(event.target)) {
        isDropdownOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdown);
    window.addEventListener('scroll', updateDropdownPosition);
    window.addEventListener('resize', updateDropdownPosition);
});

onUnmounted(() => {
    document.removeEventListener('click', closeDropdown);
    window.removeEventListener('scroll', updateDropdownPosition);
    window.removeEventListener('resize', updateDropdownPosition);
});

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

// --- Donation row presentation helpers -------------------------------------

// Split a note into text/link parts so URLs render as safe <a> tags (we never
// v-html user content).
const noteParts = (note) => {
    const parts = [];
    const re = /https?:\/\/[^\s]+/g;
    let last = 0, m;
    while ((m = re.exec(note)) !== null) {
        if (m.index > last) parts.push({ link: false, v: note.slice(last, m.index) });
        parts.push({ link: true, v: m[0] });
        last = m.index + m[0].length;
    }
    if (last < note.length) parts.push({ link: false, v: note.slice(last) });
    return parts;
};

// Prizes donated back from the DefragLive watch contest get a special badge
// (the note is machine-written by the admin "Resolve prize" action).
const isContestDonation = (note) => (note || '').startsWith('DefragLive contest prize donated back');

// Fallback avatar: initials on a hue picked from the donor's name, so rows
// without a linked account still get a distinct, stable color.
const donorInitials = (name) => (name || 'A').trim().slice(0, 2).toUpperCase();
const donorHue = (name) => {
    let h = 0;
    for (const c of (name || 'anon')) h = (h * 31 + c.charCodeAt(0)) % 360;
    return h;
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
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="text-center">
                    <h1 class="text-4xl font-bold text-white mb-4">Support Defrag Racing</h1>
                    <p class="text-xl text-gray-400">Help to keep defrag.racing projects running and the community thriving!</p>
                </div>
            </div>
        </div>

        <div class="relative z-10 max-w-7xl mx-auto px-4 pb-12" style="margin-top: -22rem;">

            <!-- Make a Donation -->
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5 mb-5 mx-auto text-center">
                <h2 class="text-2xl font-bold text-white mb-4">Make a Donation</h2>
                <p class="text-gray-400 mb-6">Your support helps cover defrag.racing projects and continue improving!</p>

                <!-- PayPal Donation Button -->
                <div class="max-w-md mx-auto">
                    <form action="https://www.paypal.com/donate" method="post" target="_top">
                        <input type="hidden" name="hosted_button_id" value="WH6GY4PDGU8FA" />
                        <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" class="mx-auto transform scale-150 hover:scale-[1.55] transition-transform" />
                        <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
                    </form>
                    <p class="text-sm text-gray-500 mt-6">Secure payment through PayPal</p>
                    <div class="mt-6 p-4 rounded-lg bg-pink-950/30 border border-pink-500/20 max-w-md mx-auto">
                        <div class="flex items-center gap-2 mb-1">
                            <img src="/images/svg/badge-donor.svg" class="w-5 h-5" alt="Supporter">
                            <span class="text-sm font-bold text-pink-300">Supporter Badge</span>
                        </div>
                        <p class="text-xs text-gray-400">Want your donation linked to your profile? Send <a href="/profile/8" class="text-blue-400 hover:text-blue-300 font-bold">me</a> a message on <a href="https://discordapp.com/users/248530770754625536" target="_blank" class="text-indigo-400 hover:text-indigo-300 font-bold">Discord</a> after donating and I'll link it to your account - you'll get a Supporter badge on your profile!</p>
                    </div>
                </div>
            </div>

            <!-- Current Year Progress -->
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5 mb-5 mx-auto">
                <div class="relative mb-2">
                    <h2 class="text-2xl font-bold text-white text-center">{{ currentYear }} Progress</h2>

                    <!-- Currency Selector Dropdown -->
                    <div class="absolute right-0 top-0 currency-dropdown">
                        <button
                            ref="dropdownButton"
                            @click="toggleDropdown"
                            class="px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white hover:bg-blue-700 transition-all flex items-center gap-2"
                        >
                            {{ selectedCurrency }}
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Exchange Rate Disclaimer -->
                        <div class="absolute right-0 top-full mt-1 text-xs text-gray-500 whitespace-nowrap">
                            Rates from <a href="https://exchangerate-api.com" target="_blank" class="text-blue-400 hover:text-blue-300 underline">exchangerate-api.com</a>
                        </div>

                        <!-- Dropdown Menu (Teleported to body) -->
                        <Teleport to="body">
                            <div
                                v-if="isDropdownOpen"
                                class="fixed w-80 max-h-96 overflow-y-auto bg-black/90 rounded-lg border border-white/10 shadow-2xl z-[9999]"
                                :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left + 'px' }"
                            >
                                <!-- Popular Currencies Header (not sticky) -->
                                <div class="bg-gradient-to-b from-blue-600/20 to-blue-600/10 border-b border-blue-500/30 px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                        </svg>
                                        <span class="text-xs font-semibold text-blue-400 uppercase tracking-wide">Popular Currencies</span>
                                    </div>
                                </div>

                                <!-- Popular Currencies List -->
                                <div v-for="currency in availableCurrencies" :key="currency">
                                    <button
                                        @click="selectedCurrency = currency; isDropdownOpen = false"
                                        :class="[
                                            'w-full px-4 py-2 text-left text-sm transition-colors flex items-center justify-between gap-2',
                                            selectedCurrency === currency
                                                ? 'bg-blue-600 text-white font-semibold'
                                                : 'text-gray-300 hover:bg-white/10',
                                            popularCurrencies.includes(currency) ? 'bg-blue-500/5' : ''
                                        ]"
                                    >
                                        <span class="flex items-center gap-2">
                                            <svg v-if="popularCurrencies.includes(currency)" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-blue-400">
                                                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" />
                                            </svg>
                                            <span class="font-mono font-bold">{{ currency }}</span>
                                        </span>
                                        <span class="text-xs opacity-70">{{ currencyNames[currency] || currency }}</span>
                                    </button>

                                    <!-- Separator and All Currencies Header (sticky) -->
                                    <div
                                        v-if="popularCurrencies.includes(currency) && availableCurrencies.indexOf(currency) === popularCurrencies.filter(c => availableCurrencies.includes(c)).length - 1"
                                        class="sticky top-0 border-t border-white/20 bg-black/95"
                                    >
                                        <div class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wide flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                            </svg>
                                            All Currencies
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
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
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5 mb-5 mx-auto">
                <h2 class="text-2xl font-bold text-white mb-4 text-center">Where Your Support Goes</h2>
                <div class="max-w-3xl mx-auto space-y-4 text-gray-300">
                    <div id="operational-costs">
                        <h3 class="text-lg font-semibold text-white mb-2">Operational Costs (~€1,200/year)</h3>
                        <p class="text-sm leading-relaxed mb-3">
                            Donations help cover: servers, hosting, vps's, backblaze, domains, DemoMe, DefragLive, isp, electricity.
                        </p>
                        <p class="text-sm leading-relaxed text-gray-400">
                            Any future excess donations would go towards tournament prizepools, new servers in underserved locations, and other community-driven initiatives.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-white mb-2">A Brief History</h3>
                        <p class="text-sm leading-relaxed">
                            All developers who worked on these projects since 2021 were fairly compensated. In early 2024, the last paid developer unexpectedly departed without response,
                            leaving everything as-is. At that point, I decided to stop paying for development as it felt like progress had stalled. With Batawi's help, I made defrag-racing and related repositories <a href="https://github.com/Defrag-racing/" target="_blank" class="text-blue-400 hover:text-blue-300 underline">open-source</a>, inviting the community to contribute.
                            Development has been in limbo since, as no one had the availability or free time to contribute. Around August 2025, I've taken it upon myself with AI assistance to continue development independently.
                            Mind you, I never had any prior knowledge with coding, but I made it work and I am learning everyday as I go, bringing you a fully functional <a href="https://twitch.tv/defraglive" target="_blank" class="text-purple-400 hover:text-purple-300 underline">DefragLive</a> with extension, improved defrag.racing, and more updates coming soon - <a :href="route('roadmap')" class="text-blue-400 hover:text-blue-300 underline">Roadmap</a>.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-white mb-2">What I Cover Out-of-Pocket</h3>
                        <p class="text-sm leading-relaxed">
                            Hardware costs (dedicated PC for DemoMe+DefragLive, EU server PC) and other expenses remain out-of-pocket.
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

            <!-- Donation History by Year -->
            <div class="space-y-4">
                <h2 class="text-3xl font-bold text-white text-center mb-6">Donation History</h2>

                <div v-for="yearData in groupedByYear" :key="yearData.year" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5 mb-5 mx-auto">
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
                            <div class="flex flex-col items-end gap-2">
                                <div class="text-4xl font-black text-green-400 tabular-nums leading-none drop-shadow-[0_0_12px_rgba(34,197,94,0.35)]">
                                    {{ currencySymbol }}{{ yearData.total.toFixed(2) }}
                                </div>
                                <div class="flex gap-2">
                                    <span v-if="yearData.donationsTotal > 0"
                                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-green-500/10 border border-green-500/25 text-xs font-semibold text-green-300 tabular-nums">
                                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                                        {{ currencySymbol }}{{ yearData.donationsTotal.toFixed(2) }} crowd
                                    </span>
                                    <span v-if="yearData.selfRaisedTotal > 0"
                                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-500/10 border border-purple-500/25 text-xs font-semibold text-purple-300 tabular-nums">
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
                                class="absolute h-full bg-gradient-to-r from-green-600 to-emerald-400 transition-all duration-1000"
                                :style="{ width: ((yearData.donationsTotal / getYearGoal(yearData.year).amount) * 100) + '%' }"
                            ></div>
                            <!-- Self-raised portion (purple) -->
                            <div
                                v-if="yearData.selfRaisedTotal > 0"
                                class="absolute h-full bg-gradient-to-r from-purple-600 to-fuchsia-400 transition-all duration-1000"
                                :style="{ left: ((yearData.donationsTotal / getYearGoal(yearData.year).amount) * 100) + '%', width: ((yearData.selfRaisedTotal / getYearGoal(yearData.year).amount) * 100) + '%' }"
                            ></div>
                            <!-- Percentage label -->
                            <div class="absolute inset-0 flex items-center justify-end pr-3">
                                <span class="text-white font-black text-sm tabular-nums drop-shadow">{{ getYearProgress(yearData.year, yearData.total).percentage }}%</span>
                            </div>
                        </div>
                        <div class="text-xs font-semibold text-gray-400 mt-1.5 text-center tabular-nums">
                            Goal: {{ currencySymbol }}{{ getYearProgress(yearData.year, yearData.total).goal }}
                        </div>
                    </div>

                    <!-- Expandable Content -->
                    <div v-if="isYearExpanded(yearData.year)">
                        <!-- Donations -->
                        <div v-if="yearData.donations.length > 0" class="mb-4">
                            <div class="flex items-center gap-2.5 mb-3">
                                <span class="w-1 h-5 rounded-full bg-green-500"></span>
                                <h4 class="text-base font-black text-white uppercase tracking-wider">Donations</h4>
                                <span class="px-2 py-0.5 rounded-full bg-green-500/10 border border-green-500/25 text-xs font-semibold text-green-300 tabular-nums">{{ yearData.donations.length }}</span>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="donation in yearData.donations"
                                    :key="donation.id"
                                    class="flex items-start gap-3 p-3 bg-black/30 rounded-xl border transition-colors"
                                    :class="isContestDonation(donation.note)
                                        ? 'border-purple-500/30 hover:border-purple-400/50'
                                        : 'border-green-500/20 hover:border-green-400/40'"
                                >
                                    <!-- Avatar: profile photo for linked accounts, tinted initials otherwise -->
                                    <component :is="donation.user ? Link : 'div'"
                                        :href="donation.user ? `/profile/${donation.user.id}` : undefined"
                                        class="shrink-0">
                                        <img v-if="donation.user?.profile_photo_path"
                                            :src="'/storage/' + donation.user.profile_photo_path"
                                            class="w-10 h-10 rounded-full object-cover ring-2 ring-white/10" alt="">
                                        <div v-else
                                            class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-black text-white/90 ring-2 ring-white/10"
                                            :style="{ background: `linear-gradient(135deg, hsl(${donorHue(donation.donor_name)}, 45%, 32%), hsl(${(donorHue(donation.donor_name) + 40) % 360}, 45%, 22%))` }">
                                            {{ donorInitials(donation.donor_name) }}
                                        </div>
                                    </component>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <component :is="donation.user ? Link : 'span'"
                                                :href="donation.user ? `/profile/${donation.user.id}` : undefined"
                                                class="font-bold text-white truncate" :class="donation.user ? 'hover:underline' : ''">
                                                {{ donation.donor_name || 'Anonymous Donor' }}
                                            </component>
                                            <Link v-if="isContestDonation(donation.note)" href="/defraglive/contest"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-500/15 border border-purple-400/30 text-[11px] font-semibold text-purple-300 hover:bg-purple-500/25 transition-colors">
                                                🎁 DefragLive prize
                                            </Link>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5">{{ formatDate(donation.donation_date) }}</div>
                                        <div v-if="donation.note" class="mt-1.5 px-3 py-1.5 rounded-lg bg-white/5 text-sm text-gray-300">
                                            <template v-for="(part, pi) in noteParts(donation.note)" :key="pi">
                                                <a v-if="part.link" :href="part.v" class="text-blue-400 hover:text-blue-300 underline break-all">{{ part.v }}</a>
                                                <span v-else>{{ part.v }}</span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="text-lg font-black text-green-400 shrink-0 tabular-nums text-right min-w-[6.5rem]">
                                        {{ currencySymbol }}{{ convertCurrency(donation.amount, donation.currency).toFixed(2) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Self-Raised Money -->
                        <div v-if="yearData.selfRaised.length > 0">
                            <div class="flex items-center gap-2.5 mb-3 mt-6 pt-5 border-t border-white/10">
                                <span class="w-1 h-5 rounded-full bg-purple-500"></span>
                                <h4 class="text-base font-black text-white uppercase tracking-wider">Self-Raised</h4>
                                <span class="text-xs text-gray-500 font-semibold">YouTube / Twitch</span>
                                <span class="px-2 py-0.5 rounded-full bg-purple-500/10 border border-purple-500/25 text-xs font-semibold text-purple-300 tabular-nums">{{ yearData.selfRaised.length }}</span>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="money in yearData.selfRaised"
                                    :key="money.id"
                                    class="flex items-start gap-3 p-3 bg-black/30 rounded-xl border border-purple-500/20 hover:border-purple-400/40 transition-colors"
                                >
                                    <!-- Source icon: brand-tinted circle (YouTube red, Twitch purple) -->
                                    <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center ring-2 ring-white/10"
                                        :class="(money.source || '').toLowerCase().includes('youtube')
                                            ? 'bg-red-600/25 text-red-300'
                                            : (money.source || '').toLowerCase().includes('twitch')
                                                ? 'bg-purple-600/25 text-purple-300'
                                                : 'bg-white/10 text-gray-300'">
                                        <svg v-if="(money.source || '').toLowerCase().includes('youtube')" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.6 15.6V8.4L15.8 12l-6.2 3.6z"/></svg>
                                        <svg v-else-if="(money.source || '').toLowerCase().includes('twitch')" class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11.6 4.7h1.7v5h-1.7m4.6-5h1.7v5h-1.7M7 0 2.8 4.2v15.1h5V24l4.2-4.7h3.4L23 11.8V0M21.3 11 18 14.4h-3.4l-3 3v-3H7.8V1.7h13.5z"/></svg>
                                        <span v-else class="font-black text-sm">{{ (money.source || '?').slice(0, 1).toUpperCase() }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-white truncate">{{ money.source }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">{{ formatDate(money.earned_date) }}</div>
                                    </div>
                                    <div class="text-lg font-black text-purple-400 shrink-0 tabular-nums text-right min-w-[6.5rem]">
                                        {{ currencySymbol }}{{ convertCurrency(money.amount, money.currency).toFixed(2) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All-Time Total -->
            <div class="mt-12 bg-black/60 border border-white/10 rounded-xl p-8 text-center">
                <h2 class="text-2xl font-bold text-white mb-4">All-Time Total (since 2020)</h2>

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
