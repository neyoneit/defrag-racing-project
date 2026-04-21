<script setup>
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import MapCardLine from '@/Components/MapCardLine.vue';
    import MapRecord from '@/Components/MapRecord.vue';
    import TimeHistoryExpand from '@/Components/TimeHistoryExpand.vue';
    // import MapRecordSmall from '@/Components/MapRecordSmall.vue'; // Obsolete - using MapRecord for all screen sizes now
    import MapCardLineSmall from '@/Components/MapCardLineSmall.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import ToggleButton from '@/Components/Basic/ToggleButton.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import AddToMaplistModal from '@/Components/Maplists/AddToMaplistModal.vue';
    import CopyButton from '@/Components/Basic/CopyButton.vue';
    import { watchEffect, watch, ref, onMounted, onUnmounted, computed } from 'vue';
    import { useClipboard } from '@/Composables/useClipboard';
    import axios from 'axios';

    const { copy, copyState } = useClipboard();
    const showAddToMaplistModal = ref(false);

    // NSFW flagging
    const flaggingNsfw = ref(false);
    const localIsNsfw = ref(false);


    // Assign demo to online record
    const showAssignModal = ref(false);
    const assigningRecord = ref(null);
    const assignPhysics = ref('VQ3');

    // Score tooltip
    const scoreTooltip = ref(null);
    const scoreTooltipStyle = computed(() => {
        if (!scoreTooltip.value?.el) return {};
        const rect = scoreTooltip.value.el.getBoundingClientRect();
        return {
            position: 'fixed',
            left: rect.left + rect.width / 2 + 'px',
            top: rect.top - 8 + 'px',
            transform: 'translate(-50%, -100%)',
            zIndex: 99999,
        };
    });
    const availableRecords = ref([]);
    const selectedRecordId = ref(null);
    const loadingRecords = ref(false);

    const openAssignModal = async (record, physics) => {
        assigningRecord.value = record;
        assignPhysics.value = physics;
        showAssignModal.value = true;
        selectedRecordId.value = null;

        // Load online records for this map + physics
        loadingRecords.value = true;
        try {
            const response = await axios.get(`/demos/maps/${encodeURIComponent(props.map.name)}/records`, {
                params: { physics: physics }
            });
            availableRecords.value = response.data;
        } catch (error) {
            console.error('Error loading records:', error);
        } finally {
            loadingRecords.value = false;
        }
    };

    // Suggested matches — records sorted by time distance to demo's time
    const suggestedRecords = computed(() => {
        if (!assigningRecord.value || availableRecords.value.length === 0) return [];
        const demoTime = assigningRecord.value.time_ms || assigningRecord.value.time;
        if (!demoTime) return [];
        return [...availableRecords.value]
            .map(r => ({ ...r, timeDiff: Math.abs(r.time - demoTime) }))
            .sort((a, b) => a.timeDiff - b.timeDiff)
            .slice(0, 5);
    });

    const closeAssignModal = () => {
        showAssignModal.value = false;
        assigningRecord.value = null;
        availableRecords.value = [];
        selectedRecordId.value = null;
    };

    const assignDemoToRecord = async () => {
        if (!selectedRecordId.value || !assigningRecord.value?.demo) return;

        try {
            const response = await axios.post(`/demos/${assigningRecord.value.demo.id}/assign`, {
                record_id: selectedRecordId.value
            });

            if (response.data.success) {
                closeAssignModal();
                router.reload();
            }
        } catch (error) {
            console.error('Error assigning demo:', error);
            alert('Failed to assign demo. ' + (error.response?.data?.message || 'Please try again.'));
        }
    };

    // Reverse assign: record → demo matches
    const demoMatchesMap = ref({});
    const showReverseAssignModal = ref(false);
    const reverseAssignRecord = ref(null);
    const reverseAssignDemos = ref([]);
    const selectedDemoId = ref(null);

    const loadDemoMatches = async () => {
        try {
            const response = await axios.get(`/maps/${encodeURIComponent(props.map.name)}/demo-matches`);
            demoMatchesMap.value = response.data;
        } catch (error) {
            console.error('Error loading demo matches:', error);
        }
    };

    const openReverseAssignModal = (record, demos) => {
        reverseAssignRecord.value = record;
        reverseAssignDemos.value = demos;
        selectedDemoId.value = demos.length > 0 ? demos[0].demo_id : null;
        showReverseAssignModal.value = true;
    };

    const closeReverseAssignModal = () => {
        showReverseAssignModal.value = false;
        reverseAssignRecord.value = null;
        reverseAssignDemos.value = [];
        selectedDemoId.value = null;
    };

    const assignDemoFromRecord = async () => {
        if (!selectedDemoId.value || !reverseAssignRecord.value) return;
        try {
            const response = await axios.post(`/demos/${selectedDemoId.value}/assign`, {
                record_id: reverseAssignRecord.value.id
            });
            if (response.data.success) {
                closeReverseAssignModal();
                loadDemoMatches();
                router.reload();
            }
        } catch (error) {
            console.error('Error assigning demo:', error);
            alert('Failed to assign demo. ' + (error.response?.data?.message || 'Please try again.'));
        }
    };

    // Reassign: record already has a demo, allow changing or removing
    const showReassignModal = ref(false);
    const reassignRecord = ref(null);
    const reassignCurrentDemo = ref(null);

    const openReassignModal = (record) => {
        reassignRecord.value = record;
        reassignCurrentDemo.value = record.uploaded_demos?.[0] || null;
        showReassignModal.value = true;
    };

    const closeReassignModal = () => {
        showReassignModal.value = false;
        reassignRecord.value = null;
        reassignCurrentDemo.value = null;
    };

    const unassignDemo = async () => {
        if (!reassignCurrentDemo.value) return;
        try {
            const response = await axios.post(`/demos/${reassignCurrentDemo.value.id}/unassign`);
            if (response.data.success) {
                closeReassignModal();
                loadDemoMatches();
                router.reload();
            }
        } catch (error) {
            console.error('Error unassigning demo:', error);
            alert('Failed to unassign demo. ' + (error.response?.data?.message || 'Please try again.'));
        }
    };

    const tags = ref([]);
    const availableTags = ref([]);
    const suggestedTags = ref([]);
    const newTagInput = ref('');
    const showTagSuggestions = ref(false);
    const showAllTags = ref(false);
    const showTagInfo = ref(false);
    const tagInfoBtn = ref(null);
    const tagInfoStyle = computed(() => {
        if (!tagInfoBtn.value) return {};
        const rect = tagInfoBtn.value.getBoundingClientRect();
        return {
            top: rect.bottom + 2 + 'px',
            right: (window.innerWidth - rect.right) + 'px',
        };
    });
    const tagInputContainer = ref(null);
    const tagDropdownStyle = computed(() => {
        if (!tagInputContainer.value) return {};
        const rect = tagInputContainer.value.getBoundingClientRect();
        return {
            position: 'fixed',
            top: rect.bottom + 2 + 'px',
            left: rect.left + 'px',
            width: rect.width + 'px',
        };
    });
    const addingTag = ref(false);
    const adoptingTag = ref(false);

    const props = defineProps({
        map: Object,
        cpmRecords: Object,
        vq3Records: Object,
        cpmOldRecords: Object,
        vq3OldRecords: Object,
        cpmOfflineRecords: Object,
        vq3OfflineRecords: Object,
        my_cpm_record: Object,
        my_vq3_record: Object,
        gametypeStats: Object,
        servers: Array,
        publicMaplists: {
            type: Array,
            default: () => []
        },
        showOldtop: {
            type: Boolean,
            default: false
        },
        showOffline: {
            type: Boolean,
            default: false
        },
        difficultyRating: {
            type: Object,
            default: () => ({ average: null, total: 0, distribution: {}, user_rating: null })
        },
        // Server-computed cluster metadata for time-history badges. Keyed by
        // uploaded_demo.id; each value is { count, signals }. Pre-computed so
        // the collapsed leaderboard badge matches the opened drawer numbers
        // exactly (no drift from pagination / client-only dedupe).
        clusterMetaVq3: {
            type: Object,
            default: () => ({})
        },
        clusterMetaCpm: {
            type: Object,
            default: () => ({})
        }
    });

    const page = usePage();
    localIsNsfw.value = props.map.is_nsfw;

    const dateColWidth = computed(() => {
        const fmt = page.props.dateFormat;
        return (fmt === 'Ymd' || fmt === 'dmY') ? 'w-[62px]' : 'w-[50px]';
    });

    // Difficulty rating
    const difficultyData = ref({ ...props.difficultyRating });
    const submittingDifficulty = ref(false);
    const difficultyLabels = [
        { level: 1, label: 'Beginner', color: 'bg-green-600', hoverColor: 'hover:bg-green-500', borderColor: 'border-green-400', activeRing: 'ring-green-400', desc: 'Basic movement, no special techniques needed' },
        { level: 2, label: 'Easy', color: 'bg-lime-600', hoverColor: 'hover:bg-lime-500', borderColor: 'border-lime-400', activeRing: 'ring-lime-400', desc: 'Simple tricks like circle jumps or ramp jumps' },
        { level: 3, label: 'Medium', color: 'bg-yellow-600', hoverColor: 'hover:bg-yellow-500', borderColor: 'border-yellow-400', activeRing: 'ring-yellow-400', desc: 'Weapon boosts or precise strafing required' },
        { level: 4, label: 'Hard', color: 'bg-orange-600', hoverColor: 'hover:bg-orange-500', borderColor: 'border-orange-400', activeRing: 'ring-orange-400', desc: 'Advanced technique combinations, requires practice' },
        { level: 5, label: 'Extreme', color: 'bg-red-600', hoverColor: 'hover:bg-red-500', borderColor: 'border-red-400', activeRing: 'ring-red-400', desc: 'Top-level techniques, very few can finish' },
    ];
    const communityDifficultyLevel = computed(() => {
        if (!difficultyData.value.average) return null;
        return Math.round(difficultyData.value.average);
    });
    const rateDifficulty = async (level) => {
        if (!page.props.auth?.user || submittingDifficulty.value) return;
        submittingDifficulty.value = true;
        try {
            const response = await axios.post(`/maps/${props.map.id}/rate-difficulty`, { rating: level });
            difficultyData.value = response.data;
        } catch (error) {
            console.error('Error rating difficulty:', error);
        } finally {
            submittingDifficulty.value = false;
        }
    };

    const needsNsfwGate = computed(() => {
        return localIsNsfw.value && !page.props.auth.user?.nsfw_confirmed;
    });

    const confirmingNsfw = ref(false);

    const confirmNsfw = () => {
        confirmingNsfw.value = true;
        router.post(route('user.confirm-nsfw'), {}, {
            preserveScroll: true,
            onFinish: () => {
                confirmingNsfw.value = false;
            },
        });
    };

    const flagAsNsfw = async () => {
        if (flaggingNsfw.value) return;
        flaggingNsfw.value = true;
        try {
            await axios.post(`/maps/${props.map.id}/flag-nsfw`);
            localIsNsfw.value = true;
        } catch (e) {
            alert('Failed to flag: ' + (e.response?.data?.message || e.message));
        } finally {
            flaggingNsfw.value = false;
        }
    };

    const unflagNsfw = async () => {
        if (flaggingNsfw.value) return;
        flaggingNsfw.value = true;
        try {
            await axios.post(`/maps/${props.map.id}/unflag-nsfw`);
            localIsNsfw.value = false;
        } catch (e) {
            alert('Failed to unflag: ' + (e.response?.data?.message || e.message));
        } finally {
            flaggingNsfw.value = false;
        }
    };

    const order = ref('ASC');
    const column = ref('time');
    const gametype = ref('run');

    const gametypes = [
        'run',
        'ctf1',
        'ctf2',
        'ctf3',
        'ctf4',
        'ctf5',
        'ctf6',
        'ctf7'
    ];

    const screenWidth = ref(window.innerWidth);

    // Initialize oldtop state from props (server/URL state is source of truth)
    const showOldtopLocal = ref(props.showOldtop);

    // Initialize offline demos state from props
    const showOfflineLocal = ref(props.showOffline);

    // Mobile physics toggle - 'both', 'VQ3', or 'CPM'
    const mobilePhysics = ref('both');

    // Physics column order preference
    const cpmFirst = computed(() => page.props.physicsOrder === 'cpm_first');

    const sortByDate = () => {
        if (column.value === 'date_set') {
            order.value = (order.value == 'ASC') ? 'DESC' : 'ASC';
        }

        column.value = 'date_set';

        router.reload({
            data: {
                sort: column.value,
                order: order.value
            }
        })
    }

    const sortByTime = () => {
        // Toggle between sorting by time (fastest) and date (when set)
        if (column.value === 'time') {
            // Switch to date sorting
            column.value = 'date_set';
            order.value = 'DESC'; // Newest first by default
        } else {
            // Switch to time sorting
            column.value = 'time';
            order.value = 'ASC'; // Fastest first by default
        }

        router.reload({
            data: {
                sort: column.value,
                order: order.value
            }
        })
    }

    const sortByGametype = (gt) => {
        gametype.value = gt;

        router.reload({
            data: {
                gametype: gt,
                sort: 'time',
                order: 'ASC'
            }
        })
    }

    watchEffect(() => {
        order.value = route().params['order'] ?? 'ASC';
        column.value = route().params['sort'] ?? 'time';
        gametype.value = route().params['gametype'] ?? 'run';
    });

    // Watch for changes to showOldtop prop and sync with local state
    watch(() => props.showOldtop, (newValue) => {
        showOldtopLocal.value = newValue;
        localStorage.setItem('mapview_show_oldtop', newValue ? '1' : '0');
    }, { immediate: true });

    // Watch for changes to showOffline prop and sync with local state
    watch(() => props.showOffline, (newValue) => {
        showOfflineLocal.value = newValue;
        localStorage.setItem('mapview_show_offline', newValue ? '1' : '0');
    }, { immediate: true });


    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    };

    const onChangeOldtop = (value) => {
        showOldtopLocal.value = value;
        localStorage.setItem('mapview_show_oldtop', value ? '1' : '0');
        router.reload({
            data: {
                showOldtop: value,
                showOffline: showOfflineLocal.value
            }
        })
    }

    const onChangeOffline = (value) => {
        showOfflineLocal.value = value;
        localStorage.setItem('mapview_show_offline', value ? '1' : '0');
        router.reload({
            data: {
                showOffline: value,
                showOldtop: showOldtopLocal.value
            }
        })
    }

    // Initialize tags from map data
    onMounted(async () => {
        if (props.map.tags) {
            tags.value = [...props.map.tags].sort((a, b) => a.display_name.localeCompare(b.display_name));
        }
        // Fetch all tags and sort alphabetically
        try {
            const response = await axios.get('/api/tags');
            availableTags.value = response.data.tags.sort((a, b) =>
                a.display_name.localeCompare(b.display_name)
            );
        } catch (error) {
            console.error('Error fetching tags:', error);
        }

        // Fetch suggested tags from maplists
        try {
            const response = await axios.get(`/api/maps/${props.map.id}/suggested-tags`);
            suggestedTags.value = response.data.suggested_tags;
        } catch (error) {
            console.error('Error fetching suggested tags:', error);
        }

        // Load demo matches for assign icons on records
        loadDemoMatches();
    });

    const fuzzyMatch = (text, query) => {
        const t = text.toLowerCase();
        const q = query.toLowerCase();
        if (t.includes(q)) return { match: true, score: 0 };
        let ti = 0;
        let consecutive = 0;
        let maxConsecutive = 0;
        let matched = 0;
        for (let qi = 0; qi < q.length; qi++) {
            let found = false;
            while (ti < t.length) {
                if (t[ti] === q[qi]) {
                    matched++;
                    consecutive++;
                    maxConsecutive = Math.max(maxConsecutive, consecutive);
                    ti++;
                    found = true;
                    break;
                }
                consecutive = 0;
                ti++;
            }
            if (!found) return { match: false, score: Infinity };
        }
        return { match: true, score: q.length - maxConsecutive + (t.length - matched) };
    };

    const filteredTags = computed(() => {
        if (!newTagInput.value) return availableTags.value;
        const q = newTagInput.value.trim();
        if (!q) return availableTags.value;

        const results = [];
        for (const tag of availableTags.value) {
            const nameMatch = fuzzyMatch(tag.name, q);
            const displayMatch = fuzzyMatch(tag.display_name, q);
            const best = nameMatch.score <= displayMatch.score ? nameMatch : displayMatch;
            if (best.match) {
                results.push({ tag, score: best.score });
            }
        }
        results.sort((a, b) => a.score - b.score);
        return results.map(r => r.tag);
    });

    const addTag = async (tagName) => {
        if (!page.props.auth.user) {
            alert('Please login to add tags');
            return;
        }

        if (addingTag.value) return;

        try {
            addingTag.value = true;
            const response = await axios.post(`/api/maps/${props.map.id}/tags`, {
                tag_name: tagName
            });
            tags.value.push(response.data.tag);

            // Also add parent tag if auto-added by backend
            if (response.data.parent_tag_added) {
                const parentTag = response.data.parent_tag_added;
                if (!tags.value.some(t => t.id === parentTag.id)) {
                    tags.value.push(parentTag);
                }
            }

            tags.value.sort((a, b) => a.display_name.localeCompare(b.display_name));
            newTagInput.value = '';
            showTagSuggestions.value = false;
        } catch (error) {
            if (error.response?.data?.error === 'blocked_keyword' && error.response?.data?.suggested_tag) {
                blockedSuggestedTag.value = error.response.data.suggested_tag;
                showBlockedTagModal.value = true;
            } else if (error.response?.data?.error) {
                alert(error.response.data.error);
            } else {
                alert('Failed to add tag');
            }
        } finally {
            addingTag.value = false;
        }
    };

    // Blocked keyword modal
    const showBlockedTagModal = ref(false);
    const blockedSuggestedTag = ref(null);
    const acceptSuggestedTag = () => {
        if (blockedSuggestedTag.value) {
            showBlockedTagModal.value = false;
            addTag(blockedSuggestedTag.value.display_name);
        }
    };

    // Tag removal confirmation
    const tagToRemove = ref(null);
    const tagRemoveChildren = ref([]);
    const showTagRemoveConfirm = ref(false);

    const confirmRemoveTag = (tag) => {
        tagToRemove.value = tag;
        // If this is a parent tag, find its children that are currently on this map
        const children = tag.children || [];
        tagRemoveChildren.value = children.filter(child => tags.value.some(t => t.id === child.id));
        showTagRemoveConfirm.value = true;
    };

    const executeRemoveTag = async () => {
        if (!tagToRemove.value) return;
        const tagId = tagToRemove.value.id;

        try {
            const response = await axios.delete(`/api/maps/${props.map.id}/tags/${tagId}`);

            // Remove the tag and any child tags that were also removed
            const removedIds = [tagId, ...(response.data.removed_child_ids || [])];
            tags.value = tags.value.filter(tag => !removedIds.includes(tag.id));

            // Update suggested tags
            for (const id of removedIds) {
                const suggestedIndex = suggestedTags.value.findIndex(t => t.id === id);
                if (suggestedIndex !== -1) {
                    suggestedTags.value[suggestedIndex].already_adopted = false;
                }
            }
        } catch (error) {
            console.error('Error removing tag:', error);
            alert(error.response?.data?.error || 'Failed to remove tag');
        } finally {
            showTagRemoveConfirm.value = false;
            tagToRemove.value = null;
            tagRemoveChildren.value = [];
        }
    };

    const adoptTag = async (tagId, tagDisplayName) => {
        if (!page.props.auth.user) {
            alert('Please login to adopt tags');
            return;
        }

        if (adoptingTag.value) return;

        try {
            adoptingTag.value = true;
            const response = await axios.post(`/api/maps/${props.map.id}/tags`, {
                tag_name: tagDisplayName
            });
            tags.value.push(response.data.tag);
            tags.value.sort((a, b) => a.display_name.localeCompare(b.display_name));

            // Update suggested tags to mark as adopted
            const suggestedIndex = suggestedTags.value.findIndex(t => t.id === tagId);
            if (suggestedIndex !== -1) {
                suggestedTags.value[suggestedIndex].already_adopted = true;
            }
        } catch (error) {
            if (error.response?.data?.error) {
                alert(error.response.data.error);
            } else {
                alert('Failed to adopt tag');
            }
        } finally {
            adoptingTag.value = false;
        }
    };

    const handleTagInput = () => {
        showTagSuggestions.value = newTagInput.value.length > 0;
    };

    const addCustomTag = () => {
        if (newTagInput.value.trim()) {
            addTag(newTagInput.value.trim());
        }
    };

    const tagBarInput = ref(null);
    let tagBarCollapseTimeout = null;

    const handleTagInputBlur = () => {
        setTimeout(() => {
            showAllTags.value = false;
        }, 100);
    };

    const onTagBarEnter = () => {
        if (tagBarCollapseTimeout) {
            clearTimeout(tagBarCollapseTimeout);
            tagBarCollapseTimeout = null;
        }
    };

    const onTagBarLeave = () => {
        tagBarCollapseTimeout = setTimeout(() => {
            if (document.activeElement === tagBarInput.value) return;
            showAllTags.value = false;
        }, 100);
    };

    const focusTagInput = () => {
        tagBarInput.value?.focus();
    };

    // Given a leaderboard row, pick the best demo id to use as seed for the
    // time-history drawer (offline/inline demos have `record.demo`, main MDD
    // records have `record.uploaded_demos[0]`). Looks up server-computed
    // cluster metadata (count + signals) so the collapsed badge numbers match
    // the drawer contents exactly. Returns null when the row has no demo or
    // the cluster has no history beyond the seed itself.
    const timeHistorySeed = (record, physics) => {
        const meta = physics === 'vq3' ? props.clusterMetaVq3 : props.clusterMetaCpm;
        if (!meta) return null;
        const demoId = record.demo?.id || record.uploaded_demos?.[0]?.id;
        if (demoId) {
            const m = meta[String(demoId)];
            if (m && m.count) return { demoId, userId: null, mddId: null, count: m.count, signals: m.signals || 0 };
        }
        // Fallback: main record with no attached demo. Prefer user_id
        // (registered defrag.racing account) then mdd_id (unclaimed q3df
        // profile) so the drawer still works for players who haven't
        // linked their q3df account to a local user yet.
        const userId = record.user_id || record.user?.id;
        if (userId) {
            const m = meta['user:' + userId];
            if (m && m.count) {
                return { demoId: null, userId, mddId: null, count: m.count, signals: m.signals || 0 };
            }
        }
        const mddId = record.mdd_id;
        if (mddId) {
            const m = meta['mdd:' + mddId];
            if (m && m.count) {
                return { demoId: null, userId: null, mddId, count: m.count, signals: m.signals || 0 };
            }
        }
        return null;
    };

    // Unique-per-row key for tracking which time-history drawer is open.
    // A row can be either an offline/online demo (has `demo.id`) or a main
    // record (no attached demo — fall back to record.id). Physics-scoped so
    // the same user's VQ3 and CPM rows don't share state.
    const timeHistoryRowKey = (record, physics) => {
        const id = record.demo?.id || record.uploaded_demos?.[0]?.id || record.id;
        return physics + ':' + id;
    };

    const expandedHistoryKeys = ref(new Set());
    const isHistoryExpanded = (record, physics) => expandedHistoryKeys.value.has(timeHistoryRowKey(record, physics));
    const toggleHistory = (record, physics) => {
        const key = timeHistoryRowKey(record, physics);
        const next = new Set(expandedHistoryKeys.value);
        if (next.has(key)) next.delete(key); else next.add(key);
        expandedHistoryKeys.value = next;
    };

    // Group items sharing any of (player_name / q3df_colored / q3df_plain).
    // Keeps only the fastest time per virtual player; the rest are surfaced
    // via the TimeHistoryExpand drawer.
    //
    // Items that participate in grouping:
    //   1. Offline records / inline assigned demos — signals come from `demo`
    //   2. Main MDD online records — signals come from `uploaded_demos[0]`
    //      (if attached) + fallback on record.name / record.user.name
    // This means a main q3df record and its owner's slower offline uploads
    // collapse into a single row (with the MDD record as representative if
    // it's the fastest, which it usually is).
    const groupByVirtualPlayer = (items) => {
        const n = items.length;
        const parent = Array.from({ length: n }, (_, i) => i);
        const find = (i) => {
            while (parent[i] !== i) { parent[i] = parent[parent[i]]; i = parent[i]; }
            return i;
        };
        const union = (a, b) => {
            const ra = find(a), rb = find(b);
            if (ra !== rb) parent[ra] = rb;
        };

        const stripColors = (s) => (s || '').replace(/\^[0-9\[\]]/g, '').trim().toLowerCase() || null;

        const signalsFor = (item) => {
            const d = item.demo || item.uploaded_demos?.[0];
            if (d) {
                return {
                    name: stripColors(d.player_name || item.name),
                    colored: ((d.q3df_login_name_colored || '').trim()) || null,
                    plain: ((d.q3df_login_name || '').trim().toLowerCase()) || null,
                };
            }
            // Main MDD record without attached demo — fall back to the record's
            // own player name so it can still cluster with that player's demos.
            const fallbackName = stripColors(item.name || item.player_name || item.user?.name);
            if (fallbackName) {
                return { name: fallbackName, colored: null, plain: null };
            }
            return null;
        };

        const nameIdx = new Map();
        const coloredIdx = new Map();
        const plainIdx = new Map();

        for (let i = 0; i < n; i++) {
            const sig = signalsFor(items[i]);
            if (!sig) continue;

            if (sig.name) {
                if (nameIdx.has(sig.name)) union(nameIdx.get(sig.name), i);
                else nameIdx.set(sig.name, i);
            }
            if (sig.colored) {
                if (coloredIdx.has(sig.colored)) union(coloredIdx.get(sig.colored), i);
                else coloredIdx.set(sig.colored, i);
            }
            if (sig.plain) {
                if (plainIdx.has(sig.plain)) union(plainIdx.get(sig.plain), i);
                else plainIdx.set(sig.plain, i);
            }
        }

        const counts = new Map();
        for (let i = 0; i < n; i++) {
            if (!signalsFor(items[i])) continue;
            const root = find(i);
            counts.set(root, (counts.get(root) || 0) + 1);
        }

        // Precompute max signal strength (0..3) for each cluster — so the
        // time-history badge can show 1/3, 2/3, 3/3 before the drawer is
        // even opened. We compare each non-representative member back to
        // its cluster root.
        const rootSeed = new Map(); // root idx -> representative item index (first seen = fastest)
        for (let i = 0; i < n; i++) {
            if (!signalsFor(items[i])) continue;
            const root = find(i);
            if (!rootSeed.has(root)) rootSeed.set(root, i);
        }
        const maxSignals = new Map();
        const matchCount = (a, b) => {
            let c = 0;
            if (a.name && b.name && a.name === b.name) c++;
            if (a.colored && b.colored && a.colored === b.colored) c++;
            if (a.plain && b.plain && a.plain === b.plain) c++;
            return c;
        };
        for (let i = 0; i < n; i++) {
            const sig = signalsFor(items[i]);
            if (!sig) continue;
            const root = find(i);
            if (i === rootSeed.get(root)) continue;
            const seedSig = signalsFor(items[rootSeed.get(root)]);
            if (!seedSig) continue;
            const c = matchCount(sig, seedSig);
            if (c > (maxSignals.get(root) || 0)) maxSignals.set(root, c);
        }

        // Items are already sorted by time asc before this call, so the first
        // index we see per root is the fastest attempt — that becomes the
        // representative row for the virtual player.
        const kept = new Set();
        const result = [];
        for (let i = 0; i < n; i++) {
            if (!signalsFor(items[i])) {
                result.push(items[i]);
                continue;
            }
            const root = find(i);
            if (!kept.has(root)) {
                kept.add(root);
                result.push({
                    ...items[i],
                    grouped_count: counts.get(root),
                    grouped_signals: maxSignals.get(root) || 0,
                });
            }
        }
        return result;
    };

    const mergeRecordSources = (onlineRecords, oldRecords, offlineRecords) => {
        let combined = [...onlineRecords.data];

        // Merge oldtop records
        if (props.showOldtop && oldRecords) {
            let oldtop_data = oldRecords.data.map((item) => {
                item['oldtop'] = true;
                return item;
            });
            combined = [...combined, ...oldtop_data];
        }

        // Merge offline/demo records
        if (props.showOffline && offlineRecords) {
            combined = [...combined, ...offlineRecords.data];
        }

        // Sort by time first, then collapse duplicates of the same virtual player.
        combined.sort((a, b) => (a.time || a.time_ms) - (b.time || b.time_ms));
        combined = groupByVirtualPlayer(combined);

        // Recalculate ranks (skip flagged items)
        let rankCounter = 0;
        combined = combined.map((item) => {
            const hasFlag = (item.approved_flags && item.approved_flags.length > 0) ||
                (item.verification_type && !['OFFLINE', 'ONLINE', 'verified'].includes(item.verification_type));
            if (hasFlag) {
                return { ...item, rank: null };
            }
            rankCounter++;
            return { ...item, rank: rankCounter };
        });

        // The offline paginator is already grouped server-side (one rep per
        // virtual player), so its last_page is correct. Main records and
        // oldtop have no collisions worth worrying about. Use max of the
        // three server paginators — that's the real last page.
        const perPage = onlineRecords.per_page || 20;
        const lastPage = Math.max(
            onlineRecords.last_page || 1,
            (props.showOldtop && oldRecords) ? (oldRecords.last_page || 1) : 1,
            (props.showOffline && offlineRecords) ? (offlineRecords.last_page || 1) : 1,
        );
        const total = Math.max(
            onlineRecords.total || 0,
            (props.showOldtop && oldRecords) ? (oldRecords.total || 0) : 0,
            (props.showOffline && offlineRecords) ? (offlineRecords.total || 0) : 0,
        );

        return {
            total,
            data: combined,
            first_page_url: onlineRecords.first_page_url,
            current_page: onlineRecords.current_page,
            last_page: lastPage,
            per_page: perPage
        };
    };

    const getVq3Records = computed(() => {
        if (props.showOldtop || props.showOffline) {
            return mergeRecordSources(props.vq3Records, props.vq3OldRecords, props.vq3OfflineRecords);
        }
        return props.vq3Records;
    })

    const getCpmRecords = computed(() => {
        if (props.showOldtop || props.showOffline) {
            return mergeRecordSources(props.cpmRecords, props.cpmOldRecords, props.cpmOfflineRecords);
        }
        return props.cpmRecords;
    })

    // Helper functions for weapon/item/function icons and names
    const getWeaponIcon = (abbr) => {
        const icons = {
            'gauntlet': '/images/weapons/iconw_gauntlet.svg',
            'gt': '/images/weapons/iconw_gauntlet.svg',
            'mg': '/images/weapons/iconw_machinegun.svg',
            'sg': '/images/weapons/iconw_shotgun.svg',
            'gl': '/images/weapons/iconw_grenade.svg',
            'rl': '/images/weapons/iconw_rocket.svg',
            'lg': '/images/weapons/iconw_lightning.svg',
            'rg': '/images/weapons/iconw_railgun.svg',
            'pg': '/images/weapons/iconw_plasma.svg',
            'bfg': '/images/weapons/iconw_bfg.svg',
            'grapple': '/images/weapons/iconw_grapple.svg',
            'hook': '/images/weapons/iconw_grapple.svg',
            'gh': '/images/weapons/iconw_grapple.svg'
        };
        return icons[abbr.toLowerCase().trim()] || '/images/weapons/iconw_gauntlet.svg';
    };

    const getWeaponName = (abbr) => {
        const weapons = {
            'gauntlet': 'Gauntlet',
            'gt': 'Gauntlet',
            'mg': 'Machine Gun',
            'sg': 'Shotgun',
            'gl': 'Grenade Launcher',
            'rl': 'Rocket Launcher',
            'lg': 'Lightning Gun',
            'rg': 'Rail Gun',
            'pg': 'Plasma Gun',
            'bfg': 'BFG',
            'grapple': 'Grappling Hook',
            'hook': 'Grappling Hook',
            'gh': 'Grappling Hook'
        };
        return weapons[abbr.toLowerCase().trim()] || abbr.toUpperCase();
    };

    const getItemIcon = (abbr) => {
        const icons = {
            // Powerups
            'enviro': '/images/powerups/envirosuit.svg',
            'haste': '/images/powerups/haste.svg',
            'quad': '/images/powerups/quad.svg',
            'regen': '/images/powerups/regen.svg',
            'invis': '/images/powerups/invis.svg',
            'flight': '/images/powerups/flight.svg',
            // Health
            'health': '/images/items/iconh_yellow.svg',
            'smallhealth': '/images/items/iconh_green.svg',
            'bighealth': '/images/items/iconh_red.svg',
            'mega': '/images/items/iconh_mega.svg',
            'medkit': '/images/items/medkit.svg',
            // Armor
            'shard': '/images/items/iconr_shard.svg',
            'ya': '/images/items/iconr_yellow.svg',
            'ra': '/images/items/iconr_red.svg',
            // CTF
            'flag': '/images/items/iconf_blu2.svg'
        };
        return icons[abbr.toLowerCase().trim()] || '/images/items/iconh_yellow.svg';
    };

    const getItemName = (abbr) => {
        const items = {
            // Powerups
            'enviro': 'Battle Suit',
            'haste': 'Haste',
            'quad': 'Quad Damage',
            'regen': 'Regeneration',
            'invis': 'Invisibility',
            'flight': 'Flight',
            // Health
            'health': 'Health (+25)',
            'smallhealth': 'Small Health (+5)',
            'bighealth': 'Large Health (+50)',
            'mega': 'Mega Health (+100)',
            'medkit': 'Medkit',
            // Armor
            'shard': 'Armor Shard (+5)',
            'ya': 'Yellow Armor (+50)',
            'ra': 'Red Armor (+100)',
            // CTF
            'flag': 'Flag'
        };
        return items[abbr.toLowerCase().trim()] || abbr;
    };

    const getFunctionIcon = (abbr) => {
        const icons = {
            'tele': '/images/functions/tele.svg',
            'teleporter': '/images/functions/teleporter.svg',
            'slick': '/images/functions/slick.svg',
            'timer': '/images/functions/timer.svg',
            'fog': '/images/functions/fog.svg',
            'water': '/images/functions/water.svg',
            'lava': '/images/functions/lava.svg',
            'moving': '/images/functions/moving.svg',
            'door': '/images/functions/door.svg',
            'button': '/images/functions/button.svg',
            'push': '/images/functions/push.svg',
            'jumppad': '/images/functions/push.svg',
            'launchramp': '/images/functions/push.svg',
            'break': '/images/functions/break.svg',
            'slime': '/images/functions/slime.svg',
            'shootergl': '/images/functions/shootergl.svg',
            'shooterpg': '/images/functions/shooterpg.svg',
            'shooterrl': '/images/functions/shooterrl.svg'
        };
        return icons[abbr.toLowerCase().trim()] || '/images/functions/timer.svg';
    };

    const getFunctionName = (abbr) => {
        const functions = {
            'tele': 'Teleporter',
            'teleporter': 'Teleporter',
            'slick': 'Slick Surface',
            'timer': 'Timer',
            'fog': 'Fog',
            'water': 'Water',
            'lava': 'Lava',
            'moving': 'Moving Platforms',
            'door': 'Doors',
            'button': 'Buttons',
            'push': 'Push Trigger',
            'jumppad': 'Jumppad',
            'launchramp': 'Launch Ramp',
            'break': 'Breakable',
            'slime': 'Slime',
            'shootergl': 'Grenade Shooter',
            'shooterpg': 'Plasma Shooter',
            'shooterrl': 'Rocket Shooter'
        };
        return functions[abbr.toLowerCase().trim()] || abbr;
    };

    const extractYoutubeId = (url) => {
        if (!url) return '';
        const match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
        return match ? match[1] : '';
    };

    const formatTime = (milliseconds) => {
        const minutes = Math.floor(milliseconds / 60000);
        const seconds = Math.floor((milliseconds % 60000) / 1000);
        const ms = milliseconds % 1000;

        if (minutes > 0) {
            return `${minutes}:${seconds.toString().padStart(2, '0')}:${ms.toString().padStart(3, '0')}`;
        }
        return `${seconds}:${ms.toString().padStart(3, '0')}`;
    };

    onMounted(() => {
        window.addEventListener("resize", resizeScreen);

        // Initialize toggle state - always trust the server/URL state
        showOldtopLocal.value = props.showOldtop;

        // Sync localStorage with the current state from URL/server
        localStorage.setItem('mapview_show_oldtop', props.showOldtop ? '1' : '0');
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
    });
</script>

<template>
    <div>
        <Head :title="map.name" />

        <!-- NSFW Age Gate Modal -->
        <div v-if="needsNsfwGate" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90">
            <div class="max-w-md mx-auto p-8 bg-gray-900/90 border border-red-500/30 rounded-2xl text-center shadow-2xl">
                <div class="text-5xl mb-4">🔞</div>
                <h2 class="text-2xl font-black text-white mb-3">Age-Restricted Content</h2>
                <p class="text-gray-400 mb-6">This map contains NSFW content. You must confirm that you are at least 18 years old to view it.</p>
                <div class="flex gap-3 justify-center">
                    <Link href="/maps" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-xl transition-colors">
                        Go Back
                    </Link>
                    <button v-if="$page.props.auth.user" @click="confirmNsfw" :disabled="confirmingNsfw" class="px-6 py-3 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white font-bold rounded-xl transition-colors">
                        {{ confirmingNsfw ? 'Confirming...' : 'I am 18+' }}
                    </button>
                    <Link v-else :href="route('login')" class="px-6 py-3 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl transition-colors">
                        Login to continue
                    </Link>
                </div>
            </div>
        </div>

        <!-- Cleaner page with card background -->
        <div class="relative pb-10">
            <!-- Fade shadow at top -->
            <div class="absolute top-0 left-0 right-0 bg-gradient-to-b from-black/25 via-black/10 to-transparent pointer-events-none" style="height: 400px; z-index: 0;"></div>

            <!-- Hero Content (compact) -->
            <div class="relative max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pt-10 pb-6" style="z-index: 10;">
                <div class="w-full max-w-4xl mx-auto rounded-2xl px-6 pt-6 pb-3 shadow-2xl relative border border-white/10 group">
                    <!-- Map thumbnail as card background -->
                    <div v-if="map.thumbnail" class="absolute inset-0 bg-cover bg-center rounded-2xl overflow-hidden" :style="`background-image: url('/storage/${map.thumbnail}');`">
                        <!-- Dark overlay for readability, lightens on hover -->
                        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/85 via-gray-900/75 to-gray-900/85 transition-opacity duration-300 group-hover:opacity-60"></div>
                    </div>
                    <!-- Fallback solid background if no thumbnail -->
                    <div v-else class="absolute inset-0 bg-gradient-to-br from-gray-800 to-gray-900"></div>

                    <!-- Content layer (on top of background) -->
                    <div class="relative z-10">
                    <!-- Map Title row: name + copy + badges -->
                    <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 mb-1">
                        <h1 class="text-4xl md:text-5xl font-bold text-white">
                            {{ map.name }}
                        </h1>
                        <CopyButton :text="map.name" size="md" />
                        <div v-if="localIsNsfw" class="group/badge relative">
                            <span class="map-badge bg-red-600/80 border-red-500/50 cursor-help">NSFW</span>
                            <div class="absolute left-1/2 -translate-x-1/2 top-full mt-1 hidden group-hover/badge:block bg-gray-900/95 border border-white/20 rounded-lg px-3 py-2 text-xs text-gray-200 whitespace-nowrap shadow-xl z-50">
                                <div class="font-bold text-white mb-1">Not Safe For Work</div>
                                <div class="text-gray-400">This map contains adult or explicit content</div>
                            </div>
                        </div>
                        <!-- Both ranked -->
                        <div v-if="map.is_ranked_vq3 && map.is_ranked_cpm" class="group/badge relative" style="order: -1">
                            <span class="map-badge bg-green-600/80 border-green-500/30 shadow-lg shadow-green-500/20 cursor-help">Ranked</span>
                            <div class="absolute left-1/2 -translate-x-1/2 top-full mt-1 hidden group-hover/badge:block bg-gray-900/95 border border-white/20 rounded-lg px-3 py-2 text-xs text-gray-200 whitespace-nowrap shadow-xl z-50">
                                <div class="font-bold text-white mb-1">Ranked in VQ3 & CPM</div>
                                <div class="text-gray-400">Records on this map count towards</div>
                                <div class="text-gray-400">player rankings in both physics modes.</div>
                                <div class="text-gray-500 mt-1 text-[10px]">Criteria: 5+ unique players, best time over 500ms</div>
                            </div>
                        </div>
                        <!-- Only one ranked -->
                        <div v-else-if="map.is_ranked_vq3 || map.is_ranked_cpm" class="group/badge relative" style="order: -1">
                            <span class="map-badge bg-green-600/80 border-green-500/30 shadow-lg shadow-green-500/20 cursor-help">Ranked {{ map.is_ranked_vq3 ? 'VQ3' : 'CPM' }}</span>
                            <div class="absolute left-1/2 -translate-x-1/2 top-full mt-1 hidden group-hover/badge:block bg-gray-900/95 border border-white/20 rounded-lg px-3 py-2 text-xs text-gray-200 whitespace-nowrap shadow-xl z-50">
                                <div class="font-bold text-white mb-1">Ranked in {{ map.is_ranked_vq3 ? 'VQ3' : 'CPM' }} only</div>
                                <div class="text-gray-400">Records count towards player rankings</div>
                                <div class="text-gray-400">in {{ map.is_ranked_vq3 ? 'VQ3' : 'CPM' }} mode. {{ map.is_ranked_vq3 ? 'CPM' : 'VQ3' }} is unranked on this map.</div>
                                <div class="text-gray-500 mt-1 text-[10px]">Criteria: 5+ unique players, best time over 500ms</div>
                            </div>
                        </div>
                        <!-- Both unranked -->
                        <div v-else class="group/badge relative" style="order: -1">
                            <span class="map-badge bg-gray-600/80 border-gray-500/30 shadow-lg shadow-gray-500/10 text-gray-300 cursor-help">Unranked</span>
                            <div class="absolute left-1/2 -translate-x-1/2 top-full mt-1 hidden group-hover/badge:block bg-gray-900/95 border border-white/20 rounded-lg px-3 py-2 text-xs text-gray-200 whitespace-nowrap shadow-xl z-50">
                                <div class="font-bold text-white mb-1">Not included in rankings</div>
                                <div class="text-gray-400">Records on this map do not affect</div>
                                <div class="text-gray-400">player ranking positions.</div>
                                <div class="text-gray-500 mt-1 text-[10px]">Needs 5+ unique players and best time over 500ms to qualify</div>
                            </div>
                        </div>
                    </div>
                    <!-- Author + Date row -->
                    <div class="flex items-center justify-center gap-3 mb-2">
                        <Link v-if="map.author" :href="route('maps.filters', {author: map.author})" class="text-blue-400 hover:text-blue-300 font-semibold underline decoration-blue-400/50 hover:decoration-blue-300 transition-colors text-sm">{{ map.author }}</Link>
                        <span v-if="map.date_added" class="text-gray-200 group-hover:text-white text-sm transition-colors">{{ new Date(map.date_added).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                    </div>

                    <!-- Map Features: Weapons, Items, Functions -->
                    <div v-if="(map.weapons && map.weapons.length > 0) || (map.items && map.items.length > 0) || (map.functions && map.functions.length > 0)" class="flex flex-wrap items-center justify-center gap-2 mb-3">
                        <div v-if="map.weapons && map.weapons.length > 0" class="flex items-center gap-1">
                            <span class="text-gray-200 group-hover:text-white font-bold text-[10px] uppercase tracking-wide transition-colors">Weapons</span>
                            <div class="flex gap-0.5">
                                <img v-for="weapon in map.weapons.split(',')" :key="weapon"
                                     :src="getWeaponIcon(weapon)"
                                     :alt="getWeaponName(weapon)"
                                     :title="getWeaponName(weapon)"
                                     class="w-5 h-5 opacity-90 hover:opacity-100 transition-opacity" />
                            </div>
                        </div>
                        <div v-if="map.items && map.items.length > 0" class="flex items-center gap-1">
                            <span class="text-gray-200 group-hover:text-white font-bold text-[10px] uppercase tracking-wide transition-colors">Items</span>
                            <div class="flex gap-0.5">
                                <img v-for="item in map.items.split(',')" :key="item"
                                     :src="getItemIcon(item)"
                                     :alt="getItemName(item)"
                                     :title="getItemName(item)"
                                     class="w-5 h-5 opacity-90 hover:opacity-100 transition-opacity" />
                            </div>
                        </div>
                        <div v-if="map.functions && map.functions.length > 0" class="flex items-center gap-1">
                            <span class="text-gray-200 group-hover:text-white font-bold text-[10px] uppercase tracking-wide transition-colors">Functions</span>
                            <div class="flex gap-0.5">
                                <img v-for="func in map.functions.split(',')" :key="func"
                                     :src="getFunctionIcon(func)"
                                     :alt="getFunctionName(func)"
                                     :title="getFunctionName(func)"
                                     class="w-5 h-5 opacity-90 hover:opacity-100 transition-opacity" />
                            </div>
                        </div>
                    </div>

                    <!-- Download & Servers -->
                    <div class="flex flex-wrap gap-2 justify-center mb-4">
                        <!-- Download Button -->
                        <a
                            :href="`/maps/download/${map.name}`"
                            class="flex items-center gap-1.5 bg-gray-600/80 hover:bg-gray-600 text-white font-medium px-3 py-1.5 rounded-md transition-all text-xs hover:shadow-md"
                        >
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/>
                            </svg>
                            Download Map
                        </a>

                        <!-- Add to Maplist Button (only for authenticated users) -->
                        <button
                            v-if="$page.props.auth.user"
                            @click="showAddToMaplistModal = true"
                            class="flex items-center gap-1.5 bg-blue-600/80 hover:bg-blue-600 text-white font-medium px-3 py-1.5 rounded-md transition-all text-xs hover:shadow-md"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add to Maplist
                        </button>

                        <!-- Flag NSFW -->
                        <button
                            v-if="$page.props.auth.user && !localIsNsfw"
                            @click="flagAsNsfw"
                            :disabled="flaggingNsfw"
                            class="flex items-center gap-1.5 bg-red-600/60 hover:bg-red-600 text-white font-medium px-3 py-1.5 rounded-md transition-all text-xs hover:shadow-md disabled:opacity-50"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5" />
                            </svg>
                            Flag NSFW
                        </button>

                        <!-- Unflag NSFW -->
                        <button
                            v-if="$page.props.auth.user && localIsNsfw"
                            @click="unflagNsfw"
                            :disabled="flaggingNsfw"
                            class="flex items-center gap-1.5 bg-green-600/60 hover:bg-green-600 text-white font-medium px-3 py-1.5 rounded-md transition-all text-xs hover:shadow-md disabled:opacity-50"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5" />
                            </svg>
                            Remove NSFW Flag
                        </button>

                        <!-- Active Servers -->
                        <div v-if="servers && servers.length > 0" class="flex flex-wrap gap-2">
                            <a
                                v-for="server in servers"
                                :key="server.id"
                                v-show="server.online_players && server.online_players.length > 0"
                                :href="`defrag://${server.ip}:${server.port}`"
                                class="flex items-center gap-1.5 bg-orange-500/80 hover:bg-orange-500 text-white font-medium px-3 py-1.5 rounded-md transition-all text-xs hover:shadow-md"
                                :title="`Connect to ${server.plain_name || server.name}`"
                            >
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/>
                                </svg>
                                <span class="whitespace-nowrap">Join</span>
                                <span class="truncate max-w-[100px]">{{ server.plain_name || server.name }}</span>
                                <span class="bg-white/20 px-1.5 py-0.5 rounded text-[10px] whitespace-nowrap">{{ server.online_players.length }} playing</span>
                            </a>
                        </div>
                    </div>

                    <!-- Tags Section -->
                    <div class="mb-4 pt-3 border-t border-white/10 relative">
                        <!-- Existing tags row -->
                        <div class="flex flex-wrap items-center gap-1.5 mb-2 min-h-[24px]">
                            <span
                                v-for="tag in tags"
                                :key="tag.id"
                                class="group/tag relative flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-600 text-white ring-1 ring-blue-400"
                            >
                                {{ tag.display_name }}
                                <button
                                    v-if="$page.props.auth.user && !$page.props.auth.user.tag_banned"
                                    @click="confirmRemoveTag(tag)"
                                    class="opacity-0 group-hover/tag:opacity-100 hover:text-red-300 transition-opacity"
                                    title="Remove tag"
                                >
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div v-if="tag.note" class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 px-2.5 py-1 rounded-lg bg-black/90 border border-white/10 text-[11px] text-gray-300 whitespace-nowrap opacity-0 group-hover/tag:opacity-100 transition pointer-events-none shadow-xl z-50">
                                    {{ tag.note }}
                                </div>
                            </span>
                            <!-- Tag Info -->
                            <span
                                v-if="tags.length > 0"
                                ref="tagInfoBtn"
                                @mouseenter="showTagInfo = true"
                                @mouseleave="showTagInfo = false"
                                class="flex items-center gap-1 ml-auto px-2.5 py-1 rounded-lg bg-white/10 border border-white/20 text-gray-300 hover:text-white hover:bg-white/20 hover:border-white/30 cursor-help transition-all text-[11px] font-semibold shadow-sm"
                                style="order: 999"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" /></svg>
                                Tag Info
                            </span>
                            <Teleport to="body">
                                <div
                                    v-if="showTagInfo && tags.length > 0"
                                    :style="tagInfoStyle"
                                    @mouseenter="showTagInfo = true"
                                    @mouseleave="showTagInfo = false"
                                    class="fixed w-96 max-h-[70vh] overflow-y-auto rounded-lg border border-white/20 shadow-2xl p-3"
                                    style="background: #0f1219; z-index: 9999;"
                                >
                                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Tag Info</div>
                                    <div class="space-y-2.5">
                                        <div v-for="t in tags" :key="'legend-' + t.id">
                                            <div class="flex items-center gap-2">
                                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-600 text-white whitespace-nowrap flex-shrink-0 leading-none">{{ t.display_name }}</span>
                                                <span class="text-[11px] text-gray-400 leading-none">{{ t.note || 'No description yet' }}</span>
                                            </div>
                                            <div v-if="t.youtube_url" class="mt-1.5 rounded-lg overflow-hidden aspect-video">
                                                <iframe
                                                    :src="'https://www.youtube.com/embed/' + extractYoutubeId(t.youtube_url) + '?rel=0&modestbranding=1'"
                                                    class="w-full h-full"
                                                    frameborder="0"
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                    allowfullscreen
                                                ></iframe>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Teleport>

                            <!-- Suggested tags -->
                            <button
                                v-for="suggestedTag in suggestedTags.filter(s => !s.already_adopted)"
                                :key="'sug-' + suggestedTag.id"
                                v-if="$page.props.auth.user && !$page.props.auth.user.tag_banned"
                                @click="adoptTag(suggestedTag.id, suggestedTag.display_name)"
                                :disabled="adoptingTag"
                                class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-600/20 text-green-300 border border-green-500/30 hover:bg-green-600/30 transition-all"
                                :title="'Suggested from: ' + suggestedTag.maplist_names.join(', ')"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                {{ suggestedTag.display_name }}
                            </button>
                            <span v-if="tags.length === 0 && !$page.props.auth.user" class="text-gray-500 text-xs italic">No tags yet</span>
                            <span v-if="tags.length === 0 && $page.props.auth.user && !$page.props.auth.user.tag_banned" class="text-gray-500 text-xs italic">No tags yet - add below</span>
                            <span v-if="tags.length === 0 && $page.props.auth.user?.tag_banned" class="text-gray-500 text-xs italic">No tags yet</span>
                        </div>

                        <div v-if="$page.props.auth.user?.tag_banned" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-red-900/20 border border-red-500/20 text-red-400 text-xs">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            You have been banned from adding or removing tags.
                        </div>

                        <!-- Add tag bar (Maps page style with hover expand) -->
                        <div v-if="$page.props.auth.user && !$page.props.auth.user.tag_banned" class="relative" ref="tagInputContainer" @mouseenter="onTagBarEnter" @mouseleave="onTagBarLeave">
                            <div class="mapview-tags-bar" :class="{ 'mapview-tags-bar-focus': showAllTags }" @click="focusTagInput">
                                <div class="flex items-center gap-2 min-h-[28px]">
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                    </svg>
                                    <!-- Input with ghost tags behind -->
                                    <div class="flex-1 min-w-0 relative" @click="focusTagInput">
                                        <!-- Ghost tags as background (hidden when typing) -->
                                        <div v-show="!newTagInput" class="absolute inset-0 flex items-center gap-1.5 overflow-hidden pointer-events-none select-none flex-nowrap opacity-70 pl-[10px]" aria-hidden="true">
                                            <span class="text-sm text-gray-200 flex-shrink-0 mr-1">Click to browse & search tags...</span>
                                            <span
                                                v-for="tag in availableTags.filter(t => !tags.some(tt => tt.id === t.id)).slice(0, 15)"
                                                :key="'ghost-' + tag.id"
                                                class="px-2 py-0.5 rounded-full text-xs font-medium text-gray-300 bg-white/[0.08] flex-shrink-0 whitespace-nowrap">
                                                {{ tag.display_name }}
                                            </span>
                                        </div>
                                        <!-- Visible input full width -->
                                        <input
                                            ref="tagBarInput"
                                            v-model="newTagInput"
                                            @input="handleTagInput"
                                            @keyup.enter="addCustomTag"
                                            @focus="showAllTags = true"
                                            @blur="handleTagInputBlur"
                                            type="text"
                                            placeholder=""
                                            class="relative w-full bg-transparent border-none text-sm text-white focus:outline-none cursor-text"
                                        />
                                    </div>
                                    <button
                                        @mousedown.prevent="addCustomTag"
                                        :disabled="!newTagInput.trim() || addingTag"
                                        class="bg-blue-600 hover:bg-blue-500 disabled:bg-gray-700 disabled:cursor-not-allowed text-white px-3 py-1 rounded-md text-xs font-medium transition-colors flex-shrink-0"
                                    >
                                        {{ addingTag ? '...' : 'Add' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Expandable tag picker overlay (teleported to body) -->
                            <Teleport to="body">
                                <div
                                    v-if="showAllTags && availableTags.length > 0"
                                    :style="tagDropdownStyle"
                                    class="mapview-tags-overlay-teleported"
                                    @mouseenter="onTagBarEnter"
                                    @mouseleave="onTagBarLeave"
                                >
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="availableTag in filteredTags.filter(t => !tags.some(tag => tag.id === t.id))"
                                            :key="availableTag.id"
                                            @mousedown.prevent="addTag(availableTag.display_name)"
                                            class="px-2 py-0.5 rounded-full text-xs font-medium transition-all bg-white/10 text-gray-300 hover:bg-white/20 hover:text-white"
                                        >
                                            {{ availableTag.display_name }}
                                            <span class="opacity-40 ml-0.5">({{ availableTag.usage_count }})</span>
                                        </button>
                                    </div>
                                </div>
                            </Teleport>
                        </div>

                        <!-- Not logged in hint -->
                        <div v-if="!$page.props.auth.user && tags.length === 0" class="mt-2 text-center">
                            <div class="text-xs text-gray-500"><a href="/login" class="underline hover:text-white transition-colors">Login</a> or <a href="/register" class="underline hover:text-white transition-colors">register</a> to add tags</div>
                        </div>
                    </div>

                    <!-- Public Maplists featuring this map -->
                    <div v-if="publicMaplists && publicMaplists.length > 0" class="mb-3 pt-2 border-t border-white/10">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-xs text-gray-200 group-hover:text-white font-semibold mr-1 transition-colors">Maplists</span>
                            <Link
                                v-for="maplist in publicMaplists"
                                :key="maplist.id"
                                :href="`/maplists/${maplist.id}`"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-600/20 text-blue-200 border border-blue-500/30 hover:bg-blue-600/40 hover:text-white hover:border-blue-400/50 transition-all"
                            >
                                {{ maplist.name }}
                                <span class="text-[10px] text-blue-400">({{ maplist.maps_count || 0 }} maps)</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Difficulty Rating + Controls Row -->
                    <div class="flex flex-wrap items-center gap-3 mb-3 relative z-10">
                        <!-- Difficulty Rating -->
                        <div class="flex items-center gap-1.5">
                            <div class="relative group/diff">
                                <span class="text-xs text-gray-200 group-hover:text-white font-semibold mr-1 transition-colors cursor-help border-b border-dotted border-gray-600">Difficulty</span>
                                <div class="absolute bottom-full left-0 mb-1.5 w-64 p-2.5 bg-gray-900 border border-gray-700 rounded-lg text-[11px] text-gray-300 shadow-xl opacity-0 pointer-events-none group-hover/diff:opacity-100 group-hover/diff:pointer-events-auto transition-opacity z-50">
                                    <p class="text-yellow-400 font-bold mb-1">How to rate difficulty</p>
                                    <p>Rate based on the <strong class="text-white">lowest skill needed to complete</strong> the map, not the WR time. A beginner map is still beginner even if the WR is insanely optimized.</p>
                                </div>
                            </div>
                            <div v-for="d in difficultyLabels" :key="d.level" class="relative group/btn">
                                <button
                                    @click="rateDifficulty(d.level)"
                                    :disabled="!$page.props.auth?.user || submittingDifficulty"
                                    :class="[
                                        'px-2.5 py-1 rounded-lg text-xs font-bold border transition-all',
                                        difficultyData.user_rating === d.level
                                            ? d.color + ' text-white ' + d.borderColor + ' ring-2 ' + d.activeRing + ' shadow-lg'
                                            : communityDifficultyLevel === d.level && difficultyData.total > 0 && !$page.props.auth?.user
                                                ? d.color + ' text-white ' + d.borderColor + ' ring-2 ' + d.activeRing + ' shadow-lg'
                                                : communityDifficultyLevel === d.level && difficultyData.total > 0 && !difficultyData.user_rating && $page.props.auth?.user
                                                    ? d.color + '/60 text-white ' + d.borderColor + '/70'
                                                : communityDifficultyLevel === d.level && difficultyData.total > 0
                                                    ? d.color + '/40 text-white ' + d.borderColor + '/50'
                                                    : 'bg-white/5 text-gray-400 border-white/10 ' + ($page.props.auth?.user ? d.hoverColor + '/20 hover:text-white hover:border-white/30 cursor-pointer' : 'cursor-default'),
                                    ]"
                                >
                                    {{ d.label }}
                                </button>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 px-2.5 py-1.5 bg-gray-900 border border-gray-700 rounded-lg text-[10px] text-gray-300 shadow-xl opacity-0 pointer-events-none group-hover/btn:opacity-100 transition-opacity z-50 whitespace-nowrap">
                                    <div class="font-medium text-white">{{ d.desc }}</div>
                                    <div v-if="difficultyData.distribution[d.level]" class="text-gray-500 mt-0.5">{{ difficultyData.distribution[d.level] }} vote{{ difficultyData.distribution[d.level] > 1 ? 's' : '' }}</div>
                                </div>
                            </div>
                            <span v-if="difficultyData.total > 0" class="text-[10px] text-gray-500 group-hover:text-gray-300 ml-1 transition-colors">
                                {{ difficultyData.average }} avg
                                <span class="text-gray-600 group-hover:text-gray-400 transition-colors">({{ difficultyData.total }})</span>
                            </span>
                            <span v-else class="text-[10px] text-gray-600 group-hover:text-gray-400 ml-1 transition-colors">No votes yet</span>
                        </div>

                        <div class="flex-1"></div>

                        <!-- Toggle Buttons -->
                        <div class="flex flex-wrap gap-2 items-center text-sm">
                            <button
                                @click="sortByTime"
                                :class="column !== 'time' ? 'bg-blue-600 text-white border-blue-400 shadow-lg shadow-blue-500/30' : 'bg-gray-800 text-gray-200 border-gray-600 hover:bg-gray-700 hover:text-white'"
                                class="px-3.5 py-1.5 rounded-lg border text-xs font-bold transition-all"
                            >
                                {{ column === 'time' ? 'Fastest' : 'Newest' }}
                            </button>
                            <button
                                @click="onChangeOldtop(!showOldtopLocal)"
                                :class="showOldtopLocal ? 'bg-blue-600 text-white border-blue-400 shadow-lg shadow-blue-500/30' : 'bg-gray-800 text-gray-200 border-gray-600 hover:bg-gray-700 hover:text-white'"
                                class="px-3.5 py-1.5 rounded-lg border text-xs font-bold transition-all"
                            >
                                Old Top
                            </button>
                            <button
                                @click="onChangeOffline(!showOfflineLocal)"
                                :class="showOfflineLocal ? 'bg-blue-600 text-white border-blue-400 shadow-lg shadow-blue-500/30' : 'bg-gray-800 text-gray-200 border-gray-600 hover:bg-gray-700 hover:text-white'"
                                class="px-3.5 py-1.5 rounded-lg border text-xs font-bold transition-all"
                            >
                                Demos Top
                            </button>
                            <div class="text-xs text-gray-500">
                                <Link v-if="page.props.auth?.user" href="/user/settings?tab=customize" class="hover:text-teal-400 transition-colors underline decoration-dotted underline-offset-2">
                                    Set your defaults
                                </Link>
                                <span v-else class="cursor-not-allowed" title="You must login or register to customize defaults">
                                    <Link href="/login" class="hover:text-teal-400 transition-colors underline decoration-dotted underline-offset-2">Log in</Link> to save defaults
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Gametype Tabs (only show if more than one gametype has records) -->
                    <div v-if="Object.values(gametypeStats).filter(count => count > 0).length > 1" class="flex gap-2 flex-wrap justify-center mb-1">
                        <button
                            v-for="gt in gametypes"
                            :key="gt"
                            v-show="gametypeStats[gt] && gametypeStats[gt] > 0"
                            @click="sortByGametype(gt)"
                            :class="[
                                'px-4 py-2 rounded-lg font-bold text-sm transition-all',
                                gametype === gt
                                    ? 'bg-blue-500/80 text-white shadow-lg ring-2 ring-blue-400'
                                    : 'bg-white/10 text-gray-300 hover:bg-white/20'
                            ]"
                        >
                            <span class="uppercase">{{ gt }}</span>
                            <span class="ml-2 text-xs opacity-75">({{ gametypeStats[gt] }})</span>
                        </button>
                    </div>

</div> <!-- Close content layer -->
                </div>
            </div>

            <!-- Leaderboards Section (on top of background) -->
            <div class="relative max-w-8xl mx-auto px-4 md:px-6 lg:px-8 z-10">
                <!-- Mobile Physics Toggle (only on small screens) -->
                <div class="md:hidden flex gap-2 justify-center mb-4">
                    <button
                        @click="mobilePhysics = mobilePhysics === 'VQ3' ? 'both' : 'VQ3'"
                        :class="[
                            'px-4 py-2 rounded-lg font-bold text-sm transition-all flex items-center gap-2',
                            mobilePhysics === 'VQ3'
                                ? 'bg-blue-500/80 text-white shadow-lg ring-2 ring-blue-400'
                                : 'bg-white/10 text-gray-300'
                        ]"
                    >
                        <!-- <img src="/images/modes/vq3-icon.svg" class="w-5 h-5" alt="VQ3" /> -->
                        <span>VQ3</span>
                    </button>
                    <button
                        @click="mobilePhysics = mobilePhysics === 'CPM' ? 'both' : 'CPM'"
                        :class="[
                            'px-4 py-2 rounded-lg font-bold text-sm transition-all flex items-center gap-2',
                            mobilePhysics === 'CPM'
                                ? 'bg-purple-500/80 text-white shadow-lg ring-2 ring-purple-400'
                                : 'bg-white/10 text-gray-300'
                        ]"
                    >
                        <!-- <img src="/images/modes/cpm-icon.svg" class="w-5 h-5" alt="CPM" /> -->
                        <span>CPM</span>
                    </button>
                </div>

                <div class="lg:flex gap-4 justify-center">
                    <!-- VQ3 Leaderboard -->
                    <div v-show="mobilePhysics === 'both' || mobilePhysics === 'VQ3'" :style="{ order: cpmFirst ? 2 : 1 }" class="flex-1 bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-sm rounded-xl overflow-hidden shadow-xl border border-white/10 hover:border-white/20 transition-all duration-300">
                    <!-- VQ3 Header -->
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 pt-1 pb-1">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <!-- <img src="/images/modes/vq3-icon.svg" class="w-5 h-5" alt="VQ3" /> -->
                                <h2 class="text-lg font-bold text-blue-400">VQ3 Records <span v-if="getVq3Records.total" class="text-sm font-semibold text-blue-400/60">({{ getVq3Records.total }})</span></h2>
                                <Link v-if="page.props.auth?.user" href="/user/settings?tab=global-customize" class="text-xs text-gray-500 hover:text-blue-400 transition-colors underline decoration-dotted underline-offset-2">
                                    Swap VQ3/CPM sides
                                </Link>
                            </div>
                            <div v-if="page.props.auth?.user" class="text-right min-w-[80px]">
                                <div class="text-[11px] text-gray-300 font-bold">Your Best</div>
                                <div v-if="my_vq3_record" class="text-sm font-bold text-blue-300 tabular-nums">
                                    {{ formatTime(my_vq3_record.time) }}
                                    <span class="text-xs text-blue-400">#{{ my_vq3_record.rank }}</span>
                                </div>
                                <div v-else class="text-sm text-gray-500">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- VQ3 Records List -->
                    <div class="p-3">
                        <div v-if="getVq3Records.total > 0">
                            <!-- Column Headers -->
                            <div class="flex items-center gap-1.5 -ml-3 -mr-1 -mt-1 mb-0 pb-1 border-b border-white/15">
                                <div class="w-8 flex-shrink-0 text-center pl-0.5 text-[10px] text-gray-400 uppercase tracking-wider font-semibold">#</div>
                                <div class="flex-1 text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Player</div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right ml-2">Time</div>
                                <div class="w-10 sm:w-12 flex-shrink-0 text-center text-[10px] text-gray-400 uppercase tracking-wider font-semibold -ml-1">Score</div>
                                <div :class="[dateColWidth, 'flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right']">Date</div>
                            </div>
                            <div class="flex-grow">
                                <template v-for="record in getVq3Records.data" :key="record.is_online ? `online-${record.id}` : `offline-${record.id}`">
                                    <MapRecord
                                        physics="VQ3"
                                        :oldtop="record.oldtop"
                                        :showSourceChips="showOldtop || showOffline"
                                        :record="record"
                                        :demoMatches="demoMatchesMap[record.id] || []"
                                        :timeHistory="timeHistorySeed(record, 'vq3')"
                                        :historyExpanded="isHistoryExpanded(record, 'vq3')"
                                        @assign="openAssignModal($event, 'VQ3')"
                                        @assign-from-record="(rec) => openReverseAssignModal(rec, demoMatchesMap[rec.id] || [])"
                                        @reassign-record="(rec) => openReassignModal(rec)"
                                        @scoreHover="scoreTooltip = $event"
                                        @toggle-history="toggleHistory(record, 'vq3')"
                                    />
                                    <TimeHistoryExpand
                                        v-if="timeHistorySeed(record, 'vq3') && isHistoryExpanded(record, 'vq3')"
                                        :mapname="map.name"
                                        :demo-id="timeHistorySeed(record, 'vq3').demoId"
                                        :user-id="timeHistorySeed(record, 'vq3').userId"
                                        :mdd-id="timeHistorySeed(record, 'vq3').mddId"
                                        :attempts-count="timeHistorySeed(record, 'vq3').count"
                                        :signals-count="timeHistorySeed(record, 'vq3').signals"
                                        physics="vq3"
                                    />
                                </template>
                            </div>

                            <!-- <div class="flex-grow" v-else>
                                <MapRecordSmall v-for="record in getVq3Records.data" physics="VQ3" :oldtop="record.oldtop" :key="record.id" :record="record" />
                            </div> -->
                        </div>

                        <div v-else class="flex items-center justify-center mt-20 text-gray-500 text-lg">
                            <div>
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div>There are no VQ3 Records</div>
                            </div>
                        </div>
                    </div>

                    <!-- VQ3 Pagination -->
                    <div class="border-t border-white/5 bg-transparent p-3" v-if="getVq3Records.total > getVq3Records.per_page">
                        <Pagination pageName="vq3Page" :last_page="getVq3Records.last_page" :current_page="getVq3Records.current_page" :link="getVq3Records.first_page_url" :only="['vq3Records', 'vq3OldRecords', 'vq3OfflineRecords']" />
                    </div>
                </div>

                <!-- CPM Leaderboard -->
                <div v-show="mobilePhysics === 'both' || mobilePhysics === 'CPM'" :style="{ order: cpmFirst ? 1 : 2 }" class="flex-1 bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-sm rounded-xl overflow-hidden shadow-xl border border-white/10 hover:border-white/20 transition-all duration-300 mt-5 lg:mt-0">
                    <!-- CPM Header -->
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 pt-1 pb-1">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <!-- <img src="/images/modes/cpm-icon.svg" class="w-5 h-5" alt="CPM" /> -->
                                <h2 class="text-lg font-bold text-purple-400">CPM Records <span v-if="getCpmRecords.total" class="text-sm font-semibold text-purple-400/60">({{ getCpmRecords.total }})</span></h2>
                                <Link v-if="page.props.auth?.user" href="/user/settings?tab=global-customize" class="text-xs text-gray-500 hover:text-purple-400 transition-colors underline decoration-dotted underline-offset-2">
                                    Swap VQ3/CPM sides
                                </Link>
                            </div>
                            <div v-if="page.props.auth?.user" class="text-right min-w-[80px]">
                                <div class="text-[11px] text-gray-300 font-bold">Your Best</div>
                                <div v-if="my_cpm_record" class="text-sm font-bold text-purple-300 tabular-nums">
                                    {{ formatTime(my_cpm_record.time) }}
                                    <span class="text-xs text-purple-400">#{{ my_cpm_record.rank }}</span>
                                </div>
                                <div v-else class="text-sm text-gray-500">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- CPM Records List -->
                    <div class="p-3">
                        <div v-if="getCpmRecords.total > 0">
                            <!-- Column Headers -->
                            <div class="flex items-center gap-1.5 -ml-3 -mr-1 -mt-1 mb-0 pb-1 border-b border-white/15">
                                <div class="w-8 flex-shrink-0 text-center pl-0.5 text-[10px] text-gray-400 uppercase tracking-wider font-semibold">#</div>
                                <div class="flex-1 text-[10px] text-gray-400 uppercase tracking-wider font-semibold">Player</div>
                                <div class="text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right ml-2">Time</div>
                                <div class="w-10 sm:w-12 flex-shrink-0 text-center text-[10px] text-gray-400 uppercase tracking-wider font-semibold -ml-1">Score</div>
                                <div :class="[dateColWidth, 'flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right']">Date</div>
                            </div>
                            <div class="flex-grow">
                                <template v-for="record in getCpmRecords.data" :key="record.is_online ? `online-${record.id}` : `offline-${record.id}`">
                                    <MapRecord
                                        physics="CPM"
                                        :showSourceChips="showOldtop || showOffline"
                                        :record="record"
                                        :demoMatches="demoMatchesMap[record.id] || []"
                                        :timeHistory="timeHistorySeed(record, 'cpm')"
                                        :historyExpanded="isHistoryExpanded(record, 'cpm')"
                                        @assign="openAssignModal($event, 'CPM')"
                                        @assign-from-record="(rec) => openReverseAssignModal(rec, demoMatchesMap[rec.id] || [])"
                                        @reassign-record="(rec) => openReassignModal(rec)"
                                        @scoreHover="scoreTooltip = $event"
                                        @toggle-history="toggleHistory(record, 'cpm')"
                                    />
                                    <TimeHistoryExpand
                                        v-if="timeHistorySeed(record, 'cpm') && isHistoryExpanded(record, 'cpm')"
                                        :mapname="map.name"
                                        :demo-id="timeHistorySeed(record, 'cpm').demoId"
                                        :user-id="timeHistorySeed(record, 'cpm').userId"
                                        :mdd-id="timeHistorySeed(record, 'cpm').mddId"
                                        :attempts-count="timeHistorySeed(record, 'cpm').count"
                                        :signals-count="timeHistorySeed(record, 'cpm').signals"
                                        physics="cpm"
                                    />
                                </template>
                            </div>

                            <!-- <div class="flex-grow" v-else>
                                <MapRecordSmall v-for="record in getCpmRecords.data" physics="CPM" :oldtop="record.oldtop" :key="record.id" :record="record" />
                            </div> -->
                        </div>

                        <div v-else class="flex items-center justify-center mt-20 text-gray-500 text-lg">
                            <div>
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0 Z" />
                                    </svg>
                                </div>
                                <div>There are no CPM Records</div>
                            </div>
                        </div>
                    </div>

                    <!-- CPM Pagination -->
                    <div class="border-t border-white/5 bg-transparent p-3" v-if="getCpmRecords.total > getCpmRecords.per_page">
                        <Pagination pageName="cpmPage" :last_page="getCpmRecords.last_page" :current_page="getCpmRecords.current_page" :link="getCpmRecords.first_page_url" :only="['cpmRecords', 'cpmOldRecords', 'cpmOfflineRecords']" />
                    </div>
                </div> <!-- Close CPM Leaderboard -->
                </div> <!-- Close md:flex container -->
            </div> <!-- Close Leaderboards Section -->
        </div> <!-- Close page wrapper -->

        <!-- Add to Maplist Modal -->
        <AddToMaplistModal
            :show="showAddToMaplistModal"
            :map-id="map.id"
            @close="showAddToMaplistModal = false"
            @added="showAddToMaplistModal = false"
        />

        <!-- Assign Demo to Online Record Modal -->
        <div v-if="showAssignModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" @click="closeAssignModal">
            <div class="bg-gray-900/95 rounded-xl p-8 w-full max-w-3xl max-h-[85vh] overflow-y-auto border border-white/10 shadow-2xl" @click.stop>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-100">Assign Demo to Online Record</h3>
                    <button @click="closeAssignModal" class="text-gray-400 hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Demo info -->
                <div v-if="assigningRecord" class="mb-6 p-4 bg-gray-800/60 rounded-lg border border-white/5">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="text-sm"><span class="text-gray-500">Map:</span> <span class="text-gray-200 font-medium">{{ map.name }}</span></div>
                        <div class="text-sm"><span class="text-gray-500">Physics:</span> <span class="font-medium" :class="assignPhysics === 'CPM' ? 'text-purple-400' : 'text-blue-400'">{{ assignPhysics }}</span></div>
                        <div class="text-sm"><span class="text-gray-500">Player:</span> <span class="text-gray-200 font-medium" v-html="q3tohtml(assigningRecord.player_name || assigningRecord.name)"></span></div>
                        <div v-if="assigningRecord.time" class="text-sm"><span class="text-gray-500">Time:</span> <span class="text-gray-200 font-mono font-medium">{{ formatTime(assigningRecord.time) }}</span></div>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="loadingRecords" class="text-center py-8">
                    <div class="text-gray-400 text-lg">Loading records...</div>
                </div>

                <!-- Suggested matches -->
                <div v-if="!loadingRecords && suggestedRecords.length > 0" class="mb-5">
                    <label class="block text-sm font-medium text-green-400 mb-2">
                        Closest time matches
                    </label>
                    <div class="border border-green-700/30 rounded-lg bg-green-900/10 overflow-hidden">
                        <button
                            v-for="record in suggestedRecords"
                            :key="'suggested-' + record.id"
                            @click="selectedRecordId = record.id"
                            :class="[
                                'w-full text-left px-4 py-3 hover:bg-green-800/20 border-b border-green-800/20 last:border-b-0 transition-all',
                                selectedRecordId === record.id ? 'bg-green-600/20 ring-1 ring-green-500/50' : ''
                            ]"
                        >
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-500 font-bold text-sm w-8 text-right">#{{ record.rank }}</span>
                                    <span class="text-base" v-html="q3tohtml(record.player_name)"></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-500" :class="record.timeDiff === 0 ? 'text-green-400 font-bold' : ''">
                                        {{ record.timeDiff === 0 ? 'EXACT' : (record.timeDiff < 1000 ? record.timeDiff + 'ms' : formatTime(record.timeDiff)) + ' diff' }}
                                    </span>
                                    <span class="text-sm font-mono" :class="selectedRecordId === record.id ? 'text-green-300' : 'text-gray-400'">{{ record.formatted_time }}</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Records list -->
                <div v-if="!loadingRecords && availableRecords.length > 0" class="mb-6">
                    <label class="block text-sm font-medium text-gray-400 mb-3">
                        All records ({{ availableRecords.length }})
                    </label>
                    <div class="max-h-[400px] overflow-y-auto border border-gray-700/50 rounded-lg">
                        <button
                            v-for="record in availableRecords"
                            :key="record.id"
                            @click="selectedRecordId = record.id"
                            :class="[
                                'w-full text-left px-4 py-3 hover:bg-white/5 border-b border-gray-800/50 last:border-b-0 transition-all',
                                selectedRecordId === record.id ? 'bg-green-600/20 ring-1 ring-green-500/50' : ''
                            ]"
                        >
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-500 font-bold text-sm w-8 text-right">#{{ record.rank }}</span>
                                    <span class="text-base" v-html="q3tohtml(record.player_name)"></span>
                                </div>
                                <span class="text-sm font-mono" :class="selectedRecordId === record.id ? 'text-green-300' : 'text-gray-400'">{{ record.formatted_time }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- No records -->
                <div v-else class="mb-6 text-center text-gray-400 py-8">
                    No online records found for {{ map.name }} ({{ assignPhysics }})
                </div>

                <!-- Action buttons -->
                <div class="flex justify-end space-x-3 pt-2">
                    <button @click="closeAssignModal" class="px-5 py-2.5 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                        Cancel
                    </button>
                    <button
                        @click="assignDemoToRecord"
                        :disabled="!selectedRecordId"
                        :class="[
                            'px-5 py-2.5 rounded-lg text-white font-medium transition-colors',
                            selectedRecordId ? 'bg-green-600 hover:bg-green-500' : 'bg-gray-600 cursor-not-allowed'
                        ]"
                    >
                        Assign Demo
                    </button>
                </div>
            </div>
        </div>

        <!-- Reverse Assign: Record → Demo Modal -->
        <div v-if="showReverseAssignModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" @click="closeReverseAssignModal">
            <div class="bg-gray-900/95 rounded-xl p-8 w-full max-w-3xl max-h-[85vh] overflow-y-auto border border-purple-500/20 shadow-2xl" @click.stop>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-purple-300">Assign Demo to Record</h3>
                    <button @click="closeReverseAssignModal" class="text-gray-400 hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Record info -->
                <div v-if="reverseAssignRecord" class="mb-6 p-4 bg-gray-800/60 rounded-lg border border-purple-500/10">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="text-sm"><span class="text-gray-500">Record:</span> <span class="text-gray-200 font-medium" v-html="q3tohtml(reverseAssignRecord.user?.name || reverseAssignRecord.name)"></span></div>
                        <div class="text-sm"><span class="text-gray-500">Time:</span> <span class="text-gray-200 font-mono font-medium">{{ formatTime(reverseAssignRecord.time) }}</span></div>
                    </div>
                </div>

                <!-- Matching demos -->
                <div v-if="reverseAssignDemos.length > 0" class="mb-6">
                    <label class="block text-sm font-medium text-purple-400 mb-2">
                        Matching demos ({{ reverseAssignDemos.length }})
                    </label>
                    <div class="border border-purple-700/30 rounded-lg bg-purple-900/10 overflow-hidden">
                        <button
                            v-for="demo in reverseAssignDemos"
                            :key="demo.demo_id"
                            @click="selectedDemoId = demo.demo_id"
                            :class="[
                                'w-full text-left px-5 py-4 hover:bg-purple-800/20 border-b border-purple-800/20 last:border-b-0 transition-all',
                                selectedDemoId === demo.demo_id ? 'bg-purple-600/20 ring-1 ring-purple-500/50' : ''
                            ]"
                        >
                            <!-- Player name + confidence -->
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-lg font-bold" :class="{
                                    'text-green-400': demo.confidence >= 80,
                                    'text-yellow-400': demo.confidence >= 50 && demo.confidence < 80,
                                    'text-orange-400': demo.confidence < 50
                                }">{{ demo.confidence }}%</span>
                                <span class="text-lg font-semibold text-gray-100" v-html="q3tohtml(demo.player_name)"></span>
                                <span v-if="demo.time_ms" class="text-base font-mono text-gray-300 ml-auto">{{ formatTime(demo.time_ms) }}</span>
                            </div>

                            <!-- Filename -->
                            <div class="mb-2 text-sm text-gray-300 break-all">
                                {{ demo.filename }}
                            </div>

                            <!-- Match reasoning -->
                            <div class="text-sm text-gray-300 leading-relaxed bg-gray-800/50 rounded-md px-3 py-2 border-l-2" :class="{
                                'border-green-500': demo.confidence >= 80,
                                'border-yellow-500': demo.confidence >= 50 && demo.confidence < 80,
                                'border-orange-500': demo.confidence < 50
                            }">
                                <span v-if="demo.confidence === 100">
                                    Exact name match — demo player "<span class="text-white font-medium" v-html="q3tohtml(demo.player_name)"></span>" is identical to record holder "<span class="text-white font-medium">{{ demo.record_player_name }}</span>".
                                </span>
                                <span v-else-if="demo.confidence >= 80">
                                    Very similar name — demo player "<span class="text-white font-medium" v-html="q3tohtml(demo.player_name)"></span>" closely matches record holder "<span class="text-white font-medium">{{ demo.record_player_name }}</span>" ({{ demo.confidence }}% similarity).
                                </span>
                                <span v-else-if="demo.confidence >= 50">
                                    Partial name match — demo player "<span class="text-white font-medium" v-html="q3tohtml(demo.player_name)"></span>" is somewhat similar to "<span class="text-white font-medium">{{ demo.record_player_name }}</span>" ({{ demo.confidence }}% similarity).
                                </span>
                                <span v-else>
                                    Weak name match — demo player "<span class="text-white font-medium" v-html="q3tohtml(demo.player_name)"></span>" has low similarity to "<span class="text-white font-medium">{{ demo.record_player_name }}</span>" ({{ demo.confidence }}% similarity).
                                </span>
                                <span class="text-green-400 font-medium"> Time matches exactly.</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="flex justify-end space-x-3 pt-2">
                    <button @click="closeReverseAssignModal" class="px-5 py-2.5 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                        Cancel
                    </button>
                    <button
                        @click="assignDemoFromRecord"
                        :disabled="!selectedDemoId"
                        :class="[
                            'px-5 py-2.5 rounded-lg text-white font-medium transition-colors',
                            selectedDemoId ? 'bg-purple-600 hover:bg-purple-500' : 'bg-gray-600 cursor-not-allowed'
                        ]"
                    >
                        Assign Demo
                    </button>
                </div>
            </div>
        </div>
        <!-- Reassign Modal -->
        <div v-if="showReassignModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" @click="closeReassignModal">
            <div class="bg-gray-900/95 rounded-xl p-8 w-full max-w-3xl border border-yellow-500/20 shadow-2xl" @click.stop>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-yellow-300">Reassign Demo</h3>
                    <button @click="closeReassignModal" class="text-gray-400 hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Current assignment info -->
                <div v-if="reassignCurrentDemo" class="mb-6 p-4 bg-gray-800/60 rounded-lg border border-yellow-500/10">
                    <div class="text-sm text-gray-400 mb-2">Currently assigned demo:</div>
                    <div class="grid grid-cols-1 gap-2">
                        <div class="text-sm"><span class="text-gray-500">File:</span> <span class="text-gray-200 break-all">{{ reassignCurrentDemo.original_filename }}</span></div>
                        <div class="text-sm"><span class="text-gray-500">Player:</span> <span class="text-gray-200" v-html="q3tohtml(reassignCurrentDemo.player_name || 'Unknown')"></span></div>
                        <div v-if="reassignCurrentDemo.time_ms" class="text-sm"><span class="text-gray-500">Time:</span> <span class="text-gray-200 font-mono">{{ formatTime(reassignCurrentDemo.time_ms) }}</span></div>
                    </div>
                </div>

                <p class="text-sm text-gray-400 mb-6">
                    Unassigning the demo will remove it from this record. It will become available for reassignment to a different record.
                </p>

                <!-- Action buttons -->
                <div class="flex justify-end space-x-3 pt-2">
                    <button @click="closeReassignModal" class="px-5 py-2.5 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                        Cancel
                    </button>
                    <button
                        @click="unassignDemo"
                        class="px-5 py-2.5 rounded-lg text-white font-medium transition-colors bg-yellow-600 hover:bg-yellow-500"
                    >
                        Unassign Demo
                    </button>
                </div>
            </div>
        </div>
        <!-- Tag Remove Confirmation Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                leave-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
            >
                <div v-if="showTagRemoveConfirm" class="fixed inset-0 z-[200] flex items-center justify-center p-4" @click.self="showTagRemoveConfirm = false">
                    <div class="fixed inset-0 bg-black/60"></div>
                    <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-md w-full p-6">
                        <h3 class="text-lg font-bold text-white mb-3">Remove Tag</h3>
                        <p class="text-gray-300 mb-2">
                            Remove <span class="font-semibold text-purple-400">{{ tagToRemove?.display_name }}</span> from this map?
                        </p>
                        <div v-if="tagRemoveChildren.length > 0" class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3 mb-4">
                            <p class="text-yellow-400 text-sm font-semibold mb-1">This is a parent tag. The following child tags will also be removed:</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="child in tagRemoveChildren" :key="child.id" class="bg-purple-600/20 border border-purple-500/30 text-purple-300 px-2 py-0.5 rounded-full text-xs">
                                    {{ child.display_name }}
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button
                                @click="executeRemoveTag"
                                class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-xl transition-colors"
                            >
                                Remove{{ tagRemoveChildren.length > 0 ? ' All' : '' }}
                            </button>
                            <button
                                @click="showTagRemoveConfirm = false"
                                class="flex-1 px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-300 font-semibold rounded-xl border border-white/10 transition-colors"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Blocked keyword modal -->
        <Teleport to="body">
            <div v-if="showBlockedTagModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4" @click.self="showBlockedTagModal = false">
                <div class="fixed inset-0 bg-black/60"></div>
                <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-md w-full p-6">
                    <h3 class="text-lg font-bold text-white mb-3">Tag Suggestion</h3>
                    <p class="text-gray-300 mb-4">
                        This keyword is not a valid tag. Did you mean:
                    </p>
                    <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4 mb-4 flex items-center gap-3">
                        <span class="px-2.5 py-1 rounded-full text-sm font-semibold bg-blue-600 text-white">{{ blockedSuggestedTag?.display_name }}</span>
                        <span v-if="blockedSuggestedTag?.note" class="text-sm text-gray-400">{{ blockedSuggestedTag.note }}</span>
                    </div>
                    <div class="flex gap-3">
                        <button
                            @click="acceptSuggestedTag"
                            class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors"
                        >
                            Add "{{ blockedSuggestedTag?.display_name }}"
                        </button>
                        <button
                            @click="showBlockedTagModal = false"
                            class="flex-1 px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-300 font-semibold rounded-xl border border-white/10 transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Score tooltip -->
        <Teleport to="body">
            <div v-if="scoreTooltip" :style="scoreTooltipStyle"
                class="px-3 py-2 rounded-lg bg-gray-900 border border-white/15 text-[10px] text-gray-300 whitespace-nowrap shadow-2xl pointer-events-none">
                <div>Score: <span class="text-yellow-400 font-bold">{{ scoreTooltip.score }}</span></div>
                <div>Reltime: <span class="text-blue-400">{{ scoreTooltip.reltime }}</span></div>
                <div v-if="scoreTooltip.multiplier != null">Multiplier: <span class="text-green-400">{{ scoreTooltip.multiplier }}</span></div>
            </div>
        </Teleport>
    </div>
</template>

<style>
.mapview-tags-overlay-teleported {
    z-index: 9999;
    background: #1a1f2e;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 0.5rem;
    padding: 0.75rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
}
</style>

<style scoped>
.map-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 26px;
    padding: 0 0.75rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 800;
    color: white;
    line-height: 1;
    white-space: nowrap;
    letter-spacing: 0.02em;
}

.mapview-tags-bar {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    padding: 0.1rem 0.75rem;
    cursor: text;
    transition: border-color 0.2s, background 0.2s;
}
.mapview-tags-bar:hover,
.mapview-tags-bar-focus {
    border-color: rgba(255, 255, 255, 0.3);
    background: rgba(0, 0, 0, 0.5);
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.1);
}

</style>
