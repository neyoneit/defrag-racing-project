<script setup>
    import { onMounted, ref, watch, computed } from 'vue';

    const emit = defineEmits(['update:modelValue'])

    const props = defineProps({
        multi: {
            type: Boolean,
            default: false
        },
        options: Array,
        values: Array
    });

    const isOpen = ref(false);
    const filteredOptions = ref(props.options);

    const search = ref("");

    const selectedOptions = ref(props.values ?? []);
    const recentlySelected = ref(false);

    const searchInput = ref(null);

    let blurTimeout;

    const filterOptions = () => {
        filteredOptions.value = props.options.filter((item) => {
            return getPlainName(item).toLowerCase().includes(search.value.toLowerCase())
        }).sort((a, b) => {
            if (selectedOptions.value.includes(a.id)) {
                return -1
            } else if (selectedOptions.value.includes(b.id)) {
                return 1
            } else {
                return 0
            }
        })
    };

    const onBlur = () => {
        blurTimeout = setTimeout(() => {
            if (recentlySelected.value) {
                recentlySelected.value = false;
                searchInput.value.focus();
            } else {
                isOpen.value = false;
            }
        }, 200);
    }

    const selectOption = (id) => {
        recentlySelected.value = true;
        if (props.multi) {
            selectMultipleOptions(id)
        } else {
            selectSingleOption(id)
        }

        emit('update:modelValue', [...selectedOptions.value]);
    };

    const selectMultipleOptions = (id) => {
        let index = selectedOptions.value.indexOf(id)
        if (index !== -1) {
            selectedOptions.value.splice(index, 1)
        } else {
            selectedOptions.value.push(id)
        }
    }

    const selectSingleOption = (id) => {
        selectedOptions.value = [id]
    }

    const clearOptions = () => {
        selectedOptions.value = []

        emit('update:modelValue', [...selectedOptions.value]);
    }

    watch(isOpen, (value) => {
        if (!value) {
            filterOptions()
        }
    });

    onMounted(() => {
        selectedOptions.value = props.values
    })

    const getCountry = (user) => {
        return user.country
    }

    const getName = (user) => {
        return user.name
    }

    const getPlainName = (user) => {
        return user.plain_name
    }
</script>

<template>
    <div class="relative">
        <input
            ref="searchInput"
            @focus="isOpen = true"
            @blur="onBlur"
            class="w-full bg-white/5 border border-white/10 text-white placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50 rounded-lg px-4 py-2.5 transition-all"
            v-model="search"
            @input="filterOptions"
            placeholder="Search player..."
            autocomplete="off"
        />

        <div class="options" v-show="isOpen">
            <div class="flex justify-between items-center text-sm text-gray-400 mb-3 pb-2 border-b border-white/10">
                <div>{{ selectedOptions.length }} Selected</div>
                <button type="button" class="hover:text-white transition-colors" @click="clearOptions">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </button>
            </div>
            <div
                :class="{'bg-white/5 hover:bg-white/10': !selectedOptions.includes(user.id), 'bg-blue-600/80 hover:bg-blue-600': selectedOptions.includes(user.id)}"
                class="p-3 cursor-pointer rounded-lg mb-2 text-white flex items-center gap-3 transition-all"
                v-for="(user, index) in filteredOptions"
                :key="index"
                @click="selectOption(user.id)"
            >
                <img :src="`/images/flags/${getCountry(user)}.png`" onerror="this.src='/images/flags/_404.png'" class="w-6 h-4 object-cover">
                <span v-html="q3tohtml(getName(user))"></span>
            </div>
        </div>
    </div>
</template>

<style scoped>
    .options {
        background: linear-gradient(to bottom, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.95));
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.75rem;
        padding: 1rem;
        margin-top: 0.5rem;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        max-height: 280px;
        overflow-y: auto;
        z-index: 100;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
    }

    .options::-webkit-scrollbar {
        width: 8px;
    }

    .options::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 4px;
    }

    .options::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 4px;
    }

    .options::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.3);
    }
</style>
