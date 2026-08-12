<script setup>
    import { ref, computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
    import ItemsChoices from '@/Components/Basic/ItemsChoices.vue';
    import { t } from '@/utils/i18n';


    const weapons = [
        {
            'code': 'gauntlet',
            'name': 'Gauntlet'
        },
        {
            'code': 'mg',
            'name': 'Machine Gun'
        },{
            'code': 'sg',
            'name': 'Shot Gun'
        },{
            'code': 'gl',
            'name': 'Grenade Launcher'
        },{
            'code': 'rl',
            'name': 'Rocket Launcher'
        },{
            'code': 'lg',
            'name': 'Lightening Gun'
        },{
            'code': 'rg',
            'name': 'Rail Gun'
        },{
            'code': 'pg',
            'name': 'Plasma Gun'
        },{
            'code': 'bfg',
            'name': 'BFG'
        },{
            'code': 'hook',
            'name': 'Grappling Hook'
        },{
            'code': 'cg',
            'name': 'Chain Gun'
        },{
            'code': 'ng',
            'name': 'Nail Gun'
        },{
            'code': 'pml',
            'name': 'Proximity Mine Launcher'
        },
    ];

    const functions = computed(() => [
        {
            'code': 'door',
            'name': t('Door')
        },
        {
            'code': 'button',
            'name': t('Button')
        },{
            'code': 'tele',
            'name': t('Teleporter')
        },{
            'code': 'jumppad',
            'name': t('Jump Pad')
        },{
            'code': 'moving',
            'name': t('Moving Object')
        },{
            'code': 'slick',
            'name': t('Slick')
        },{
            'code': 'water',
            'name': t('Water')
        },{
            'code': 'fog',
            'name': t('Fog')
        },{
            'code': 'slime',
            'name': t('Slime')
        },{
            'code': 'lava',
            'name': t('Lava')
        },{
            'code': 'break',
            'name': t('Breakable')
        },{
            'code': 'sound',
            'name': t('Sound')
        },{
            'code': 'timer',
            'name': t('Timer')
        },
    ]);

    const items = [
        {
            'code': 'ra',
            'name': 'Red Armor'
        },
        {
            'code': 'ya',
            'name': 'Yellow Armor'
        },{
            'code': 'enviro',
            'name': 'Battle Suit'
        },{
            'code': 'flight',
            'name': 'Flight'
        },{
            'code': 'haste',
            'name': 'Haste'
        },{
            'code': 'health',
            'name': 'Health'
        },{
            'code': 'mega',
            'name': 'Mega Health'
        },{
            'code': 'smallhealth',
            'name': 'Small Health'
        },{
            'code': 'bighealth',
            'name': 'Large Health'
        },{
            'code': 'invis',
            'name': 'Invisibility'
        },{
            'code': 'quad',
            'name': 'Quad Damage'
        },{
            'code': 'regen',
            'name': 'Regeneration'
        },{
            'code': 'medkit',
            'name': 'Medi Kit / Double Jump'
        },{
            'code': 'ptele',
            'name': 'Personal Teleporter'
        }
    ];

    const categories = [
        'Strafe',
        'Rockets',
        'Combo',
        'Plasma',
        'Accuracy',
        'Technical',
        'Haste strafe'
    ];

    const props = defineProps({
        item: Object,
        tournament: Object,
        url: String
    });

    const form = useForm({
        _method: 'POST',
        name: props.item?.name || '',
        mapname: props.item?.mapname || '',
        category: props.item?.category || '',
        image: props.item?.image || '',
        start_date: props.item?.start_date || '',
        end_date: props.item?.end_date || '',
        author: props.item?.author || '',
        weapons: {},
        items: {},
        functions: {},
    });

    if (props.item?.weapons) {
        form.weapons = {
            'exclude': [],
            'include': props.item.weapons?.split(',') || []
        }
    }

    if (props.item?.items) {
        form.items = {
            'exclude': [],
            'include': props.item.items?.split(',') || []
        }
    }

    if (props.item?.functions) {
        form.functions = {
            'exclude': [],
            'include': props.item.functions?.split(',') || []
        }
    }

    const imagePreview = ref(null);
    const imageInput = ref(null);

    const finishBasicInformation = () => {
        let submitUrl = null;

        if (props.item?.id) {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
                round: props.item.id,
            });
        } else {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
            });
        }

        form.post(submitUrl, {
            errorBag: 'finishBasicInformation',
            preserveScroll: true
        });
    };

    const selectNewImage = () => {
        imageInput.value.click();
    };

    const updateImagePreview = () => {
        const image = imageInput.value.files[0];

        if (! image) {
            form.errors.image = t('The image field is required.');
            return;
        };

        if (! image.type.startsWith('image/')) {
            form.errors.image = t('The file must be an Image.');
            imageInput.value = '';
            return;
        }

        const reader = new FileReader();
        form.errors.image = '';

        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };

        reader.readAsDataURL(image);

        form.image = image;
    };
</script>

<template>
    <div>
        <FormSection @submitted="finishBasicInformation">
            <template #title>
                <div>{{ $t('Round Details') }}</div>
            </template>
    
            <template #description>
                <div>{{ $t('Add the details of the new round') }}</div>
            </template>
    
            <template #form>
                <div class="col-span-6">
                    <div class="mb-3">
                        <InputLabel for="name" :value="$t('Round Name')" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            :class="{ 'border-red-500': form.errors.name }"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    
                    <div class="mb-3">
                        <InputLabel for="mapname" :value="$t('Map Name')" />
                        <TextInput
                            id="mapname"
                            v-model="form.mapname"
                            type="text"
                            class="mt-1 block w-full"
                            :class="{ 'border-red-500': form.errors.mapname }"
                        />
                        <InputError :message="form.errors.mapname" />
                    </div>
                    
                    <div class="mb-3">
                        <InputLabel for="author" :value="$t('Map Author')" />
                        <TextInput
                            id="author"
                            v-model="form.author"
                            type="text"
                            class="mt-1 block w-full"
                            :class="{ 'border-red-500': form.errors.author }"
                        />
                        <InputError :message="form.errors.author" />
                    </div>
                    
                    <div class="mb-3">
                        <InputLabel for="category" :value="$t('Map Category')" />
                        <select id="category" v-model="form.category" class="border-2 border-grayop-700 bg-grayop-900 text-gray-600 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm w-full">
                            <option v-for="category in categories" :value="category">
                                {{ category }}
                            </option>
                        </select>
                        <InputError :message="form.errors.mapname" />
                    </div>

                    <div class="mb-3">
                        <input
                            id="image"
                            ref="imageInput"
                            type="file"
                            class="hidden"
                            accept="image/*"
                            @change="updateImagePreview"
                        >
        
                        <InputLabel for="image" :value="$t('Round Image [600 x 400] px')" />

                        <div v-if="imagePreview" class="mt-2">
                            <span
                                class="block rounded-lg bg-cover bg-no-repeat bg-center"
                                :style="'background-image: url(\'' + imagePreview + '\'); width: 100%; height: 200px;'"
                            />
                        </div>

                        <div v-if="!imagePreview && item?.image" class="mt-2">
                            <span
                                class="block rounded-lg bg-cover bg-no-repeat bg-center"
                                :style="'background-image: url(\'/storage/' + item.image + '\'); width: 100%; height: 200px;'"
                            />
                        </div>
        
                        <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewImage">
                            {{ $t('Select A New Image') }}
                        </SecondaryButton>
                        <InputError :message="form.errors.image" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="start_date" :value="$t('Start Date')" />
                        <TextInput
                            id="start_date"
                            v-model="form.start_date"
                            type="datetime-local"
                            class="mt-1 block w-full"
                        />
                        <div class="text-sm text-gray-500">{{ $t('Dates are in UTC timezone') }}</div>
                        <InputError :message="form.errors.start_date" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="end_date" :value="$t('End Date')" />
                        <input
                            id="end_date"
                            v-model="form.end_date"
                            type="datetime-local"
                            class="mt-1 block w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm"
                        />
                        <div class="text-sm text-gray-500">{{ $t('Dates are in UTC timezone') }}</div>
                        <InputError :message="form.errors.end_date" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel :value="$t('Weapons')" />
                        <div class="flex">
                            <span v-for="element in form.weapons?.include">
                                <div :class="`sprite-items sprite-${element} w-4 h-4 flex-shrink-0 mx-1`"></div>
                            </span>
                        </div>
                        <ItemsChoices
                            :options="weapons"
                            :multi="false"
                            v-model="form.weapons"
                            :values="form.weapons"
                            :placeholder="$t('Select Weapons')"
                        />
                        <InputError :message="form.errors.weapons" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel :value="$t('Items')" />
                        <div class="flex">
                            <span v-for="element in form.items?.include">
                                <div :class="`sprite-items sprite-${element} w-4 h-4 flex-shrink-0 mx-1`"></div>
                            </span>
                        </div>
                        <ItemsChoices
                            :options="items"
                            :multi="false"
                            v-model="form.items"
                            :values="form.items"
                            :placeholder="$t('Select Items')"
                        />
                        <InputError :message="form.errors.items" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel :value="$t('Functions')" />
                        <div class="flex">
                            <span v-for="element in form.functions?.include">
                                <div :class="`sprite-items sprite-${element} w-4 h-4 flex-shrink-0 mx-1`"></div>
                            </span>
                        </div>
                        <ItemsChoices
                            :options="functions"
                            :multi="false"
                            v-model="form.functions"
                            :values="form.functions"
                            :placeholder="$t('Select Functions')"
                        />
                        <InputError :message="form.errors.functions" class="mt-2" />
                    </div>
                </div>
            </template>
    
            <template #actions>
                <div class="flex justify-between w-full">
                    <PrimaryButton>{{ $t('Submit') }}</PrimaryButton>
                </div>
            </template>
        </FormSection>
    </div>
</template>
