<script setup>
    import { useForm } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import CKEditor from '@ckeditor/ckeditor5-vue';
    import ClassicEditor from '@ckeditor/ckeditor5-build-classic';

    const ckeditor = CKEditor.component;

    const props = defineProps({
        player: Object,
        clan: Object
    });

    const isEditing = ref(false);
    const configFileInput = ref(null);

    const form = useForm({
        note: props.player.note || '',
        position: props.player.position || '',
        config_file: null
    });

    const editorConfig = {
        toolbar: {
            items: [
                'heading',
                '|',
                'bold',
                'italic',
                'link',
                'bulletedList',
                'numberedList',
                '|',
                'blockQuote',
                'undo',
                'redo'
            ]
        }
    };

    const saveNote = () => {
        form.post(route('clans.manage.member.note', { clan: props.clan.id, user: props.player.user_id }), {
            preserveScroll: true,
            onSuccess: () => {
                isEditing.value = false;
            }
        });
    };

    const cancelEdit = () => {
        form.note = props.player.note || '';
        form.position = props.player.position || '';
        form.config_file = null;
        isEditing.value = false;
        form.clearErrors();
    };

    const handleFileSelect = (event) => {
        form.config_file = event.target.files[0];
    };

    const removeConfigFile = () => {
        form.delete(route('clans.manage.member.config.delete', { clan: props.clan.id, user: props.player.user_id }), {
            preserveScroll: true
        });
    };
</script>

<template>
    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl transition-all duration-300 hover:border-blue-500/30">
        <!-- Player Header -->
        <div class="p-4 flex items-center gap-3">
            <img
                class="h-10 w-10 rounded-full object-cover ring-2 ring-white/20"
                :src="player.user?.profile_photo_path ? `/storage/${player.user.profile_photo_path}` : '/images/null.jpg'"
                :alt="player.user?.name"
            />
            <div class="flex items-center gap-2">
                <div class="text-base font-semibold text-white" v-html="q3tohtml(player.user?.name)"></div>
                <img
                    v-if="player.user?.country"
                    :src="`/images/flags/${player.user.country}.png`"
                    class="w-5 h-4 opacity-80"
                    onerror="this.src='/images/flags/_404.png'"
                />
            </div>
        </div>

        <!-- Content -->
        <div class="px-4 pb-4">
            <div v-if="!isEditing">
                <!-- Position Display -->
                <div v-if="player.position" class="mb-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-600/20 text-blue-300 border border-blue-500/30">
                        {{ player.position }}
                    </span>
                </div>

                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="flex-1">
                        <div v-if="player.note" class="text-gray-300 leading-relaxed prose prose-invert prose-sm max-w-none" v-html="player.note"></div>
                        <p v-else class="text-gray-500 italic">No note added yet.</p>
                    </div>
                    <button
                        @click="isEditing = true"
                        class="flex items-center gap-2 px-3 py-2 bg-blue-600/20 hover:bg-600/30 border border-blue-500/30 hover:border-blue-500/50 rounded-lg transition-all duration-300 text-sm font-medium text-blue-400"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        Edit
                    </button>
                </div>

                <!-- Config File Display -->
                <div v-if="player.config_file" class="mt-3 flex items-center gap-2">
                    <a
                        :href="`/storage/${player.config_file}`"
                        download
                        class="flex-1 flex items-center justify-between p-3 bg-blue-600/10 border border-blue-500/30 hover:bg-blue-600/20 hover:border-blue-500/50 rounded-lg transition-all duration-300 group"
                    >
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <span class="text-sm text-gray-300">{{ player.config_file.split('/').pop() }}</span>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-blue-400 group-hover:text-blue-300">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </a>
                    <button
                        @click="removeConfigFile"
                        class="p-3 bg-red-600/20 hover:bg-red-600/30 border border-red-500/30 hover:border-red-500/50 rounded-lg transition-all duration-300"
                        title="Delete config file"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-red-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </button>
                </div>
            </div>
            <form v-else @submit.prevent="saveNote">
                <!-- Position Input -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Clan Position</label>
                    <input
                        type="text"
                        v-model="form.position"
                        placeholder="e.g., Leader, Co-Leader, Member, Recruit..."
                        class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/20 transition-all"
                    />
                    <InputError class="mt-2" :message="form.errors.position" />
                </div>

                <div class="ckeditor-dark-theme">
                    <ckeditor
                        :editor="ClassicEditor"
                        v-model="form.note"
                        :config="editorConfig"
                    ></ckeditor>
                </div>
                <InputError class="mt-2" :message="form.errors.note" />

                <!-- Config File Upload -->
                <div class="mt-4">
                    <input
                        ref="configFileInput"
                        type="file"
                        accept=".cfg"
                        @change="handleFileSelect"
                        class="hidden"
                    />
                    <button
                        type="button"
                        @click="$refs.configFileInput.click()"
                        class="flex items-center gap-2 px-3 py-2 bg-blue-600/20 hover:bg-blue-600/30 border border-blue-500/30 hover:border-blue-500/50 rounded-lg text-white transition-all duration-300 text-sm"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                        </svg>
                        <span v-if="form.config_file">{{ form.config_file.name }}</span>
                        <span v-else-if="player.config_file">{{ player.config_file.split('/').pop() }}</span>
                        <span v-else>Upload Config (.cfg)</span>
                    </button>
                    <InputError class="mt-2" :message="form.errors.config_file" />
                </div>

                <div class="flex gap-3 mt-4">
                    <PrimaryButton type="submit" :disabled="form.processing">
                        Save Changes
                    </PrimaryButton>
                    <button
                        type="button"
                        @click="cancelEdit"
                        class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 rounded-lg text-white transition-all duration-300"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style>
.ckeditor-dark-theme .ck-editor__editable {
    min-height: 200px;
    background-color: rgba(255, 255, 255, 0.05) !important;
    color: #fff !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.ckeditor-dark-theme .ck-editor__editable:focus {
    border-color: rgba(99, 102, 241, 0.5) !important;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2) !important;
}

.ckeditor-dark-theme .ck.ck-toolbar {
    background-color: rgba(255, 255, 255, 0.05) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.ckeditor-dark-theme .ck.ck-button,
.ckeditor-dark-theme .ck.ck-dropdown__button {
    color: #d1d5db !important;
}

.ckeditor-dark-theme .ck.ck-button:hover,
.ckeditor-dark-theme .ck.ck-dropdown__button:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
}

.ckeditor-dark-theme .ck.ck-button.ck-on,
.ckeditor-dark-theme .ck.ck-button.ck-on:hover {
    background-color: rgba(99, 102, 241, 0.3) !important;
    color: #fff !important;
}

.ckeditor-dark-theme .ck.ck-dropdown__panel {
    background-color: rgba(17, 24, 39, 0.95) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.ckeditor-dark-theme .ck.ck-list__item {
    color: #d1d5db !important;
}

.ckeditor-dark-theme .ck.ck-list__item:hover {
    background-color: rgba(255, 255, 255, 0.1) !important;
}
</style>
