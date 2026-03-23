<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    listing_type: 'request',
    work_type: 'map',
    title: '',
    description: '',
    budget: '',
});

const workTypes = [
    { value: 'map', label: 'Map', desc: 'Custom map creation' },
    { value: 'player_model', label: 'Player Model', desc: 'Custom player skin/model' },
    { value: 'weapon_model', label: 'Weapon Model', desc: 'Custom weapon skin/model' },
    { value: 'shadow_model', label: 'Shadow Model', desc: 'Custom shadow model' },
];

const submit = () => {
    form.post(route('marketplace.store'));
};
</script>

<template>
    <div class="min-h-screen pb-20">
        <Head title="Create Listing - Marketplace" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-3xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="mb-8">
                    <Link :href="route('marketplace.index')" class="text-gray-400 hover:text-white text-sm transition mb-4 inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        Back to Marketplace
                    </Link>
                    <h1 class="text-4xl md:text-5xl font-black text-gray-300/90 mb-2">Create Listing</h1>
                    <p class="text-gray-400">Post a commission request or offer your services</p>
                </div>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 md:px-6 lg:px-8 relative z-10" style="margin-top: -24rem;">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Listing Type -->
                <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-6">
                    <label class="block text-sm font-bold text-white mb-3">What are you posting?</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            @click="form.listing_type = 'request'"
                            :class="form.listing_type === 'request' ? 'border-blue-500 bg-blue-500/10' : 'border-white/10 hover:border-white/20'"
                            class="p-4 rounded-xl border-2 transition-all text-left"
                        >
                            <div class="text-lg font-bold text-white mb-1">Request</div>
                            <div class="text-sm text-gray-400">I'm looking for someone to create something</div>
                        </button>
                        <button
                            type="button"
                            @click="form.listing_type = 'offer'"
                            :class="form.listing_type === 'offer' ? 'border-green-500 bg-green-500/10' : 'border-white/10 hover:border-white/20'"
                            class="p-4 rounded-xl border-2 transition-all text-left"
                        >
                            <div class="text-lg font-bold text-white mb-1">Offer</div>
                            <div class="text-sm text-gray-400">I'm offering my creation services</div>
                        </button>
                    </div>
                </div>

                <!-- Work Type -->
                <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-6">
                    <label class="block text-sm font-bold text-white mb-3">Type of work</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            v-for="wt in workTypes"
                            :key="wt.value"
                            type="button"
                            @click="form.work_type = wt.value"
                            :class="form.work_type === wt.value ? 'border-blue-500 bg-blue-500/10' : 'border-white/10 hover:border-white/20'"
                            class="p-3 rounded-xl border-2 transition-all text-left"
                        >
                            <div class="text-sm font-bold text-white">{{ wt.label }}</div>
                            <div class="text-xs text-gray-400">{{ wt.desc }}</div>
                        </button>
                    </div>
                    <div v-if="form.errors.work_type" class="text-red-400 text-sm mt-2">{{ form.errors.work_type }}</div>
                </div>

                <!-- Title & Description -->
                <div class="bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 rounded-xl p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-white mb-2">Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="e.g., Looking for a strafe training map"
                            maxlength="255"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                        />
                        <div v-if="form.errors.title" class="text-red-400 text-sm mt-1">{{ form.errors.title }}</div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-white mb-2">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="6"
                            placeholder="Describe what you need in detail..."
                            maxlength="5000"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 resize-none"
                        ></textarea>
                        <div class="flex justify-between mt-1">
                            <div v-if="form.errors.description" class="text-red-400 text-sm">{{ form.errors.description }}</div>
                            <div class="text-xs text-gray-500 ml-auto">{{ form.description.length }}/5000</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-white mb-2">Budget <span class="text-gray-500 font-normal">(optional)</span></label>
                        <input
                            v-model="form.budget"
                            type="text"
                            placeholder="e.g., $50, negotiable, open to offers..."
                            maxlength="255"
                            class="w-full bg-black/40 border border-white/10 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                        />
                        <div v-if="form.errors.budget" class="text-red-400 text-sm mt-1">{{ form.errors.budget }}</div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex items-center justify-between">
                    <Link :href="route('marketplace.index')" class="text-gray-400 hover:text-white text-sm transition">
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing || !form.title || !form.description"
                        class="px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:from-gray-700 disabled:to-gray-700 disabled:text-gray-500 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Listing' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
