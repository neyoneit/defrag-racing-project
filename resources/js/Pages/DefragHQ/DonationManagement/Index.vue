<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';

const props = defineProps({
    donations: Array,
    selfRaisedMoney: Array,
    goal: Object,
    currentYear: Number,
});

// Forms
const donationForm = useForm({
    donor_name: '',
    amount: '',
    currency: 'EUR',
    donation_date: new Date().toISOString().split('T')[0],
    note: '',
});

const selfRaisedForm = useForm({
    source: '',
    amount: '',
    currency: 'EUR',
    earned_date: new Date().toISOString().split('T')[0],
    description: '',
});

const goalForm = useForm({
    year: props.currentYear,
    yearly_goal: props.goal?.yearly_goal || 1200,
    currency: props.goal?.currency || 'EUR',
});

// State
const editingDonation = ref(null);
const editingSelfRaised = ref(null);
const showDonationForm = ref(false);
const showSelfRaisedForm = ref(false);

// Submit donation
const submitDonation = () => {
    if (editingDonation.value) {
        donationForm.put(route('defraghq.donations.update', editingDonation.value.id), {
            onSuccess: () => {
                resetDonationForm();
            }
        });
    } else {
        donationForm.post(route('defraghq.donations.store'), {
            onSuccess: () => {
                resetDonationForm();
            }
        });
    }
};

// Submit self-raised money
const submitSelfRaised = () => {
    if (editingSelfRaised.value) {
        selfRaisedForm.put(route('defraghq.selfraised.update', editingSelfRaised.value.id), {
            onSuccess: () => {
                resetSelfRaisedForm();
            }
        });
    } else {
        selfRaisedForm.post(route('defraghq.selfraised.store'), {
            onSuccess: () => {
                resetSelfRaisedForm();
            }
        });
    }
};

// Submit goal update
const submitGoal = () => {
    goalForm.post(route('defraghq.goal.update'), {
        preserveScroll: true,
    });
};

// Edit donation
const editDonation = (donation) => {
    editingDonation.value = donation;
    donationForm.donor_name = donation.donor_name || '';
    donationForm.amount = donation.amount;
    donationForm.currency = donation.currency;
    donationForm.donation_date = donation.donation_date;
    donationForm.note = donation.note || '';
    showDonationForm.value = true;
};

// Edit self-raised
const editSelfRaised = (item) => {
    editingSelfRaised.value = item;
    selfRaisedForm.source = item.source;
    selfRaisedForm.amount = item.amount;
    selfRaisedForm.currency = item.currency;
    selfRaisedForm.earned_date = item.earned_date;
    selfRaisedForm.description = item.description || '';
    showSelfRaisedForm.value = true;
};

// Delete donation
const deleteDonation = (id) => {
    if (confirm('Are you sure you want to delete this donation?')) {
        router.delete(route('defraghq.donations.delete', id));
    }
};

// Delete self-raised
const deleteSelfRaised = (id) => {
    if (confirm('Are you sure you want to delete this entry?')) {
        router.delete(route('defraghq.selfraised.delete', id));
    }
};

// Reset forms
const resetDonationForm = () => {
    donationForm.reset();
    editingDonation.value = null;
    showDonationForm.value = false;
};

const resetSelfRaisedForm = () => {
    selfRaisedForm.reset();
    editingSelfRaised.value = null;
    showSelfRaisedForm.value = false;
};

// Calculate totals
const totalDonations = computed(() => {
    return props.donations.reduce((sum, d) => {
        if (d.currency === 'EUR') return sum + parseFloat(d.amount);
        return sum;
    }, 0).toFixed(2);
});

const totalSelfRaised = computed(() => {
    return props.selfRaisedMoney.reduce((sum, m) => {
        if (m.currency === 'EUR') return sum + parseFloat(m.amount);
        return sum;
    }, 0).toFixed(2);
});
</script>

<template>
    <Head title="Donation Management - DefragHQ" />

    <MainLayout>
        <div class="max-w-7xl mx-auto py-8 px-4">
            <h1 class="text-3xl font-bold text-white mb-8">Donation Management</h1>

            <!-- Donation Goal Section -->
            <div class="bg-gray-800/50 backdrop-blur-sm border border-white/10 rounded-xl p-6 mb-8">
                <h2 class="text-xl font-bold text-white mb-4">Yearly Goal</h2>
                <form @submit.prevent="submitGoal" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Year</label>
                            <input
                                v-model.number="goalForm.year"
                                type="number"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Goal Amount</label>
                            <input
                                v-model.number="goalForm.yearly_goal"
                                type="number"
                                step="0.01"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Currency</label>
                            <select
                                v-model="goalForm.currency"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                                <option value="CZK">CZK</option>
                            </select>
                        </div>
                    </div>
                    <button
                        type="submit"
                        :disabled="goalForm.processing"
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
                    >
                        Update Goal
                    </button>
                </form>
            </div>

            <!-- Donations Section -->
            <div class="bg-gray-800/50 backdrop-blur-sm border border-white/10 rounded-xl p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">Donations (Total: {{ totalDonations }} EUR)</h2>
                    <button
                        @click="showDonationForm = !showDonationForm"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors"
                    >
                        {{ showDonationForm ? 'Cancel' : 'Add Donation' }}
                    </button>
                </div>

                <!-- Add/Edit Donation Form -->
                <form v-if="showDonationForm" @submit.prevent="submitDonation" class="bg-gray-900/50 border border-white/10 rounded-lg p-4 mb-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Donor Name (optional)</label>
                            <input
                                v-model="donationForm.donor_name"
                                type="text"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Amount *</label>
                            <input
                                v-model.number="donationForm.amount"
                                type="number"
                                step="0.01"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Currency *</label>
                            <select
                                v-model="donationForm.currency"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                                <option value="CZK">CZK</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Date *</label>
                            <input
                                v-model="donationForm.donation_date"
                                type="date"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Note (optional)</label>
                            <textarea
                                v-model="donationForm.note"
                                rows="2"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            ></textarea>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="donationForm.processing"
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
                        >
                            {{ editingDonation ? 'Update' : 'Add' }} Donation
                        </button>
                        <button
                            type="button"
                            @click="resetDonationForm"
                            class="px-6 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </form>

                <!-- Donations List -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-white/10">
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Date</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Donor</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Amount</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Currency</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Note</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="donation in donations" :key="donation.id" class="border-b border-white/5 hover:bg-white/5">
                                <td class="px-4 py-3 text-sm text-gray-300">{{ donation.donation_date }}</td>
                                <td class="px-4 py-3 text-sm text-white">{{ donation.donor_name || 'Anonymous' }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-green-400">{{ donation.amount }}</td>
                                <td class="px-4 py-3 text-sm text-gray-300">{{ donation.currency }}</td>
                                <td class="px-4 py-3 text-sm text-gray-400">{{ donation.note || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right space-x-2">
                                    <button
                                        @click="editDonation(donation)"
                                        class="text-blue-400 hover:text-blue-300"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="deleteDonation(donation.id)"
                                        class="text-red-400 hover:text-red-300"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Self-Raised Money Section -->
            <div class="bg-gray-800/50 backdrop-blur-sm border border-white/10 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">Self-Raised Money (Total: {{ totalSelfRaised }} EUR)</h2>
                    <button
                        @click="showSelfRaisedForm = !showSelfRaisedForm"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors"
                    >
                        {{ showSelfRaisedForm ? 'Cancel' : 'Add Entry' }}
                    </button>
                </div>

                <!-- Add/Edit Self-Raised Form -->
                <form v-if="showSelfRaisedForm" @submit.prevent="submitSelfRaised" class="bg-gray-900/50 border border-white/10 rounded-lg p-4 mb-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Source *</label>
                            <input
                                v-model="selfRaisedForm.source"
                                type="text"
                                placeholder="YouTube, Twitch, etc."
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Amount *</label>
                            <input
                                v-model.number="selfRaisedForm.amount"
                                type="number"
                                step="0.01"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Currency *</label>
                            <select
                                v-model="selfRaisedForm.currency"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                                <option value="CZK">CZK</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Date *</label>
                            <input
                                v-model="selfRaisedForm.earned_date"
                                type="date"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                required
                            />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Description (optional)</label>
                            <textarea
                                v-model="selfRaisedForm.description"
                                rows="2"
                                class="w-full px-4 py-2 bg-gray-900/50 border border-white/10 rounded-lg text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            ></textarea>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="selfRaisedForm.processing"
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors disabled:opacity-50"
                        >
                            {{ editingSelfRaised ? 'Update' : 'Add' }} Entry
                        </button>
                        <button
                            type="button"
                            @click="resetSelfRaisedForm"
                            class="px-6 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </form>

                <!-- Self-Raised Money List -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-white/10">
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Date</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Source</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Amount</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Currency</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-300">Description</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in selfRaisedMoney" :key="item.id" class="border-b border-white/5 hover:bg-white/5">
                                <td class="px-4 py-3 text-sm text-gray-300">{{ item.earned_date }}</td>
                                <td class="px-4 py-3 text-sm text-white">{{ item.source }}</td>
                                <td class="px-4 py-3 text-sm font-bold text-purple-400">{{ item.amount }}</td>
                                <td class="px-4 py-3 text-sm text-gray-300">{{ item.currency }}</td>
                                <td class="px-4 py-3 text-sm text-gray-400">{{ item.description || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right space-x-2">
                                    <button
                                        @click="editSelfRaised(item)"
                                        class="text-blue-400 hover:text-blue-300"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="deleteSelfRaised(item.id)"
                                        class="text-red-400 hover:text-red-300"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
