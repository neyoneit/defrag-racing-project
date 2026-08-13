<template>
    <div class="relative" >
        <input @focus="isOpen = true" @blur="onBlur" class="w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm" v-model="country" @input="filterOptions" :placeholder="$t('Select Country')" autocomplete="off" />
        
        <div class="options p-2 m-1 rounded-md" v-show="isOpen">
            <div class="p-2 cursor-pointer option rounded-md mb-2 text-white flex items-center" v-for="(name, code) in filteredOptions" :key="code" @click="selectOption(code)">
                <img :src="`/images/flags/${code}.png`" class="w-8 pt-1 mr-5">
                {{ name}}
            </div>
        </div>
    </div>
  </template>
  
<script setup>
    import { computed, ref, watch, onMounted } from 'vue';
    import { countryList } from '@/Components/stubs/countries'

    const isOpen = ref(false);
    // Sorting 200 names is not free and the filter runs on every keystroke,
    // so the localised list is built once and re-used.
    const countries = computed(() => countryList());
    const filteredOptions = ref(countryList());
    const country = ref("");
    const selectedOption = ref(null);
    let blurTimeout;

    const props = defineProps({
        setCountry: Function,
        selectedCountry: String,
    });

    onMounted(() => {
        if (props.selectedCountry && countries.value[props.selectedCountry]) {
            country.value = countries.value[props.selectedCountry];
            selectedOption.value = props.selectedCountry;
        }
    });

    const filterOptions = () => {
        const needle = country.value.toLowerCase();

        filteredOptions.value = Object.fromEntries(
            Object.entries(countries.value).filter(([, name]) => name.toLowerCase().includes(needle))
        );
    };

    const onBlur = () => {
        blurTimeout = setTimeout(() => {
            isOpen.value = false;
        }, 100);
    }

    const selectOption = (option) => {
        props.setCountry(option)
        selectedOption.value = option;
        country.value = countries.value[option]
        isOpen.value = false;
    };

    watch(isOpen, (value) => {
        if (!value) {
            filterOptions()
        }
    });
</script>
  
<style scoped>
    .options {
        background-color: #272e3b;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
    }

    .option {
        background-color: #2b323f;
    }

    .option:hover {
        background-color: #343b49;
    }
  </style>
  