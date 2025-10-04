<script setup>
    import { useForm } from '@inertiajs/vue3';
    import ActionMessage from '@/Components/Laravel/ActionMessage.vue';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';

    const props = defineProps({
        user: Object,
    });

    const form = useForm({
        _method: 'POST',
        twitter_name: props.user.twitter_name,
        twitch_name: props.user.twitch_name,
        discord_name: props.user.discord_name
    });

    const updateSocialMediaInformation = () => {
        form.post(route('settings.socialmedia'), {
            preserveScroll: true
        });
    };
</script>

<template>
    <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500/20 to-purple-600/20 border border-purple-500/30 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                    </svg>
                </div>
                <h2 class="text-sm font-bold text-white">Social Media</h2>
            </div>
            <div v-if="form.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-xs font-medium text-green-400">Saved</span>
            </div>
        </div>

        <form @submit.prevent="updateSocialMediaInformation" class="space-y-3">
            <div>
                <InputLabel for="twitter" value="Twitter" />
                <TextInput
                    id="twitter"
                    v-model="form.twitter_name"
                    type="text"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.twitter_name" class="mt-1" />
            </div>

            <div>
                <InputLabel for="twitch" value="Twitch" />
                <TextInput
                    id="twitch"
                    v-model="form.twitch_name"
                    type="text"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.twitch_name" class="mt-1" />
            </div>

            <div>
                <InputLabel for="discord" value="Discord" />
                <TextInput
                    id="discord"
                    v-model="form.discord_name"
                    type="text"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.discord_name" class="mt-1" />
            </div>

            <div class="flex justify-end pt-1">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Save
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
