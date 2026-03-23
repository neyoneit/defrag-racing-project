<script setup>
    import { Head, router, Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { ref, computed, onMounted, getCurrentInstance } from 'vue';

    const props = defineProps({
        scores: Object,
        myScore: Object,
        tiers: Array,
        weights: Object,
    });

    const loaded = ref(props.scores !== null);
    const expandedRow = ref(null);

    const { proxy } = getCurrentInstance();
    const q3tohtml = proxy.q3tohtml;

    const getTier = (badgeScore) => {
        if (!props.tiers) return null;
        let tier = null;
        for (const t of props.tiers) {
            if (badgeScore >= t.min_score) {
                tier = t;
            }
        }
        return tier;
    };

    const myTier = computed(() => {
        if (!props.myScore) return null;
        return getTier(props.myScore.community_badge_score);
    });

    const toggleExpand = (id) => {
        expandedRow.value = expandedRow.value === id ? null : id;
    };

    const categories = [
        { key: 'demos_uploaded', label: 'Demos Uploaded', icon: '📁', desc: 'Demos uploaded and processed on the site' },
        { key: 'tags_added', label: 'Tags Added', icon: '🏷', desc: 'Tags added to maps to help others find them' },
        { key: 'alias_reports', label: 'Alias Reports', icon: '🔍', desc: 'Player alias reports that were resolved' },
        { key: 'demo_assignment_reports', label: 'Demo Reports', icon: '📋', desc: 'Demo assignment reports that were approved' },
        { key: 'maplists_created', label: 'Maplists Created', icon: '📝', desc: 'Public maplists created for the community' },
        { key: 'maplist_maps_added', label: 'Maplist Maps', icon: '🗺', desc: 'Maps added to public maplists' },
        { key: 'maplist_likes_received', label: 'Maplist Likes', icon: '👍', desc: 'Likes received on your maplists' },
        { key: 'maplist_favorites_received', label: 'Maplist Favorites', icon: '⭐', desc: 'Favorites received on your maplists' },
        { key: 'play_later_maps', label: 'Play Later Maps', icon: '⏰', desc: 'Maps saved to your Play Later list' },
        { key: 'marketplace_listings', label: 'Marketplace Listings', icon: '🛒', desc: 'Listings created on the marketplace' },
        { key: 'marketplace_reviews_written', label: 'Reviews Written', icon: '✍', desc: 'Reviews written for marketplace transactions' },
        { key: 'marketplace_reviews_received', label: 'Reviews Received', icon: '💬', desc: 'Reviews received from marketplace transactions' },
        { key: 'headhunter_created', label: 'Challenges Created', icon: '🎯', desc: 'Headhunter challenges created for others' },
        { key: 'headhunter_completed', label: 'Challenges Completed', icon: '🏅', desc: 'Headhunter challenges completed and approved' },
        { key: 'record_flags', label: 'Record Flags', icon: '🚩', desc: 'Suspicious records flagged and approved by admin' },
        { key: 'models_uploaded', label: 'Models Uploaded', icon: '🎮', desc: 'Player models uploaded and approved' },
        { key: 'render_requests', label: 'Render Requests', icon: '🎬', desc: 'YouTube video renders requested and completed' },
        { key: 'clan_created', label: 'Clans Created', icon: '⚔', desc: 'Clans founded as owner' },
        { key: 'clan_membership', label: 'Clan Member', icon: '🛡', desc: 'Being a member of a clan' },
        { key: 'nsfw_flags', label: 'NSFW Flags', icon: '🔞', desc: 'Maps flagged as NSFW content' },
        { key: 'records_count', label: 'Records', icon: '🏆', desc: 'Total records set in the game' },
        { key: 'maps_authored', label: 'Maps Authored', icon: '🗺', desc: 'Maps you created as a mapper' },
        { key: 'models_authored', label: 'Models Authored', icon: '🎨', desc: 'Player models you created as a modeler' },
        { key: 'social_connections', label: 'Social Connected', icon: '🔗', desc: 'Social accounts connected via OAuth' },
        { key: 'profile_avatar', label: 'Custom Avatar', icon: '🖼', desc: 'Uploaded a custom profile avatar' },
        { key: 'profile_background', label: 'Custom Background', icon: '🌄', desc: 'Uploaded a custom profile background' },
        { key: 'profile_layout_customized', label: 'Custom Layout', icon: '📐', desc: 'Customized your profile layout' },
        { key: 'name_effect_set', label: 'Name Effect', icon: '✨', desc: 'Set a name effect on your profile' },
        { key: 'avatar_effect_set', label: 'Avatar Effect', icon: '💫', desc: 'Set an avatar effect on your profile' },
        { key: 'donation_total_eur', label: 'Donations (EUR)', icon: '💰', desc: 'Donated to support the site' },
    ];

    const getTopCategories = (score) => {
        if (!score || !props.weights) return [];
        return categories
            .map(c => {
                let val = score[c.key];
                if (val === true) val = 1;
                if (val === false) val = 0;
                const w = props.weights[c.key] || 0;
                return { ...c, value: val, points: Math.round(val * w * 100) / 100 };
            })
            .filter(c => c.points > 0)
            .sort((a, b) => b.points - a.points)
            .slice(0, 5);
    };

    const getCategoryPoints = (score, cat) => {
        if (!score || !props.weights) return 0;
        let val = score[cat.key];
        if (val === true) val = 1;
        if (val === false) val = 0;
        return Math.round(val * (props.weights[cat.key] || 0) * 100) / 100;
    };

    const loadData = (page = 1) => {
        loaded.value = false;
        router.reload({
            only: ['scores'],
            data: { page },
            onFinish: () => {
                loaded.value = true;
            }
        });
    };

    onMounted(() => {
        if (!props.scores) {
            loadData();
        }
    });
</script>

<template>
    <Head title="Defragger Leaderboard" />

    <!-- Header Section with gradient fade -->
    <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
            <div class="mb-8">
                <h1 class="text-4xl md:text-5xl font-black text-gray-300/90 mb-2">Defragger Leaderboard</h1>
                <p class="text-gray-400">Who contributes the most to our community? This leaderboard celebrates those who go beyond just playing - the ones who upload demos, tag maps, create content, help moderate, and make defrag a better experience for everyone.</p>
            </div>
        </div>
    </div>

    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-[22rem] relative z-10 pb-12">

        <!-- My Score Card -->
        <div v-if="myScore" class="mb-6 bg-gray-800/50 border border-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="text-2xl font-bold text-gray-400">#{{ myScore.rank }}</div>
                    <div>
                        <div class="text-white font-semibold">Your Score</div>
                        <div class="text-gray-400 text-sm">
                            {{ parseFloat(myScore.total_score).toFixed(1) }} points
                            <span v-if="myTier" :style="{ color: myTier.color }" class="ml-2 font-semibold">
                                <img src="/images/svg/badge-defragger.png" class="w-3.5 h-3.5 inline" :style="{ filter: `drop-shadow(0 0 2px ${myTier.color})` }">
                                {{ myTier.name }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <div v-for="cat in getTopCategories(myScore)" :key="cat.key"
                        class="px-2 py-1 bg-gray-700/50 rounded text-xs text-gray-300"
                        :title="cat.desc">
                        {{ cat.icon }} {{ cat.points }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Tier Legend + How it works -->
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <div v-for="tier in tiers" :key="tier.key"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm"
                :style="{ borderColor: tier.color + '50', backgroundColor: tier.color + '15' }">
                <img src="/images/svg/badge-defragger.png" class="w-3.5 h-3.5" :style="{ filter: `drop-shadow(0 0 2px ${tier.color})` }">
                <span class="font-bold" :style="{ color: tier.color }">{{ tier.name }}</span>
                <span class="text-xs" :style="{ color: tier.color + '99' }">{{ tier.min_score }}+</span>
            </div>
            <div class="group/how relative">
                <span class="text-xs text-gray-500 hover:text-gray-300 cursor-help transition border-b border-dashed border-gray-600">How it works</span>
                <div class="absolute left-0 top-full mt-2 hidden group-hover/how:block bg-gray-900/95 border border-white/20 rounded-lg px-4 py-3 text-xs text-gray-200 shadow-xl z-50 pointer-events-none w-80">
                    <div class="font-bold text-white mb-2">How scoring works</div>
                    <div class="text-gray-400 mb-2">Points are earned by contributing to the community. Each action has a different weight:</div>
                    <div class="text-gray-300 space-y-0.5">
                        <div>+ Demos uploaded, tags added to maps</div>
                        <div>+ Maplists created, maps curated</div>
                        <div>+ Alias & demo reports (only approved)</div>
                        <div>+ Headhunter challenges & completions</div>
                        <div>+ Models uploaded & approved</div>
                        <div>+ Marketplace listings & reviews</div>
                        <div>+ Record flags (only approved)</div>
                        <div>+ Video render requests (completed)</div>
                        <div>+ Clan creation & membership</div>
                        <div>+ Profile customization, social connections</div>
                        <div>+ Donations, map/model authoring</div>
                    </div>
                    <div class="mt-2 pt-2 border-t border-white/10 text-gray-500">
                        <div>Records count with very low weight (0.1).</div>
                        <div>Badge tier is based on score excluding records.</div>
                        <div>Scores recalculated daily. Click a row for full breakdown.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="!loaded" class="text-center py-20">
            <div class="inline-block w-8 h-8 border-2 border-gray-600 border-t-blue-500 rounded-full animate-spin"></div>
            <div class="text-gray-400 mt-3">Loading leaderboard...</div>
        </div>

        <!-- Leaderboard Table -->
        <div v-else-if="scores && scores.data && scores.data.length > 0" class="bg-gray-800/30 rounded-lg border border-gray-700 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700 text-gray-400 text-sm">
                        <th class="text-left py-3 px-4 w-16">Rank</th>
                        <th class="text-left py-3 px-4">Player</th>
                        <th class="text-left py-3 px-4 hidden md:table-cell">Top Contributions</th>
                        <th class="text-right py-3 px-4 w-24">Score</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="score in scores.data" :key="score.id">
                        <tr @click="toggleExpand(score.id)"
                            class="border-b border-gray-700/50 hover:bg-gray-700/30 cursor-pointer transition-colors"
                            :class="{ 'bg-gray-700/20': expandedRow === score.id }"
                            :style="getTier(score.community_badge_score) ? { borderLeft: `4px solid ${getTier(score.community_badge_score).color}` } : { borderLeft: '4px solid transparent' }">
                            <td class="py-3 px-4">
                                <span class="font-mono font-bold" :style="getTier(score.community_badge_score) ? { color: getTier(score.community_badge_score).color } : { color: '#9ca3af' }">{{ score.rank }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <img v-if="score.user?.profile_photo_path" :src="score.user.profile_photo_path" class="w-8 h-8 rounded-full object-cover" onerror="this.style.display='none'">
                                    <div v-else class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-gray-500 text-xs">?</div>
                                    <div class="flex items-center gap-2">
                                        <img v-if="score.user?.country" :src="`/images/flags/${score.user.country}.png`" class="w-5 h-3.5" onerror="this.style.display='none'">
                                        <Link :href="`/profile/${score.user?.id}`" class="hover:text-blue-400 transition" :style="getTier(score.community_badge_score) ? { color: getTier(score.community_badge_score).color } : { color: 'white' }" @click.stop>
                                            <span v-html="q3tohtml(score.user?.name)"></span>
                                        </Link>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 hidden md:table-cell">
                                <div class="flex gap-1.5 flex-wrap">
                                    <span v-for="cat in getTopCategories(score)" :key="cat.key" class="px-1.5 py-0.5 bg-gray-700/50 rounded text-xs text-gray-400" :title="cat.desc">
                                        {{ cat.icon }} {{ cat.value }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <span class="font-semibold" :style="getTier(score.community_badge_score) ? { color: getTier(score.community_badge_score).color } : { color: 'white' }">{{ parseFloat(score.total_score).toFixed(1) }}</span>
                            </td>
                        </tr>

                        <!-- Expanded Row -->
                        <tr v-if="expandedRow === score.id">
                            <td colspan="4" class="bg-gray-800/50 px-4 py-4 border-b border-gray-700/50">
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                    <div v-for="cat in categories" :key="cat.key"
                                        class="group/cat relative flex items-center gap-2 px-2 py-1.5 rounded"
                                        :class="getCategoryPoints(score, cat) > 0 ? 'bg-gray-700/50 text-gray-200' : 'text-gray-600'">
                                        <span class="text-sm">{{ cat.icon }}</span>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs truncate">{{ cat.label }}</div>
                                            <div class="text-xs font-mono">
                                                <span>{{ score[cat.key] === true ? 'Yes' : score[cat.key] === false ? 'No' : score[cat.key] }}</span>
                                                <span v-if="getCategoryPoints(score, cat) > 0" class="text-green-400 ml-1">(+{{ getCategoryPoints(score, cat) }})</span>
                                            </div>
                                        </div>
                                        <!-- Hover tooltip -->
                                        <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1 hidden group-hover/cat:block bg-gray-900/95 border border-white/20 rounded-lg px-3 py-2 text-xs text-gray-200 whitespace-nowrap shadow-xl z-50 pointer-events-none">
                                            <div class="font-bold text-white mb-0.5">{{ cat.label }}</div>
                                            <div class="text-gray-400">{{ cat.desc }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-t border-gray-700/50 flex items-center gap-4 text-sm">
                                    <span class="text-gray-400">Total: <span class="text-white font-bold">{{ parseFloat(score.total_score).toFixed(1) }}</span></span>
                                    <span class="text-gray-400">Community: <span class="text-white font-bold">{{ parseFloat(score.community_badge_score).toFixed(1) }}</span></span>
                                    <span v-if="getTier(score.community_badge_score)" class="inline-flex items-center gap-1 font-semibold" :style="{ color: getTier(score.community_badge_score)?.color }">
                                        <img src="/images/svg/badge-defragger.png" class="w-3.5 h-3.5" :style="{ filter: `drop-shadow(0 0 2px ${getTier(score.community_badge_score).color})` }">
                                        {{ getTier(score.community_badge_score)?.name }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="scores.last_page > 1" class="p-4 border-t border-gray-700">
                <Pagination :data="scores" @page-changed="loadData" />
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="loaded" class="text-center py-20">
            <div class="text-gray-500 text-lg">No community scores calculated yet.</div>
            <div class="text-gray-600 text-sm mt-2">Scores are calculated daily. Check back later.</div>
        </div>
    </div>
</template>
