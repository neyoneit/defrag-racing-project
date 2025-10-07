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
        if (user.user) {
            return user.user.country
        }

        return user.country
    }

    const getName = (user) => {
        if (user.user) {
            return user.user.name
        }

        return user.name
    }

    const getPlainName = (user) => {
        if (user.user) {
            return user.user.plain_name
        }

        return user.plain_name
    }
</script>

<template>
    <div class="relative">
        <div class="relative">
            <input
                ref="searchInput"
                @focus="isOpen = true"
                @blur="onBlur"
                class="w-full bg-white/5 border border-white/20 text-white placeholder-gray-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/50 rounded-lg px-4 py-2.5 transition-all outline-none"
                v-model="search"
                @input="filterOptions"
                placeholder="Search players..."
                autocomplete="off"
            />
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 pointer-events-none">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
        </div>

        <div class="dropdown-modern" v-show="isOpen && search.length > 0">
            <div class="flex justify-between items-center px-3 py-2 border-b border-white/10 bg-white/5">
                <span class="text-xs font-semibold text-gray-400">{{ selectedOptions.length }} selected</span>
                <button @click="clearOptions" class="text-red-400 hover:text-red-300 transition-colors p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="option-list">
                <div
                    v-for="(user, index) in filteredOptions"
                    :key="index"
                    @click="selectOption(user.id)"
                    class="option-item"
                    :class="{'option-selected': selectedOptions.includes(user.id)}"
                >
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <img :src="`/images/flags/${getCountry(user)}.png`" onerror="this.src='/images/flags/_404.png'" class="w-6 h-4 object-cover rounded flex-shrink-0">
                        <span class="truncate text-sm" v-html="q3tohtml(getName(user))"></span>
                    </div>
                    <svg v-if="selectedOptions.includes(user.id)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-5 h-5 text-blue-400 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
    .dropdown-modern {
        position: absolute;
        top: calc(100% + 0.5rem);
        left: 0;
        right: 0;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 0.75rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        z-index: 100;
        overflow: hidden;
    }

    .option-list {
        max-height: 240px;
        overflow-y: auto;
        padding: 0.25rem;
    }

    .option-list::-webkit-scrollbar {
        width: 8px;
    }

    .option-list::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
    }

    .option-list::-webkit-scrollbar-thumb {
        background: rgba(59, 130, 246, 0.5);
        border-radius: 10px;
    }

    .option-list::-webkit-scrollbar-thumb:hover {
        background: rgba(59, 130, 246, 0.7);
    }

    .option-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.625rem 0.75rem;
        margin-bottom: 0.125rem;
        cursor: pointer;
        border-radius: 0.5rem;
        color: rgba(255, 255, 255, 0.9);
        transition: all 0.15s ease;
    }

    .option-item:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .option-selected {
        background: rgba(59, 130, 246, 0.2);
        border: 1px solid rgba(59, 130, 246, 0.4);
    }

    .option-selected:hover {
        background: rgba(59, 130, 246, 0.3);
    }
</style>
  