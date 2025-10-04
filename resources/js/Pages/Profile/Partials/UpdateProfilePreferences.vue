<script setup>
    import { useForm } from '@inertiajs/vue3';
    import ActionMessage from '@/Components/Laravel/ActionMessage.vue';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import { computed } from 'vue';

    const props = defineProps({
        user: Object,
    });

    const form = useForm({
        _method: 'POST',
        color: props.user.color
    });

    const updateProfilePreferences = () => {
        form.post(route('settings.preferences'), {
            preserveScroll: true
        });
    };

    const getAnimation = computed(() => {
        const isMozilla = /firefox/.test(navigator.userAgent.toLowerCase());
        let result = '';

        if (isMozilla) {
            result = 'none';
        } else {
            result = 'profilepulse 5s linear infinite';
        }

        return {
            '--profile-animation': result,
        }
    })
</script>

<template>
    <FormSection @submitted="updateProfilePreferences" id="preferences">
        <template #title>
            Profile Preferences
        </template>

        <template #description>
            Customize your profile.
        </template>

        <template #form>
            <div class="col-span-6">
                <div class="flex justify-center">
                    <div>
                        <div class="flex flex-col items-center p-4 relative">
                            <div class="profile-effect" :style="getAnimation"></div>
                            <img style="z-index: 2;" class="h-24 w-24 rounded-full border-4 border-gray-500 object-cover" :src="user?.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'" :alt="user?.name ?? profile.name">
                        </div>

                        <div class="flex items-center justify-center">
                            <div>
                                <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${user?.country ?? profile.country}.png`" :title="user?.country ?? profile.country" class="w-7 inline mr-2 mb-0.5">
                            </div>
                            <div class="text-2xl font-medium text-gray-100" v-html="q3tohtml(user?.name ?? profile.name)"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-6 flex flex-col items-center">
                <div class="text-white mb-2">Profile Color</div>
                <div class="flex justify-center">
                    <v-color-picker show-swatches color="#1F2937" v-model="form.color" hide-inputs />
                </div>
                <InputError :message="form.errors.color" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>


<style scoped>
    .profile-effect {
        box-shadow: 0 0 100px 40px v-bind('form.color');
        transition: all .5s ease-in-out;
        height: 1px;
        left: 50%;
        top: 50%;
        position: absolute;
        width: 1px;
        z-index: 1;
        animation: var(--profile-animation);
    }

    @keyframes profilepulse {
        0% {
            box-shadow: 0 0 100px 40px v-bind('form.color');
        }
        50% {
            box-shadow: 0 0 200px 40px v-bind('form.color');
        }
        100% {
            box-shadow: 0 0 100px 40px v-bind('form.color');
        }
    }
</style>