<script setup>
    import { useForm } from '@inertiajs/vue3';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import Checkbox from '@/Components/Laravel/Checkbox.vue';

    const props = defineProps({
        user: Object,
    });

    const form = useForm({
        _method: 'POST',
        defrag_news: props.user.defrag_news ? true : false,
        tournament_news: props.user.tournament_news ? true : false,
        clan_notifications: props.user.clan_notifications ? true : false,
        invitations: true,
        records_vq3: props.user.records_vq3,
        records_cpm: props.user.records_cpm,
        preview_records: props.user.preview_records || 'all',
        preview_system: props.user.preview_system || ['announcement', 'clan', 'tournament']
    });

    const updateSocialMediaInformation = () => {
        form.post(route('settings.notifications'), {
            preserveScroll: true
        });
    };

    const togglePreviewSystem = (type) => {
        if (type === 'announcement') return; // Announcement is mandatory

        const index = form.preview_system.indexOf(type);
        if (index > -1) {
            form.preview_system.splice(index, 1);
        } else {
            form.preview_system.push(type);
        }
    };
</script>

<template>
    <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-yellow-500/20 to-yellow-600/20 border border-yellow-500/30 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-yellow-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                </div>
                <h2 class="text-sm font-bold text-white" id="notifications-settings">Notification Settings</h2>
            </div>
            <div class="flex items-center gap-2">
                <div v-if="form.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                    <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="text-xs font-medium text-green-400">Saved</span>
                </div>
                <PrimaryButton type="button" @click="updateSocialMediaInformation" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Save
                </PrimaryButton>
            </div>
        </div>

        <form @submit.prevent="updateSocialMediaInformation" class="space-y-3">
            <div>
                <div class="text-sm text-white font-bold">System Notifications</div>
                <div class="text-xs text-gray-400">When do you want to recieve system notifications</div>

                <div class="mt-2 space-y-2">
                    <label class="flex items-center cursor-pointer">
                        <Checkbox v-model:checked="form.defrag_news" name="defrag_news" />
                        <span class="ms-2 text-xs text-gray-400">
                            Defrag News (Announcements, Changelog, game updates, etc...)
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer">
                        <Checkbox v-model:checked="form.tournament_news" name="tournament_news" />
                        <span class="ms-2 text-xs text-gray-400">
                            Tournament News (Round starts, Results, updates, etc...)
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer">
                        <Checkbox v-model:checked="form.clan_notifications" name="clan_notifications" />
                        <span class="ms-2 text-xs text-gray-400">
                            Clan Notifications (Clan invites, kicks, accepts, leaves, transfers)
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer">
                        <Checkbox :checked="true" name="invitations" disabled class="text-gray-500" />
                        <span class="ms-2 text-xs text-gray-400">
                            Team Invitations (Team invites, etc...) [Mandatory]
                        </span>
                    </label>
                </div>
            </div>

            <div>
                <div class="text-sm text-white font-bold">Header Preview Settings</div>
                <div class="text-xs text-gray-400 mb-2">Configure what notifications appear in the header preview</div>

                <!-- System Notification Preview -->
                <div class="mb-3">
                    <div class="text-blue-400 font-bold text-xs mb-2">System Notification Preview</div>

                    <label class="flex items-center cursor-pointer mb-2">
                        <Checkbox :checked="true" disabled class="text-gray-500" />
                        <span class="ms-2 text-xs text-gray-400">
                            Announcements [Always Shown]
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer mb-2">
                        <Checkbox :checked="form.preview_system.includes('clan')" @change="togglePreviewSystem('clan')" />
                        <span class="ms-2 text-xs text-gray-400">
                            Clan Notifications
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer mb-2">
                        <Checkbox :checked="form.preview_system.includes('tournament')" @change="togglePreviewSystem('tournament')" />
                        <span class="ms-2 text-xs text-gray-400">
                            Tournament Notifications
                        </span>
                    </label>
                </div>

                <!-- Record Notification Preview -->
                <div>
                    <div class="text-orange-400 font-bold text-xs mb-2">Record Notification Preview</div>

                    <div class="flex items-center mb-2">
                        <input id="preview_all" type="radio" v-model="form.preview_records" value="all" name="preview_records" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                        <label for="preview_all" class="ms-2 text-xs font-medium text-gray-300">Show All Records</label>
                    </div>

                    <div class="flex items-center mb-2">
                        <input id="preview_wr" type="radio" v-model="form.preview_records" value="wr" name="preview_records" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                        <label for="preview_wr" class="ms-2 text-xs font-medium text-gray-300">Show World Records Only</label>
                    </div>

                    <div class="flex items-center mb-2">
                        <input id="preview_none" type="radio" v-model="form.preview_records" value="none" name="preview_records" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                        <label for="preview_none" class="ms-2 text-xs font-medium text-gray-300">Don't Show Preview</label>
                    </div>
                </div>
            </div>

            <div>
                <div class="text-sm text-white font-bold">Records Notifications</div>
                <div class="text-xs text-gray-400">When do you want to recieve records notifications</div>

                <div class="mt-2 w-full flex gap-4">
                    <div class="flex-1">
                        <div class="text-blue-400 font-bold text-sm mb-2">VQ3</div>

                        <div class="flex items-center mb-2">
                            <input id="recordsall_vq3" type="radio" v-model="form.records_vq3" value="all" name="recordsall_vq3" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                            <label for="recordsall_vq3" class="ms-2 text-xs font-medium text-gray-300">All</label>
                        </div>

                        <div class="flex items-center mb-2">
                            <input id="recordswr_vq3" type="radio" v-model="form.records_vq3" value="wr" name="recordswr_vq3" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                            <label for="recordswr_vq3" class="ms-2 text-xs font-medium text-gray-300">World Records Only</label>
                        </div>
                    </div>

                    <div class="flex-1">
                        <div class="text-green-400 font-bold text-sm mb-2">CPM</div>

                        <div class="flex items-center mb-2">
                            <input id="recordsall_cpm" type="radio" v-model="form.records_cpm" value="all" name="recordsall_cpm" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                            <label for="recordsall_cpm" class="ms-2 text-xs font-medium text-gray-300">All</label>
                        </div>

                        <div class="flex items-center mb-2">
                            <input id="recordswr_cpm" type="radio" v-model="form.records_cpm" value="wr" name="recordswr_cpm" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                            <label for="recordswr_cpm" class="ms-2 text-xs font-medium text-gray-300">World Records Only</label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
