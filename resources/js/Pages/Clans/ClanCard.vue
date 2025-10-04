<script setup>
    import { Link } from '@inertiajs/vue3';
    import Popper from "vue3-popper";
    import { computed } from 'vue';

    const props = defineProps({
        clan: Object,
        manage: {
            type: Boolean,
            default: false
        },
        highlighted: {
            type: Boolean,
            default: false
        },
        InvitePlayer: Function,
        KickPlayer: Function,
        TransferOwnership: Function,
        LeaveClan: Function,
        EditClan: Function,
        DismantleClan: Function,
        EditMemberNotes: Function
    });

    const hexToRgba = (hex, alpha = 1) => {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    };

    const effectColor = computed(() => props.clan.effect_color || '#60a5fa');
</script>

<template>
    <div
        class="group relative overflow-hidden rounded-xl transition-all duration-300 hover:scale-[1.01]"
        :class="highlighted ? 'bg-gradient-to-br from-blue-600/10 to-blue-800/10 border-2 border-blue-500/30' : 'bg-gradient-to-br from-white/5 to-white/[0.02] border border-white/10 hover:border-blue-500/30'"
    >
        <!-- Background Image -->
        <div v-if="clan.background" class="absolute inset-0 opacity-30 group-hover:opacity-40 transition-opacity duration-500">
            <img :src="`/storage/${clan.background}`" class="w-full h-full object-cover" alt="Clan Background" />
        </div>

        <!-- Hover Glow Effect -->
        <div class="absolute inset-0 bg-gradient-to-r from-blue-600/0 to-blue-800/0 group-hover:from-blue-600/5 group-hover:to-blue-800/5 transition-all duration-500"></div>

        <!-- Content -->
        <div class="relative p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <!-- Clan Info -->
                <Link :href="route('clans.show', clan.id)" class="flex items-center gap-4 min-w-0 flex-1 group/link">
                    <!-- Clan Avatar with Glow -->
                    <div class="relative flex-shrink-0">
                        <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-blue-600 blur-lg opacity-0 group-hover/link:opacity-40 transition-opacity duration-500 rounded-full"></div>
                        <img
                            class="relative h-16 w-16 rounded-full object-cover ring-2 ring-white/20 group-hover/link:ring-blue-500/60 transition-all duration-300"
                            :src="`/storage/${clan.image}`"
                        />
                    </div>

                    <!-- Clan Name & Admin -->
                    <div class="min-w-0 flex-1">
                        <div class="relative">
                            <!-- Main Clan Name -->
                            <h3 class="relative z-10 text-2xl font-bold text-white group-hover/link:text-blue-400 transition-colors mb-1 truncate" v-html="q3tohtml(clan.name)"></h3>

                            <!-- Particles Effect -->
                            <div v-if="clan.name_effect === 'particles'" class="absolute inset-0 -inset-x-12 -inset-y-6 pointer-events-none">
                                <div class="absolute top-[20%] left-[15%] w-2 h-2 rounded-full" :style="`background-color: ${effectColor}; box-shadow: 0 0 15px ${hexToRgba(effectColor, 0.8)}; animation: particle-float 3s ease-in-out infinite;`"></div>
                                <div class="absolute top-[70%] left-[25%] w-1.5 h-1.5 rounded-full" :style="`background-color: ${effectColor}; box-shadow: 0 0 10px ${hexToRgba(effectColor, 0.8)}; animation: particle-float 2.5s ease-in-out infinite; animation-delay: 0.5s;`"></div>
                                <div class="absolute top-[40%] left-[80%] w-2 h-2 rounded-full" :style="`background-color: ${effectColor}; box-shadow: 0 0 15px ${hexToRgba(effectColor, 0.8)}; animation: particle-float 2.8s ease-in-out infinite; animation-delay: 1s;`"></div>
                            </div>

                            <!-- Orbs Effect -->
                            <div v-if="clan.name_effect === 'orbs'" class="absolute inset-0 -inset-x-12 -inset-y-6 pointer-events-none">
                                <div class="absolute top-[30%] left-[10%] w-6 h-6 rounded-full" :style="`background: radial-gradient(circle at 30% 30%, ${hexToRgba(effectColor, 0.8)}, ${hexToRgba(effectColor, 0.2)}); box-shadow: 0 0 30px ${hexToRgba(effectColor, 0.6)}, inset 0 0 20px ${hexToRgba(effectColor, 0.4)}; animation: orb-float 4s ease-in-out infinite;`"></div>
                                <div class="absolute top-[60%] left-[85%] w-8 h-8 rounded-full" :style="`background: radial-gradient(circle at 30% 30%, ${hexToRgba(effectColor, 0.7)}, ${hexToRgba(effectColor, 0.1)}); box-shadow: 0 0 40px ${hexToRgba(effectColor, 0.5)}, inset 0 0 25px ${hexToRgba(effectColor, 0.3)}; animation: orb-float 3.5s ease-in-out infinite; animation-delay: 0.5s;`"></div>
                            </div>

                            <!-- Lines Effect -->
                            <div v-if="clan.name_effect === 'lines'" class="absolute inset-0 -inset-x-12 -inset-y-6 pointer-events-none overflow-hidden">
                                <div class="absolute top-0 left-0 right-0 h-px" :style="`background: linear-gradient(90deg, transparent, ${hexToRgba(effectColor, 0.8)}, transparent); box-shadow: 0 0 10px ${hexToRgba(effectColor, 0.6)}; animation: line-scan 2s linear infinite;`"></div>
                                <div class="absolute top-1/2 left-0 right-0 h-px" :style="`background: linear-gradient(90deg, transparent, ${hexToRgba(effectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(effectColor, 0.4)}; animation: line-scan 2.5s linear infinite; animation-delay: 0.3s;`"></div>
                            </div>

                            <!-- Matrix Effect -->
                            <div v-if="clan.name_effect === 'matrix'" class="absolute inset-0 -inset-x-12 -inset-y-6 pointer-events-none overflow-hidden font-mono text-xs">
                                <div class="absolute top-0 left-[20%] opacity-30" :style="`color: ${effectColor}; animation: matrix-fall 3s linear infinite;`">10</div>
                                <div class="absolute top-0 left-[50%] opacity-30" :style="`color: ${effectColor}; animation: matrix-fall 2.5s linear infinite; animation-delay: 0.5s;`">01</div>
                                <div class="absolute top-0 left-[75%] opacity-30" :style="`color: ${effectColor}; animation: matrix-fall 3.2s linear infinite; animation-delay: 1s;`">11</div>
                            </div>

                            <!-- Glitch Effect -->
                            <div v-if="clan.name_effect === 'glitch'" class="absolute inset-0 pointer-events-none flex items-center">
                                <h3 class="absolute text-2xl font-bold blur-[1px] truncate" :style="`color: ${hexToRgba(effectColor, 0.3)}; animation: glitch-text 0.5s infinite; transform: translate(2px, -1px);`" v-html="q3tohtml(clan.name)"></h3>
                                <h3 class="absolute text-2xl font-bold blur-[1px] truncate" :style="`color: ${hexToRgba(effectColor, 0.3)}; animation: glitch-text 0.5s infinite; animation-delay: 0.2s; transform: translate(-2px, 1px);`" v-html="q3tohtml(clan.name)"></h3>
                            </div>

                            <!-- Wave Text Effect -->
                            <div v-if="clan.name_effect === 'wave'" class="absolute inset-0 pointer-events-none flex items-center">
                                <h3 class="absolute text-2xl font-bold truncate" :style="`color: ${hexToRgba(effectColor, 0.5)}; animation: wave-text 2s ease-in-out infinite;`" v-html="q3tohtml(clan.name)"></h3>
                            </div>

                            <!-- Neon Pulse Effect -->
                            <div v-if="clan.name_effect === 'neon'" class="absolute inset-0 pointer-events-none flex items-center">
                                <h3 class="absolute text-2xl font-bold truncate" :style="`color: ${effectColor}; text-shadow: 0 0 10px ${hexToRgba(effectColor, 0.8)}, 0 0 20px ${hexToRgba(effectColor, 0.6)}, 0 0 30px ${hexToRgba(effectColor, 0.4)}; animation: neon-pulse 2s ease-in-out infinite;`" v-html="q3tohtml(clan.name)"></h3>
                            </div>

                            <!-- RGB Split Effect -->
                            <div v-if="clan.name_effect === 'rgb'" class="absolute inset-0 pointer-events-none flex items-center">
                                <h3 class="absolute text-2xl font-bold truncate" :style="`color: ${effectColor}; animation: rgb-split 3s ease-in-out infinite;`" v-html="q3tohtml(clan.name)"></h3>
                            </div>

                            <!-- Flicker Effect -->
                            <div v-if="clan.name_effect === 'flicker'" class="absolute inset-0 pointer-events-none flex items-center">
                                <h3 class="absolute text-2xl font-bold truncate" :style="`color: ${effectColor}; text-shadow: 0 0 10px ${hexToRgba(effectColor, 0.8)}; animation: flicker 4s linear infinite;`" v-html="q3tohtml(clan.name)"></h3>
                            </div>

                            <!-- Hologram Effect -->
                            <div v-if="clan.name_effect === 'hologram'" class="absolute inset-0 pointer-events-none flex items-center overflow-hidden">
                                <h3 class="absolute text-2xl font-bold truncate" :style="`color: ${effectColor}; text-shadow: 0 0 10px ${hexToRgba(effectColor, 0.6)}; animation: hologram 3s ease-in-out infinite;`" v-html="q3tohtml(clan.name)"></h3>
                                <div class="absolute inset-0 pointer-events-none" :style="`background: linear-gradient(0deg, transparent 0%, ${hexToRgba(effectColor, 0.1)} 50%, transparent 100%); animation: line-scan 2s linear infinite;`"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-500">Admin:</span>
                            <Link
                                :href="route('profile.index', clan.admin.id)"
                                class="text-gray-300 hover:text-blue-400 transition-colors"
                                v-html="q3tohtml(clan.admin.name)"
                                @click.stop
                            ></Link>
                        </div>
                    </div>
                </Link>

                <!-- Stats, Members & Management -->
                <div class="flex items-center gap-4">
                    <!-- Achievement Stats -->
                    <div class="flex items-center gap-2">
                        <!-- World Records Badge -->
                        <div v-if="clan.total_wrs > 0" class="flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-yellow-600/20 to-amber-600/20 border border-yellow-500/30 rounded-lg" title="Total World Records">
                            <img src="/images/powerups/quad.svg" class="w-5 h-5" alt="WRs" />
                            <span class="text-yellow-400 text-sm font-bold">{{ clan.total_wrs }}</span>
                        </div>

                        <!-- Top 3 Positions Badge -->
                        <div v-if="clan.total_top3 > 0" class="flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-blue-600/20 to-cyan-600/20 border border-blue-500/30 rounded-lg" title="Total Top 3 Positions">
                            <img src="/images/powerups/haste.svg" class="w-5 h-5" alt="Top 3" />
                            <span class="text-blue-400 text-sm font-bold">{{ clan.total_top3 }}</span>
                        </div>
                    </div>

                    <!-- Member Avatars - Show All (hidden in manage mode) -->
                    <div v-if="!manage" class="flex items-center">
                        <div class="flex -space-x-3">
                            <div v-for="(player, index) in clan.players" :key="player.id" class="relative">
                                <Popper placement="bottom" arrow hover :offsetDistance="12" appendTo="body">
                                    <Link
                                        :href="route('profile.index', player.user_id)"
                                        @click.stop
                                        class="block transition-transform hover:scale-110 relative z-10 hover:z-50"
                                    >
                                        <img
                                            class="h-10 w-10 rounded-full object-cover ring-2 ring-gray-800 hover:ring-blue-500 transition-all"
                                            :src="player.user?.profile_photo_path ? `/storage/${player.user.profile_photo_path}` : '/images/null.jpg'"
                                        />
                                    </Link>
                                    <template #content>
                                        <div class="py-2 px-3 bg-gray-900 backdrop-blur-sm rounded-lg border border-white/10 whitespace-nowrap">
                                            <div class="text-gray-200 font-medium" v-html="q3tohtml(player.user?.name)"></div>
                                        </div>
                                    </template>
                                </Popper>
                            </div>
                        </div>
                    </div>

                    <!-- Management Buttons -->
                    <div v-if="manage" class="flex flex-wrap gap-2">
                    <button
                        v-if="clan.admin_id === $page.props.auth.user.id"
                        @click="InvitePlayer"
                        class="flex items-center justify-center gap-1.5 px-3 py-2 bg-gradient-to-r from-green-600/50 to-emerald-600/50 hover:from-green-600/60 hover:to-emerald-600/60 border border-green-500/50 hover:border-green-500/70 rounded-lg text-white transition-all duration-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                        <span class="text-xs font-medium">Invite</span>
                    </button>

                    <button
                        v-if="clan.admin_id === $page.props.auth.user.id"
                        @click="KickPlayer"
                        class="flex items-center justify-center gap-1.5 px-3 py-2 bg-gradient-to-r from-orange-600/50 to-yellow-600/50 hover:from-orange-600/60 hover:to-yellow-600/60 border border-orange-500/50 hover:border-orange-500/70 rounded-lg text-white transition-all duration-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                        </svg>
                        <span class="text-xs font-medium">Kick</span>
                    </button>

                    <button
                        v-if="clan.admin_id === $page.props.auth.user.id"
                        @click="EditClan"
                        class="flex items-center justify-center gap-1.5 px-3 py-2 bg-gradient-to-r from-gray-600/50 to-slate-600/50 hover:from-gray-600/60 hover:to-slate-600/60 border border-gray-500/50 hover:border-gray-500/70 rounded-lg text-white transition-all duration-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        <span class="text-xs font-medium">Edit Clan</span>
                    </button>

                    <button
                        v-if="clan.admin_id === $page.props.auth.user.id && EditMemberNotes"
                        @click="EditMemberNotes"
                        class="flex items-center justify-center gap-1.5 px-3 py-2 bg-gradient-to-r from-blue-600/50 to-blue-800/50 hover:from-blue-600/60 hover:to-blue-800/60 border border-blue-500/50 hover:border-blue-500/70 rounded-lg text-white transition-all duration-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        <span class="text-xs font-medium">Member Details</span>
                    </button>

                    <button
                        v-if="clan.admin_id === $page.props.auth.user.id"
                        @click="TransferOwnership"
                        class="flex items-center justify-center gap-1.5 px-3 py-2 bg-gradient-to-r from-sky-600/50 to-cyan-600/50 hover:from-sky-600/60 hover:to-cyan-600/60 border border-sky-500/50 hover:border-sky-500/70 rounded-lg text-white transition-all duration-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 0 0 4.5 9.75v7.5a2.25 2.25 0 0 0 2.25 2.25h7.5a2.25 2.25 0 0 0 2.25-2.25v-7.5a2.25 2.25 0 0 0-2.25-2.25h-.75m0-3-3-3m0 0-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25v-.75" />
                        </svg>
                        <span class="text-xs font-medium">Transfer</span>
                    </button>

                    <button
                        v-if="clan.admin_id !== $page.props.auth.user.id"
                        @click="LeaveClan"
                        class="flex items-center justify-center gap-1.5 px-3 py-2 bg-gradient-to-r from-red-600/50 to-rose-600/50 hover:from-red-600/60 hover:to-rose-600/60 border border-red-500/50 hover:border-red-500/70 rounded-lg text-white transition-all duration-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        <span class="text-xs font-medium">Leave</span>
                    </button>

                    <button
                        v-if="clan.admin_id === $page.props.auth.user.id"
                        @click="DismantleClan"
                        class="flex items-center justify-center gap-1.5 px-3 py-2 bg-gradient-to-r from-red-600/50 to-rose-600/50 hover:from-red-600/60 hover:to-rose-600/60 border border-red-500/50 hover:border-red-500/70 rounded-lg text-white transition-all duration-300"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        <span class="text-xs font-medium">Dismantle</span>
                    </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
