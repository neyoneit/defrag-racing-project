<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Teleport } from 'vue';
import { currentLocale } from '@/utils/i18n';

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

// EUR by default. The goal, the bills and the comps prize pool are all
// denominated in EUR, and the progress bar in the header shows EUR, so opening
// this page in dollars meant the same money carried two different numbers
// depending on where you read it.
const selectedCurrency = ref(availableCurrencies.value.includes('EUR') ? 'EUR' : availableCurrencies.value[0]);

// Dropdown state
const isDropdownOpen = ref(false);
const dropdownButton = ref(null);
const dropdownPosition = ref({ top: 0, left: 0, width: 0 });

// Where to put the dropdown.
//
// The panel is position:fixed, so it wants viewport coordinates, and
// getBoundingClientRect already gives them. Adding the scroll offset turned
// them into document coordinates: the further down the page you were, the
// further below the button the panel opened.
const DROPDOWN_WIDTH = 320;
const DROPDOWN_MAX_HEIGHT = 384;

const updateDropdownPosition = () => {
    if (!dropdownButton.value) return;

    const rect = dropdownButton.value.getBoundingClientRect();
    const margin = 8;

    // The button sits on the right of its panel and the list is wider than
    // the button, so aligning the left edges pushes it off the screen. Hang it
    // off the right edge instead, then keep it on screen either way.
    let left = rect.right - DROPDOWN_WIDTH;
    left = Math.min(left, window.innerWidth - DROPDOWN_WIDTH - margin);
    left = Math.max(margin, left);

    // Below the button, unless there is no room below and more room above.
    const below = window.innerHeight - rect.bottom - margin;
    const above = rect.top - margin;
    const dropUp = below < Math.min(DROPDOWN_MAX_HEIGHT, above) && above > below;

    dropdownPosition.value = {
        top: dropUp
            ? Math.max(margin, rect.top - Math.min(DROPDOWN_MAX_HEIGHT, above) - margin)
            : rect.bottom + margin,
        left,
        width: rect.width,
    };
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

// The link that jumps here sits inside a translated sentence rendered with
// v-html, so its click is caught on the paragraph rather than on the anchor.
const onOperationalCostsLink = (event) => {
    if (!event.target.closest('a[href="#operational-costs"]')) {
        return;
    }

    event.preventDefault();
    highlightOperationalCosts();
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

// Part of a donation that was earmarked for the comps prize pool, in the
// currency the page is showing. Stored in EUR whatever the donation was made
// in, because a prize pool is promised in EUR.
const compsPart = (donation) => convertCurrency(Number(donation.comps_amount || 0), 'EUR');

// What a donation contributed towards running the site: everything it was not
// promised to somebody as prize money.
//
// The goal on this page is a hosting and upkeep bill. Counting a prize pool
// towards it would show the year as covered while the bills are still short by
// exactly the amount already promised to a winner - so the split happens here,
// once, and every total below is built from it.
const maintenancePart = (donation) =>
    Math.max(0, convertCurrency(donation.amount, donation.currency) - compsPart(donation));

// Everything given to comps prizes, all time, in the selected currency.
const compsTotal = computed(() =>
    props.donations.reduce((sum, donation) => sum + compsPart(donation), 0),
);

// The same figure in EUR, which is the only currency comps is denominated in:
// a donation earmarked for the pool is recorded in EUR, the pool is quoted in
// EUR and the winners are paid in EUR. Converting it into whatever the reader
// picked from the dropdown produced "$116.00" next to a comps page saying 100
// EUR - the same money, two numbers, and no way to tell they were the same.
const compsTotalEur = computed(() =>
    props.donations.reduce((sum, donation) => sum + Number(donation.comps_amount || 0), 0),
);

// Calculate total in selected currency
const totalRaised = computed(() => {
    let total = 0;

    props.donations.forEach(donation => {
        total += maintenancePart(donation);
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
        total += maintenancePart(donation);
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
            total += maintenancePart(donation);
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
            total += maintenancePart(donation);
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
        // The per-year totals feed that year's goal bar, so they carry the
        // maintenance part only - the card below still shows what the person
        // actually gave.
        const amount = maintenancePart(donation);
        groups[year].donationsTotal += amount;
        groups[year].total += amount;
        groups[year].compsTotal = (groups[year].compsTotal || 0) + compsPart(donation);
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
    return new Date(dateString).toLocaleDateString(currentLocale(), {
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
    <Head :title="$t('Support Defrag Racing')" />

    <div>
        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="text-center">
                    <h1 class="text-2xl md:text-3xl font-bold text-white mb-4">{{ $t('Support Defrag Racing') }}</h1>
                    <p class="text-xl text-gray-400">{{ $t('Help to keep defrag.racing projects running and the community thriving!') }}</p>
                </div>
            </div>
        </div>

        <!-- Same container as the header above it and as every other page:
             max-w-8xl with the site's padding. It used to be max-w-7xl with a
             flat px-4, so the content sat in a column narrower than its own
             heading and stepped in at the edges. -->
        <div class="relative z-10 max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12" style="margin-top: -22rem;">

            <!-- The donate box and the year's progress share a row from lg.
                 Each on its own was a small centred thing in a box the width of
                 the page, which is what made this read as spread out rather
                 than as full. -->
            <!-- Stretched, not top-aligned: the donate box holds a button and two
                 notes, the panel beside it holds an essay, and left to their own
                 heights the short one sat in the top corner of an empty field. -->
            <div class="grid gap-5 lg:grid-cols-2 mb-5">

            <!-- Make a Donation -->
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5 mx-auto w-full h-full flex flex-col justify-center text-center">
                <h2 class="text-3xl font-bold text-white mb-3">{{ $t('Make a Donation') }}</h2>
                <p class="text-lg text-gray-300 mb-8">{{ $t('Your support helps cover defrag.racing projects and continue improving!') }}</p>

                <!-- PayPal Donation Button -->
                <div class="max-w-xl mx-auto w-full">
                    <form action="https://www.paypal.com/donate" method="post" target="_top">
                        <input type="hidden" name="hosted_button_id" value="WH6GY4PDGU8FA" />
                        <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" :title="$t('PayPal - The safer, easier way to pay online!')" :alt="$t('Donate with PayPal button')" class="mx-auto transform scale-[1.75] hover:scale-[1.8] transition-transform" />
                        <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
                    </form>
                    <p class="text-base text-gray-500 mt-8">{{ $t('Secure payment through PayPal') }}</p>

                    <!-- Where the money goes is the donor's call, and until now
                         the page never said so. A donation can pay for the site
                         or for the comps prize pool, or be split between them -
                         but the pool is paid out one week at a time, so a lump
                         sum is meaningless without the two facts only the donor
                         knows: how much of it is for comps, and over how many
                         weeks it should last. Asked for here rather than worked
                         out later by whoever received it. -->
                    <div class="mt-6 p-4 rounded-lg bg-emerald-950/30 border border-emerald-500/20 w-full text-left">
                        <div class="flex items-center gap-1.5 mb-1">
                            <svg class="w-6 h-6 flex-shrink-0 text-emerald-400" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2H6v2H2v3a5 5 0 0 0 4.6 5A5.5 5.5 0 0 0 11 15.9V19H7v3h10v-3h-4v-3.1a5.5 5.5 0 0 0 4.4-3.9A5 5 0 0 0 22 7V4h-4V2zM4 7V6h2v3.9A3 3 0 0 1 4 7zm16 0a3 3 0 0 1-2 2.9V6h2v1z" /></svg>
                            <Link :href="route('comps.index')" class="text-base font-bold text-emerald-300 hover:text-emerald-200">{{ $t('Want it to go to the comps prize pool?') }}</Link>
                        </div>
                        <p class="text-sm leading-relaxed text-gray-300">
                            {{ $t('You decide what your donation pays for. Write "comps" in the PayPal note to send all or part of it to the weekly prize pool, and say how much and over how many weeklies it should be spread.') }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-400 mt-2 [&_a]:font-bold [&_a]:text-emerald-300 [&_a:hover]:text-emerald-200"
                           v-html="$t('Not sure, or forgot to write it? Message <a href=/profile/8>the admin</a> on <a href=https://discordapp.com/users/248530770754625536 target=_blank>Discord</a> and it gets sorted.')"></p>
                    </div>

                    <!-- The one thing a donation buys outright. Every demo
                         downloaded is bandwidth that is paid for, which is why
                         there is a limit at all and why this is a high ceiling
                         rather than none. Said here because the demos page
                         points at this page and used to promise something the
                         site did not deliver. -->
                    <div class="mt-4 p-4 rounded-lg bg-blue-950/30 border border-blue-500/20 w-full text-left">
                        <div class="flex items-center gap-1.5 mb-1">
                            <svg class="w-6 h-6 flex-shrink-0 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <Link :href="route('demos.index')" class="text-base font-bold text-blue-300 hover:text-blue-200">{{ $t('More demo downloads') }}</Link>
                        </div>
                        <p class="text-sm leading-relaxed text-gray-300">
                            {{ $t('An account downloads :member demos a day. Donate :amount or more, all donations counted together, and that becomes :donor a day.', { member: 50, amount: '€10', donor: 500 }) }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-400 mt-2">
                            {{ $t('Every demo downloaded is bandwidth I pay for, which is why there is a limit at all and why this is a high ceiling rather than none. It applies as soon as the donation is linked to your account.') }}
                        </p>
                    </div>

                    <div class="mt-4 p-4 rounded-lg bg-pink-950/30 border border-pink-500/20 w-full text-left">
                        <div class="flex items-center gap-2 mb-1">
                            <img src="/images/svg/badge-donor.svg" class="w-6 h-6" :alt="$t('Supporter')">
                            <span class="text-base font-bold text-pink-300">{{ $t('Supporter Badge') }}</span>
                        </div>
                        <p class="text-sm leading-relaxed text-gray-300 [&_a]:font-bold [&_a:first-of-type]:text-blue-400 [&_a:first-of-type:hover]:text-blue-300 [&_a:last-of-type]:text-indigo-400 [&_a:last-of-type:hover]:text-indigo-300"
                            v-html="$t('Want your donation linked to your profile? Send <a href=/profile/8>admin</a> a message on <a href=https://discordapp.com/users/248530770754625536 target=_blank>Discord</a> after donating and it gets linked to your account - you get a Supporter badge on your profile!')"></p>
                    </div>
                </div>
            </div>

            <!-- Where Donations Go -->
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5 mx-auto w-full">
                <h2 class="text-2xl font-bold text-white mb-4 text-center">{{ $t('Where Your Support Goes') }}</h2>
                <div class="space-y-5 text-gray-300">
                    <div id="operational-costs">
                        <h3 class="text-lg font-semibold text-white mb-2">{{ $t('Operational Costs (~€1,200/year)') }}</h3>
                        <p class="text-sm leading-relaxed mb-3">
                            {{ $t('Donations help cover: servers, hosting, vps\'s, backblaze, domains, DemoMe, DefragLive, isp, electricity.') }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-400">
                            {{ $t('Any future excess donations would go towards tournament prizepools, new servers in underserved locations, and other community-driven initiatives.') }}
                        </p>
                    </div>

                    <!-- Said plainly and said first. It was in the history
                         below, halfway through a paragraph about developers who
                         left in 2024, where it read as background rather than
                         as the thing to know before asking for something. -->
                    <div class="rounded-xl bg-white/[0.07] border border-white/25 ring-1 ring-white/10 p-5 shadow-2xl">
                        <h3 class="text-xl font-bold text-white mb-3 flex items-center gap-2">
                            <svg class="w-6 h-6 flex-shrink-0 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            {{ $t('One person does all of this') }}
                        </h3>
                        <p class="text-sm leading-relaxed text-gray-300">
                            {{ $t('The site, the launcher, DemoMe and this page were built by me and by nobody else. So was DefragLive, apart from its early parts, which frog wrote. Nobody is paid and there is no team.') }}
                        </p>
                        <!-- The bit people never ask about and always wonder.
                             Said in the brightest text in the block, because a
                             page asking for money owes the answer plainly. -->
                        <p class="text-sm leading-relaxed text-gray-100 font-semibold mt-2">
                            {{ $t('No donation goes into my pocket. Not a cent of it. It pays the running costs and nothing else.') }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-400 mt-2">
                            {{ $t('It goes the other way as well: things like the DefragLive contest prizes come out of my own pocket.') }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-400 mt-2">
                            {{ $t('Almost every public defrag server is maintained by me too, but their owners pay for them - the 10 Gbit servers are the ones I pay for.') }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-400 mt-2">
                            {{ $t('So please be patient with anything you ask for. It gets done when I get to it, and your donation pays the bills rather than buying a place in the queue.') }}
                        </p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-white mb-2">{{ $t('A Brief History') }}</h3>
                        <p class="text-sm leading-relaxed">
                            {{ $t('All developers who worked on these projects since 2021 were fairly compensated. In early 2024, the last paid developer unexpectedly departed without response, leaving everything as-is. At that point, I decided to stop paying for development as it felt like progress had stalled.') }}
                            <span class="[&_a]:underline [&_a]:text-blue-400 [&_a:hover]:text-blue-300"
                                v-html="$t('With Batawi\'s help, I made defrag-racing and related repositories <a href=https://github.com/Defrag-racing/ target=_blank>open-source</a>, inviting the community to contribute.')"></span>
                            {{ $t('Development has been in limbo since, as no one had the availability or free time to contribute. Around August 2025, I\'ve taken it upon myself with AI assistance to continue development independently.') }}
                            <span class="[&_a]:underline [&_a]:text-blue-400 [&_a:hover]:text-blue-300"
                                v-html="$t('Mind you, I never had any prior knowledge with coding, but I made it work and I am learning everyday as I go, bringing you a fully functional <a href=https://twitch.tv/defraglive target=_blank>DefragLive</a> with extension, the <a href=/bundles/7/defrag-launcher>Defrag Launcher</a>, improved defrag.racing, and more updates coming soon - <a href=:roadmap>Roadmap</a>.', { roadmap: route('roadmap') })"></span>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-white mb-2">{{ $t('What admin covers out-of-pocket') }}</h3>
                        <p class="text-sm leading-relaxed [&_a]:underline [&_a]:cursor-pointer [&_a]:text-blue-400 [&_a:hover]:text-blue-300"
                            @click="onOperationalCostsLink"
                            v-html="$t('Hardware costs (dedicated PC for DemoMe+DefragLive, EU server PC) and other expenses remain out-of-pocket. Your donations help offset <a href=#operational-costs>operational costs</a>.')"></p>
                    </div>

                    <div class="border-t border-white/10 pt-4">
                        <h3 class="text-lg font-semibold text-blue-300 mb-3">{{ $t('How You Can Help Without Donating Money') }}</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex gap-2">
                                <span class="text-blue-400">•</span>
                                <span v-html="$t('<strong>Allow DefragLive to spectate you</strong> - The more viewers it gets, the less I have to pay from my own pocket')"></span>
                            </li>
                            <li class="flex gap-2">
                                <span class="text-blue-400">•</span>
                                <span v-html="$t('<strong>Spread the word about DefragLegends YouTube channel</strong> - The channel is missing 1,600 yearly watch hours to get it monetized. Playing it in the background could help!')"></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            </div>

            <!-- Current Year Progress -->
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5 mb-5 mx-auto">
                <div class="relative mb-2">
                    <h2 class="text-2xl font-bold text-white text-center">{{ $t(':year Progress', { year: currentYear }) }}</h2>

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
                            {{ $t('Rates from') }} <a href="https://exchangerate-api.com" target="_blank" class="text-blue-400 hover:text-blue-300 underline">exchangerate-api.com</a>
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
                                        <span class="text-xs font-semibold text-blue-400 uppercase tracking-wide">{{ $t('Popular Currencies') }}</span>
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
                                            {{ $t('All Currencies') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Teleport>
                    </div>
                </div>

                <p class="text-xs text-gray-500 text-center mb-4 italic">{{ $t('Manually updated sporadically, not in real-time') }}</p>

                <!-- Full width of the panel: a bar is a picture of a number, and it
                     reads better long than it does narrow. -->
                <div>
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
                            <div class="text-sm text-gray-400 mt-1">{{ $t('Raised This Year') }}</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-blue-400">{{ currencySymbol }}{{ goalAmount }}</div>
                            <div class="text-sm text-gray-400 mt-1">{{ $t('Yearly Goal') }}</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-orange-400">{{ currencySymbol }}{{ monthlyGoal }}</div>
                            <div class="text-sm text-gray-400 mt-1">{{ $t('Monthly Goal') }}</div>
                        </div>
                    </div>

                    <!-- Money promised to comps winners is not progress towards
                         the hosting bill, so it is not in the bar. Saying so is
                         not optional: without this line the totals simply come
                         out lower than the donations listed below add up to,
                         and the page looks like it is losing money. -->
                    <div v-if="compsTotal > 0"
                         class="mt-5 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 rounded-xl border border-emerald-400/25 bg-emerald-500/10 px-4 py-3 text-sm">
                        <span class="font-bold text-emerald-300">{{ compsTotalEur.toFixed(2) }} EUR</span>
                        <span class="text-emerald-100/80">{{ $t('of the donations above went to the comps prize pool and is not counted towards the goal.') }}</span>
                        <Link :href="route('comps.index')" class="font-semibold text-emerald-300 underline decoration-emerald-400/40 hover:text-emerald-200">{{ $t('See comps') }}</Link>
                    </div>
                </div>
            </div>

            <!-- Donation History by Year -->
            <div class="space-y-4">
                <h2 class="text-3xl font-bold text-white text-center mb-6">{{ $t('Donation History') }}</h2>

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
                                        {{ currencySymbol }}{{ yearData.donationsTotal.toFixed(2) }} {{ $t('crowd') }}
                                    </span>
                                    <span v-if="yearData.selfRaisedTotal > 0"
                                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-500/10 border border-purple-500/25 text-xs font-semibold text-purple-300 tabular-nums">
                                        <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                                        {{ currencySymbol }}{{ yearData.selfRaisedTotal.toFixed(2) }} {{ $t('self') }}
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
                            {{ $t('Goal: :amount', { amount: currencySymbol + getYearProgress(yearData.year, yearData.total).goal }) }}
                        </div>
                    </div>

                    <!-- Expandable Content -->
                    <div v-if="isYearExpanded(yearData.year)">
                        <!-- Donations -->
                        <div v-if="yearData.donations.length > 0" class="mb-4">
                            <div class="flex items-center gap-2.5 mb-3">
                                <span class="w-1 h-5 rounded-full bg-green-500"></span>
                                <h4 class="text-base font-black text-white uppercase tracking-wider">{{ $t('Donations') }}</h4>
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
                                                {{ donation.donor_name || $t('Anonymous Donor') }}
                                            </component>
                                            <Link v-if="isContestDonation(donation.note)" href="/defraglive/contest"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-500/15 border border-purple-400/30 text-[11px] font-semibold text-purple-300 hover:bg-purple-500/25 transition-colors">
                                                🎁 {{ $t('DefragLive prize') }}
                                            </Link>
                                            <!-- The amount on the right is what
                                                 was given; this says where part
                                                 of it went, so the card and the
                                                 goal total below stop looking
                                                 like they disagree. -->
                                            <Link v-if="compsPart(donation) > 0" :href="route('comps.index')"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-[11px] font-semibold text-emerald-300 hover:bg-emerald-500/25 transition-colors">
                                                🏆 {{ $t(':amount to the comps prize pool', { amount: currencySymbol + compsPart(donation).toFixed(2) }) }}
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
                                <h4 class="text-base font-black text-white uppercase tracking-wider">{{ $t('Self-Raised') }}</h4>
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
                <h2 class="text-2xl font-bold text-white mb-4">{{ $t('All-Time Total (since 2020)') }}</h2>

                <!-- Progress Bar showing split -->
                <div class="mb-6">
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
                            {{ currencySymbol }}{{ allTimeDonationsTotal.toFixed(2) }} {{ $t('crowd') }}
                        </span>
                        <span v-if="allTimeSelfRaisedTotal > 0" class="flex items-center gap-1">
                            <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                            {{ currencySymbol }}{{ allTimeSelfRaisedTotal.toFixed(2) }} {{ $t('self') }}
                        </span>
                    </div>
                </div>

                <div class="text-5xl font-bold text-green-400">{{ currencySymbol }}{{ totalRaised }}</div>
                <p class="text-gray-400 mt-4">{{ $t('Thank you to everyone who has supported defrag.racing projects!') }}</p>
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
