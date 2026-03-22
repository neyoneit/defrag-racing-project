<script setup>
    import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
    import ClanAchievements from './ClanAchievements.vue';
    import { computed, ref } from 'vue';

    const page = usePage();

    const props = defineProps({
        clan: Object,
        players: Array,
        statistics: Object,
        userRequestStatus: String,
        userIsMember: Boolean,
        userIsInClan: Boolean,
        pendingRequestId: Number,
    });

    const showJoinConfirm = ref(false);
    const showCancelConfirm = ref(false);

    const joinForm = useForm({ _method: 'POST' });
    const cancelForm = useForm({ _method: 'POST' });

    const submitJoinRequest = () => {
        joinForm.post(route('clans.request.join', props.clan.id), {
            preserveScroll: true,
            onSuccess: () => { showJoinConfirm.value = false; }
        });
    };

    const submitCancelRequest = () => {
        cancelForm.post(route('clans.request.cancel', props.pendingRequestId), {
            preserveScroll: true,
            onSuccess: () => { showCancelConfirm.value = false; }
        });
    };

    const canRequestJoin = computed(() => {
        return page.props.auth?.user
            && !props.userIsMember
            && !props.userIsInClan
            && props.userRequestStatus !== 'pending'
            && props.userRequestStatus !== 'blocked'
            && !props.clan.hidden
            && !props.clan.banned;
    });

    const hexToRgba = (hex, alpha = 1) => {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    };

    const effectColor = computed(() => props.clan.effect_color || '#60a5fa');
    const avatarEffectColor = computed(() => props.clan.avatar_effect_color || '#60a5fa');
    const shadowColor = computed(() => props.clan.name_shadow_color || '#3b82f6');
</script>

<template>
    <div class="min-h-screen">
        <Head :title="clan.plain_name" />

        <!-- Hero Section with Background -->
        <div class="relative h-[400px] overflow-hidden">
            <!-- Background Image -->
            <div class="absolute inset-0">
                <div class="absolute inset-0 flex justify-center">
                    <div class="relative w-full max-w-[1920px] h-full">
                        <div
                            v-if="clan.background"
                            class="w-full h-full bg-top bg-no-repeat"
                            :style="`background-image: url('/storage/${clan.background}'); background-size: 1920px auto;`"
                        ></div>
                        <!-- Fade left edge of image -->
                        <div v-if="clan.background" class="absolute left-0 top-0 bottom-0 w-80 bg-gradient-to-r from-gray-900 to-transparent"></div>
                        <!-- Fade right edge of image -->
                        <div v-if="clan.background" class="absolute right-0 top-0 bottom-0 w-80 bg-gradient-to-l from-gray-900 to-transparent"></div>
                    </div>
                </div>
                <!-- Fade image to transparent at bottom -->
                <div v-if="clan.background" class="absolute inset-0 bg-gradient-to-b from-transparent from-0% via-transparent via-60% to-gray-900 to-100%"></div>
                <!-- Dark overlay on top -->
                <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/30 to-transparent"></div>
            </div>

            <!-- Member avatars scattered across hero (avoiding center) - positioned relative to 1920px background -->
            <div class="absolute inset-0 top-16 bottom-0 pointer-events-none flex justify-center">
                <div class="relative w-full max-w-[1920px] h-full">
                    <Link
                        v-for="(player, index) in players"
                        :key="player.id"
                        :href="route('profile.index', player.id)"
                        class="absolute group/avatar transition-all duration-300 hover:scale-125 hover:z-50 pointer-events-auto opacity-50 hover:opacity-100"
                        :style="{
                            left: (() => {
                                const positions = [8, 12, 18, 82, 88, 92, 15, 78, 10, 85, 20, 75, 14, 80, 22, 72, 16, 76, 24, 70];
                                return (positions[index % positions.length]) + '%';
                            })(),
                            top: (() => {
                                const positions = [8, 15, 22, 12, 18, 25, 70, 75, 82, 78, 72, 85, 30, 65, 35, 60, 40, 55, 45, 50];
                                return (positions[index % positions.length]) + '%';
                            })()
                        }"
                    >
                        <div class="relative">
                            <div class="absolute inset-0 bg-blue-500 blur-md opacity-0 group-hover/avatar:opacity-60 transition-opacity duration-300 rounded-full"></div>
                            <img
                                class="relative h-11 w-11 rounded-full object-cover border-2 border-white/40 group-hover/avatar:border-blue-400 shadow-xl transition-all duration-300"
                                :src="player.profile_photo_path ? `/storage/${player.profile_photo_path}` : '/images/null.jpg'"
                                :alt="player.name"
                                :title="player.plain_name"
                            />
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Clan Info -->
            <div class="relative h-full flex flex-col items-center justify-center px-4">
                <!-- Clan Logo with effects -->
                <div class="relative group mb-6">
                    <!-- Default glow (when no effect) -->
                    <div v-if="!clan.avatar_effect || clan.avatar_effect === 'none'" class="absolute inset-0 rounded-full opacity-50 group-hover:opacity-75 transition-opacity duration-500" style="box-shadow: 0 0 30px rgba(59,130,246,0.6);"></div>

                    <!-- Glow Effect -->
                    <div v-if="clan.avatar_effect === 'glow'" class="absolute inset-0 rounded-full opacity-60" :style="`box-shadow: 0 0 40px ${avatarEffectColor};`"></div>

                    <!-- Pulse Effect -->
                    <div v-if="clan.avatar_effect === 'pulse'" class="absolute inset-0 rounded-full animate-ping opacity-75" :style="`background-color: ${avatarEffectColor};`"></div>

                    <!-- Ring Effect -->
                    <div v-if="clan.avatar_effect === 'ring'" class="absolute -inset-4">
                        <div class="w-full h-full rounded-full border-[6px] border-t-transparent animate-spin" :style="`border-color: ${avatarEffectColor}; border-top-color: transparent;`"></div>
                    </div>

                    <!-- Shine Effect -->
                    <div v-if="clan.avatar_effect === 'shine'" class="absolute inset-0 rounded-full overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-50 animate-shine"></div>
                    </div>

                    <!-- Animated Border -->
                    <div v-if="clan.avatar_effect === 'border'" class="absolute -inset-2 rounded-full opacity-75 animate-pulse" :style="`background: linear-gradient(45deg, ${avatarEffectColor}, transparent, ${avatarEffectColor}); background-size: 200% 200%; animation: gradient-shift 3s ease infinite;`"></div>

                    <!-- Particle Orbit -->
                    <div v-if="clan.avatar_effect === 'particles'" class="absolute -inset-8">
                        <div class="absolute top-0 left-1/2 w-3 h-3 rounded-full animate-orbit-1" :style="`background-color: ${avatarEffectColor}; box-shadow: 0 0 15px ${avatarEffectColor};`"></div>
                        <div class="absolute top-0 left-1/2 w-3 h-3 rounded-full animate-orbit-2" :style="`background-color: ${avatarEffectColor}; box-shadow: 0 0 15px ${avatarEffectColor};`"></div>
                        <div class="absolute top-0 left-1/2 w-3 h-3 rounded-full animate-orbit-3" :style="`background-color: ${avatarEffectColor}; box-shadow: 0 0 15px ${avatarEffectColor};`"></div>
                    </div>

                    <img
                        class="relative h-32 w-32 rounded-full object-cover border-4 border-white/20 shadow-2xl transition-transform duration-500 group-hover:scale-110 z-10"
                        :class="{'animate-spin-slow': clan.avatar_effect === 'spin'}"
                        :src="`/storage/${clan.image}`"
                        :alt="clan.plain_name"
                    />
                </div>

                <!-- Clan Name with animated effects -->
                <div class="relative mb-4 py-6">
                    <!-- Particles Effect -->
                    <div v-if="clan.name_effect === 'particles'" class="absolute inset-0 -inset-x-24 -inset-y-12 pointer-events-none">
                        <div class="absolute top-[20%] left-[15%] w-3 h-3 rounded-full" :style="`background-color: ${effectColor}; box-shadow: 0 0 20px ${hexToRgba(effectColor, 0.8)}; animation: particle-float 3s ease-in-out infinite;`"></div>
                        <div class="absolute top-[70%] left-[25%] w-2 h-2 rounded-full" :style="`background-color: ${effectColor}; box-shadow: 0 0 15px ${hexToRgba(effectColor, 0.8)}; animation: particle-float 2.5s ease-in-out infinite; animation-delay: 0.5s;`"></div>
                        <div class="absolute top-[40%] left-[80%] w-3 h-3 rounded-full" :style="`background-color: ${effectColor}; box-shadow: 0 0 20px ${hexToRgba(effectColor, 0.8)}; animation: particle-float 2.8s ease-in-out infinite; animation-delay: 1s;`"></div>
                        <div class="absolute top-[60%] left-[85%] w-2 h-2 rounded-full" :style="`background-color: ${effectColor}; box-shadow: 0 0 15px ${hexToRgba(effectColor, 0.8)}; animation: particle-float 3.2s ease-in-out infinite; animation-delay: 1.5s;`"></div>
                        <div class="absolute top-[30%] left-[10%] w-2 h-2 rounded-full" :style="`background-color: ${effectColor}; box-shadow: 0 0 15px ${hexToRgba(effectColor, 0.8)}; animation: particle-float 2.7s ease-in-out infinite; animation-delay: 0.8s;`"></div>
                    </div>

                    <!-- Orbs Effect -->
                    <div v-if="clan.name_effect === 'orbs'" class="absolute inset-0 -inset-x-32 -inset-y-16 pointer-events-none overflow-hidden">
                        <div class="absolute -left-8 top-1/2 -translate-y-1/2 w-40 h-40 rounded-full" :style="`animation: orb-float 6s ease-in-out infinite; box-shadow: 0 0 60px 30px ${hexToRgba(effectColor, 0.3)};`"></div>
                        <div class="absolute -right-8 top-1/2 -translate-y-1/2 w-48 h-48 rounded-full" :style="`animation: orb-float 6s ease-in-out infinite; animation-delay: 1s; box-shadow: 0 0 60px 30px ${hexToRgba(effectColor, 0.3)};`"></div>
                    </div>

                    <!-- Lines Effect -->
                    <div v-if="clan.name_effect === 'lines'" class="absolute inset-0 -inset-x-24 -inset-y-4 pointer-events-none">
                        <div class="absolute top-0 left-0 right-0 h-[2px]" :style="`background: linear-gradient(to right, transparent, ${hexToRgba(effectColor, 0.6)}, transparent); box-shadow: 0 0 15px ${hexToRgba(effectColor, 0.5)}; animation: line-scan 3s linear infinite;`"></div>
                        <div class="absolute bottom-0 left-0 right-0 h-[2px]" :style="`background: linear-gradient(to right, transparent, ${hexToRgba(effectColor, 0.6)}, transparent); box-shadow: 0 0 15px ${hexToRgba(effectColor, 0.5)}; animation: line-scan 3s linear infinite; animation-delay: 1.5s;`"></div>
                    </div>

                    <!-- Matrix Effect -->
                    <div v-if="clan.name_effect === 'matrix'" class="absolute inset-0 -inset-x-24 -inset-y-12 pointer-events-none overflow-hidden">
                        <div class="absolute top-0 left-[20%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(effectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(effectColor, 0.6)}; animation: matrix-fall 2.5s linear infinite;`"></div>
                        <div class="absolute top-0 left-[40%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(effectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(effectColor, 0.6)}; animation: matrix-fall 2s linear infinite; animation-delay: 0.5s;`"></div>
                        <div class="absolute top-0 left-[60%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(effectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(effectColor, 0.6)}; animation: matrix-fall 2.8s linear infinite; animation-delay: 1s;`"></div>
                        <div class="absolute top-0 left-[80%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(effectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(effectColor, 0.6)}; animation: matrix-fall 2.3s linear infinite; animation-delay: 1.5s;`"></div>
                    </div>

                    <!-- Glitch Effect -->
                    <div v-if="clan.name_effect === 'glitch'" class="absolute inset-0 pointer-events-none flex items-start justify-center pt-6">
                        <h1 class="absolute text-6xl font-black" :style="`color: ${hexToRgba(effectColor, 0.3)}; animation: glitch-text 2s infinite; transform: translate(2px, -1px);`" v-html="q3tohtml(clan.name)"></h1>
                        <h1 class="absolute text-6xl font-black" :style="`color: ${hexToRgba(effectColor, 0.3)}; animation: glitch-text 2s infinite; animation-delay: 0.2s; transform: translate(-2px, 1px);`" v-html="q3tohtml(clan.name)"></h1>
                    </div>

                    <!-- Wave Effect -->
                    <div v-if="clan.name_effect === 'wave'" class="absolute inset-0 pointer-events-none flex items-start justify-center pt-6">
                        <h1 class="absolute text-6xl font-black" :style="`color: ${hexToRgba(effectColor, 0.4)}; animation: wave-text 1s ease-in-out infinite;`" v-html="q3tohtml(clan.name)"></h1>
                        <h1 class="absolute text-6xl font-black" :style="`color: ${hexToRgba(effectColor, 0.4)}; animation: wave-text 1s ease-in-out infinite; animation-delay: 0.1s;`" v-html="q3tohtml(clan.name)"></h1>
                    </div>

                    <!-- Neon Pulse Effect -->
                    <div v-if="clan.name_effect === 'neon'" class="absolute inset-0 pointer-events-none flex items-start justify-center pt-6">
                        <h1 class="absolute text-6xl font-black" :style="`color: ${effectColor}; text-shadow: 0 0 20px ${effectColor}, 0 0 40px ${effectColor}; animation: neon-pulse 1.5s ease-in-out infinite;`" v-html="q3tohtml(clan.name)"></h1>
                    </div>

                    <!-- RGB Split Effect -->
                    <div v-if="clan.name_effect === 'rgb'" class="absolute inset-0 pointer-events-none flex items-start justify-center pt-6">
                        <h1 class="absolute text-6xl font-black" :style="`color: ${effectColor}; animation: rgb-split 0.8s ease-in-out infinite;`" v-html="q3tohtml(clan.name)"></h1>
                    </div>

                    <!-- Flicker Effect -->
                    <div v-if="clan.name_effect === 'flicker'" class="absolute inset-0 pointer-events-none flex items-start justify-center pt-6">
                        <h1 class="absolute text-6xl font-black" :style="`color: ${effectColor}; text-shadow: 0 0 10px ${effectColor}; animation: flicker 3s linear infinite;`" v-html="q3tohtml(clan.name)"></h1>
                    </div>

                    <!-- Hologram Effect -->
                    <div v-if="clan.name_effect === 'hologram'" class="absolute inset-0 pointer-events-none flex items-start justify-center pt-6">
                        <h1 class="absolute text-6xl font-black" :style="`color: ${hexToRgba(effectColor, 0.85)}; text-shadow: 0 0 15px ${effectColor}; animation: hologram 2s ease-in-out infinite;`" v-html="q3tohtml(clan.name)"></h1>
                        <div class="absolute top-6 left-0 right-0 h-[72px] pointer-events-none" :style="`background: repeating-linear-gradient(0deg, transparent, transparent 2px, ${hexToRgba(effectColor, 0.05)} 2px, ${hexToRgba(effectColor, 0.05)} 4px);`"></div>
                    </div>

                    <!-- None / Minimal -->
                    <div v-if="clan.name_effect === 'none'" class="absolute inset-0 -inset-x-20 -inset-y-8 pointer-events-none">
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent"></div>
                    </div>

                    <!-- Clan name text -->
                    <div class="relative flex flex-col items-center gap-2">
                        <h1 class="text-6xl font-black text-white drop-shadow-2xl" :style="clan.name_shadow_enabled === true || clan.name_shadow_enabled === 1 ? `text-shadow: 0 0 40px ${hexToRgba(shadowColor, 0.5)}, 0 0 80px ${hexToRgba(shadowColor, 0.3)};` : ''" v-html="q3tohtml(clan.name)"></h1>
                        <div v-if="clan.tag" class="text-2xl font-bold text-blue-300/80" :style="clan.name_shadow_enabled === true || clan.name_shadow_enabled === 1 ? `text-shadow: 0 0 20px ${hexToRgba(shadowColor, 0.4)};` : ''" v-html="q3tohtml(clan.tag)"></div>
                    </div>
                </div>

                <!-- Stats Bar -->
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-6 bg-black/40 rounded-full px-8 py-3 border border-white/20 shadow-2xl">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <span class="text-white font-bold">{{ players.length }}</span>
                            <span class="text-gray-300 text-sm">Members</span>
                        </div>
                    </div>

                    <!-- Request to Join Button -->
                    <button
                        v-if="canRequestJoin"
                        @click="showJoinConfirm = true"
                        class="flex items-center gap-2 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-500 hover:to-green-600 text-white font-bold px-6 py-3 rounded-full shadow-2xl border border-green-400/30 transition-all duration-300 hover:scale-105 hover:shadow-green-500/30"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                        Request to Join
                    </button>

                    <!-- Pending Request Status -->
                    <div
                        v-else-if="userRequestStatus === 'pending'"
                        class="flex items-center gap-2"
                    >
                        <div class="flex items-center gap-2 bg-yellow-600/20 text-yellow-400 font-semibold px-6 py-3 rounded-full border border-yellow-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Request Pending
                        </div>
                        <button
                            @click="showCancelConfirm = true"
                            class="flex items-center gap-1 px-3 py-3 rounded-full bg-white/5 hover:bg-red-600/20 text-gray-400 hover:text-red-400 border border-white/10 hover:border-red-500/30 transition-all duration-300"
                            title="Cancel request"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Achievements & Statistics Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 mt-12 relative z-10">
            <ClanAchievements :statistics="statistics" />
        </div>

        <!-- Members Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 mt-12 relative z-10">
            <div class="bg-black/40 rounded-2xl overflow-hidden shadow-2xl border border-white/10">
                <!-- Section Header -->
                <div class="bg-gradient-to-r from-blue-600/20 to-blue-800/20 border-b border-white/10 px-8 py-6">
                    <h2 class="text-3xl font-black text-white flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                        <span>Roster</span>
                    </h2>
                </div>

                <!-- Members Grid -->
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <Link
                            v-for="player in players"
                            :key="player.id"
                            :href="route('profile.index', player.id)"
                            class="group relative overflow-visible rounded-xl bg-gradient-to-br from-gray-900/85 to-gray-950/90 border border-white/10 hover:border-blue-500/50 transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl hover:shadow-blue-500/20 hover:z-50"
                        >
                            <!-- Card Background Glow -->
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-600/0 to-blue-700/0 group-hover:from-blue-600/10 group-hover:to-blue-700/10 transition-all duration-500 rounded-xl"></div>

                            <!-- Content -->
                            <div class="relative p-6">
                                <!-- Player Header -->
                                <div class="flex items-center gap-4 mb-4">
                                    <!-- Avatar with ring -->
                                    <div class="relative">
                                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-blue-600 blur-lg opacity-0 group-hover:opacity-50 transition-opacity duration-500 rounded-full"></div>
                                        <img
                                            class="relative h-16 w-16 rounded-full object-cover ring-2 ring-white/20 group-hover:ring-blue-500/60 transition-all duration-300"
                                            :src="player.profile_photo_path ? `/storage/${player.profile_photo_path}` : '/images/null.jpg'"
                                            :alt="player.name"
                                        />
                                    </div>

                                    <!-- Player Name & Country -->
                                    <div class="flex-1 min-w-0">
                                        <div class="text-lg font-bold text-white group-hover:text-blue-400 transition-colors truncate mb-1" v-html="q3tohtml(player.name)"></div>
                                        <div class="flex items-center gap-2">
                                            <img
                                                v-if="player.country"
                                                :src="`/images/flags/${player.country}.png`"
                                                class="w-5 h-4 opacity-80"
                                                onerror="this.src='/images/flags/_404.png'"
                                            />
                                            <span v-if="player.pivot?.position" class="text-xs font-medium text-blue-400">
                                                • {{ player.pivot.position }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Member Note -->
                                <div class="mt-4 pt-4 border-t border-white/10">
                                    <div v-if="player.pivot?.note" class="text-sm text-gray-300 leading-relaxed prose prose-invert prose-sm max-w-none max-h-[4.5rem] group-hover:max-h-[500px] overflow-hidden transition-all duration-500 ease-in-out" v-html="player.pivot.note"></div>
                                    <div v-else class="text-sm text-gray-500 italic">Player doesn't have a public note filled in yet.</div>
                                </div>

                                <!-- Config File Download -->
                                <div v-if="player.pivot?.config_file" class="mt-4 pt-4 border-t border-white/10" @click.prevent>
                                    <a
                                        :href="`/storage/${player.pivot.config_file}`"
                                        download
                                        class="flex items-center justify-center gap-2 px-3 py-2 bg-blue-600/20 hover:bg-blue-600/30 border border-blue-500/30 hover:border-blue-500/50 rounded-lg text-blue-400 hover:text-blue-300 transition-all duration-300"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                        <span class="text-sm font-medium">Download Config</span>
                                    </a>
                                </div>
                            </div>

                            <!-- Hover Arrow -->
                            <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                                </svg>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rival Clans Section -->
        <div v-if="statistics.special_sections && statistics.special_sections.rival_clans && statistics.special_sections.rival_clans.length > 0" class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 mt-6 relative z-10 pb-20">
            <div class="bg-black/40 rounded-2xl overflow-hidden shadow-2xl border border-white/10">
                <!-- Section Header -->
                <div class="bg-gradient-to-r from-red-600/20 to-orange-800/20 border-b border-white/10 px-8 py-6">
                    <h2 class="text-3xl font-black text-white flex items-center gap-3">
                        <img src="/images/weapons/iconw_rocket.svg" class="w-8 h-8" alt="Rivals" />
                        <span>Rival Clans</span>
                    </h2>
                    <div class="text-sm text-gray-400 mt-2">Top clans ranked by World Records and Top 3 finishes</div>
                </div>

                <!-- Rival Clans Grid -->
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <Link
                            v-for="rival in statistics.special_sections.rival_clans"
                            :key="rival.id"
                            :href="`/clans/${rival.id}`"
                            class="group relative overflow-hidden rounded-xl bg-gradient-to-br from-black/40 to-black/20 border border-white/10 hover:border-yellow-500/50 transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl hover:shadow-yellow-500/20"
                        >
                            <!-- Card Background Glow -->
                            <div class="absolute inset-0 bg-gradient-to-br from-yellow-600/0 to-orange-700/0 group-hover:from-yellow-600/10 group-hover:to-orange-700/10 transition-all duration-500"></div>

                            <!-- Content -->
                            <div class="relative p-6">
                                <!-- Clan Header -->
                                <div class="flex items-center gap-4 mb-4">
                                    <!-- Logo -->
                                    <div class="relative">
                                        <div class="absolute inset-0 bg-gradient-to-r from-yellow-500 to-orange-600 blur-lg opacity-0 group-hover:opacity-50 transition-opacity duration-500 rounded-lg"></div>
                                        <img
                                            class="relative h-16 w-16 rounded-lg object-cover ring-2 ring-white/20 group-hover:ring-yellow-500/60 transition-all duration-300"
                                            :src="rival.image ? `/storage/${rival.image}` : '/images/null.jpg'"
                                            :alt="rival.plain_name"
                                        />
                                    </div>

                                    <!-- Clan Name & Tag -->
                                    <div class="flex-1 min-w-0">
                                        <div class="text-lg font-bold text-white group-hover:text-yellow-400 transition-colors truncate mb-1" v-html="q3tohtml(rival.name)"></div>
                                        <div v-if="rival.tag" class="text-sm text-gray-500">{{ rival.tag }}</div>
                                    </div>
                                </div>

                                <!-- Stats -->
                                <div class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between p-2 bg-black/20 rounded-lg">
                                        <span class="text-gray-400">World Records:</span>
                                        <span class="font-bold text-yellow-400">{{ rival.total_wrs }}</span>
                                    </div>
                                    <div class="flex items-center justify-between p-2 bg-black/20 rounded-lg">
                                        <span class="text-gray-400">Top 3:</span>
                                        <span class="font-bold text-purple-400">{{ rival.total_top3 }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Hover Arrow -->
                            <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-yellow-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" />
                                </svg>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Join Request Confirmation Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                leave-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
            >
                <div v-if="showJoinConfirm" class="fixed inset-0 z-[100] flex items-center justify-center p-4" @click.self="showJoinConfirm = false">
                    <div class="fixed inset-0 bg-black/60"></div>
                    <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-md w-full p-6">
                        <h3 class="text-xl font-bold text-white mb-3">Request to Join Clan</h3>
                        <p class="text-gray-300 mb-2">
                            Send a join request to <span class="font-semibold text-white" v-html="q3tohtml(clan.name)"></span>?
                        </p>
                        <p class="text-gray-500 text-sm mb-6">The clan admin will be notified and can accept or decline your request.</p>
                        <div class="flex gap-3">
                            <button
                                @click="submitJoinRequest"
                                :disabled="joinForm.processing"
                                class="flex-1 px-4 py-2.5 bg-green-600 hover:bg-green-500 disabled:opacity-50 text-white font-semibold rounded-xl transition-colors"
                            >
                                {{ joinForm.processing ? 'Sending...' : 'Send Request' }}
                            </button>
                            <button
                                @click="showJoinConfirm = false"
                                class="flex-1 px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-300 font-semibold rounded-xl border border-white/10 transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Cancel Request Confirmation Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                leave-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
            >
                <div v-if="showCancelConfirm" class="fixed inset-0 z-[100] flex items-center justify-center p-4" @click.self="showCancelConfirm = false">
                    <div class="fixed inset-0 bg-black/60"></div>
                    <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-md w-full p-6">
                        <h3 class="text-xl font-bold text-white mb-3">Cancel Join Request</h3>
                        <p class="text-gray-300 mb-6">Are you sure you want to cancel your request to join this clan?</p>
                        <div class="flex gap-3">
                            <button
                                @click="submitCancelRequest"
                                :disabled="cancelForm.processing"
                                class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white font-semibold rounded-xl transition-colors"
                            >
                                {{ cancelForm.processing ? 'Cancelling...' : 'Cancel Request' }}
                            </button>
                            <button
                                @click="showCancelConfirm = false"
                                class="flex-1 px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-300 font-semibold rounded-xl border border-white/10 transition-colors"
                            >
                                Go Back
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
/* Avatar Effect Animations */
@keyframes shine {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(200%);
    }
}

.animate-shine {
    animation: shine 3s ease-in-out infinite;
}

@keyframes spin-slow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin-slow {
    animation: spin-slow 8s linear infinite;
}

@keyframes orbit-1 {
    0% {
        transform: rotate(0deg) translateX(4rem) rotate(0deg);
    }
    100% {
        transform: rotate(360deg) translateX(4rem) rotate(-360deg);
    }
}

@keyframes orbit-2 {
    0% {
        transform: rotate(120deg) translateX(4rem) rotate(-120deg);
    }
    100% {
        transform: rotate(480deg) translateX(4rem) rotate(-480deg);
    }
}

@keyframes orbit-3 {
    0% {
        transform: rotate(240deg) translateX(4rem) rotate(-240deg);
    }
    100% {
        transform: rotate(600deg) translateX(4rem) rotate(-600deg);
    }
}

.animate-orbit-1 {
    animation: orbit-1 4s linear infinite;
}

.animate-orbit-2 {
    animation: orbit-2 4s linear infinite;
}

.animate-orbit-3 {
    animation: orbit-3 4s linear infinite;
}

@keyframes gradient-shift {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}
</style>
