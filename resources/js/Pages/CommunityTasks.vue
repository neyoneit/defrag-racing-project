<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
    inheritAttrs: false,
}
</script>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick, getCurrentInstance, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    assignmentTasks: Array,
    verificationTasks: Array,
    difficultyTasks: Array,
    tagTasks: Array,
    communityScore: Object,
    tiers: Array,
    weights: Object,
    personalBest: Object,
    leaderboard: Array,
});

const $page = usePage();
const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

// ─── State ─────────────────────────────────────────────
const phase = ref('assignment'); // 'assignment' | 'verification' | 'rating' | 'tagging' | 'summary'
const sessionPoints = ref(0);
const streak = ref(0);
const totalPointsEver = ref(0);

// Assignment state
const assignmentQueue = ref([...props.assignmentTasks]);
const completedAssignments = ref(0);
const selectedRecords = ref({}); // { demoId: record }
const confirmingTask = ref(null);
const confirmingRecord = ref(null);
const assigning = ref(false);
const completingSlot = ref(null);
const assignHints = ref({}); // { demoId: 'message' }

// Verification state
const verificationQueue = ref([...(props.verificationTasks || [])]);
const completedVerifications = ref(0);

// Rating state
const ratingQueue = ref([...props.difficultyTasks]);
const completedRatings = ref(0);
const ratedMapIds = ref(new Set());
const ratedValues = ref({});
const ratingSubmitting = ref(null);
const renderRequesting = ref(null);

// Tag state
const tagQueue = ref([...(props.tagTasks || [])]);
const completedTags = ref(0);
const tagInput = ref('');
const tagSuggestions = ref([]);
const allTags = ref([]);
const showTagSuggestions = ref(false);
const addingTag = ref(false);
const taggedCurrentMap = ref(false);

// Effects state
const showPointSplash = ref(false);
const lastPointsEarned = ref(0);
const pointSplashX = ref(50);
const pointSplashY = ref(50);
const confettiCanvas = ref(null);
let confettiSystem = null;

// Leaderboard state
const personalBest = ref(props.personalBest || { best_points: 0, total_sessions: 0, total_points: 0 });
const leaderboardTop = ref(props.leaderboard || []);
const showLeaderboardModal = ref(false);
const fullLeaderboard = ref([]);
const loadingLeaderboard = ref(false);
const sessionSaved = ref(false);
const loadingMore = ref(false);
const roundNumber = ref(1);

// Audio
let audioCtx = null;

// ─── Computed ──────────────────────────────────────────
const totalScore = computed(() => {
    return (props.communityScore?.total_score || 0) + sessionPoints.value;
});

const badgeScore = computed(() => {
    return (props.communityScore?.community_badge_score || 0) + sessionPoints.value;
});

const currentTier = computed(() => {
    if (!props.tiers) return null;
    let tier = null;
    const score = badgeScore.value;
    for (const t of props.tiers) {
        if (score >= t.min_score) tier = t;
    }
    return tier;
});

const nextTier = computed(() => {
    if (!props.tiers) return null;
    const score = badgeScore.value;
    for (const t of props.tiers) {
        if (score < t.min_score) return t;
    }
    return null;
});

const tierProgress = computed(() => {
    if (!currentTier.value && nextTier.value) {
        return Math.min((badgeScore.value / nextTier.value.min_score) * 100, 100);
    }
    if (!nextTier.value) return 100;
    const current = currentTier.value?.min_score || 0;
    const next = nextTier.value.min_score;
    return Math.min(((badgeScore.value - current) / (next - current)) * 100, 100);
});

const currentTask = computed(() => {
    if (phase.value === 'assignment') return assignmentQueue.value[0] || null;
    if (phase.value === 'verification') return verificationQueue.value[0] || null;
    return null;
});

const currentRating = computed(() => ratingQueue.value[0] || null);
const currentTagMap = computed(() => tagQueue.value[0] || null);

const totalTasks = computed(() => {
    return props.assignmentTasks.length + (props.verificationTasks?.length || 0)
        + props.difficultyTasks.length + (props.tagTasks?.length || 0);
});

const completedTasks = computed(() => {
    return completedAssignments.value + completedVerifications.value + completedRatings.value + completedTags.value;
});

const difficultyLabels = [
    { level: 1, label: 'Beginner', short: 'B', color: 'bg-green-600', bgSubtle: 'bg-green-500/10 text-green-400/70', hover: 'hover:bg-green-600/30', ring: 'ring-green-400', desc: 'Basic movement' },
    { level: 2, label: 'Easy', short: 'E', color: 'bg-lime-600', bgSubtle: 'bg-lime-500/10 text-lime-400/70', hover: 'hover:bg-lime-600/30', ring: 'ring-lime-400', desc: 'Simple tricks' },
    { level: 3, label: 'Medium', short: 'M', color: 'bg-yellow-600', bgSubtle: 'bg-yellow-500/10 text-yellow-400/70', hover: 'hover:bg-yellow-600/30', ring: 'ring-yellow-400', desc: 'Weapon boosts' },
    { level: 4, label: 'Hard', short: 'H', color: 'bg-orange-600', bgSubtle: 'bg-orange-500/10 text-orange-400/70', hover: 'hover:bg-orange-600/30', ring: 'ring-orange-400', desc: 'Advanced combos' },
    { level: 5, label: 'Extreme', short: 'X', color: 'bg-red-600', bgSubtle: 'bg-red-500/10 text-red-400/70', hover: 'hover:bg-red-600/30', ring: 'ring-red-400', desc: 'Top-level' },
];

// ─── Time formatting ───────────────────────────────────
function formatTime(ms) {
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    const millis = ms % 1000;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}.${String(millis).padStart(3, '0')}`;
}

function formatTimeDiff(ms) {
    if (ms === 0) return 'EXACT';
    if (ms < 1000) return `${ms}ms`;
    if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
    return formatTime(ms);
}

// Signed diff: negative = record is faster, positive = record is slower
function formatSignedDiff(recordTime, demoTime) {
    const diff = recordTime - demoTime;
    if (diff === 0) return 'EXACT';
    const abs = Math.abs(diff);
    const prefix = diff < 0 ? '-' : '+';
    if (abs < 1000) return `${prefix}${abs}ms`;
    if (abs < 60000) return `${prefix}${(abs / 1000).toFixed(1)}s`;
    return prefix + formatTime(abs);
}

function isDemoFaster(recordTime, demoTime) {
    return recordTime > demoTime; // record is slower = demo is faster
}

function hasExactMatch(task) {
    return task.closest_matches?.some(r => r.time_diff === 0) || task.all_records?.some(r => r.time_diff === 0);
}


// ─── Vote API helper ───────────────────────────────────
async function submitVote(demoId, voteType, taskType, selectedRecordId = null) {
    const { data } = await axios.post('/community-tasks/vote', {
        demo_id: demoId,
        vote_type: voteType,
        task_type: taskType,
        selected_record_id: selectedRecordId,
    });
    return data;
}

// ─── Assignment logic ──────────────────────────────────
function selectRecord(task, record) {
    if (selectedRecords.value[task.demo.id]?.id === record.id) {
        delete selectedRecords.value[task.demo.id];
    } else {
        selectedRecords.value[task.demo.id] = record;
    }
    delete assignHints.value[task.demo.id];
}

function tryAssign(task) {
    const record = selectedRecords.value[task.demo.id];
    if (!record) {
        assignHints.value[task.demo.id] = 'Click on a record first to select it';
        setTimeout(() => { delete assignHints.value[task.demo.id]; }, 3000);
        return;
    }
    confirmingTask.value = task;
    confirmingRecord.value = record;
}

function cancelConfirm() {
    confirmingTask.value = null;
    confirmingRecord.value = null;
}

async function confirmAssign() {
    if (assigning.value) return;
    assigning.value = true;

    const task = confirmingTask.value;
    const record = confirmingRecord.value;
    const taskType = task.task_type || 'assignment';
    const voteType = taskType === 'verification' ? 'better_match' : 'assign';

    try {
        const result = await submitVote(task.demo.id, voteType, taskType, record.id);

        if (taskType === 'verification') {
            completedVerifications.value++;
        } else {
            completedAssignments.value++;
        }
        streak.value++;
        awardPoints(result.points || 3);

        await removeTask(task.demo.id, taskType);

        confirmingTask.value = null;
        confirmingRecord.value = null;
        delete selectedRecords.value[task.demo.id];
    } catch (err) {
        console.error('Vote failed:', err);
        streak.value = 0;
    } finally {
        assigning.value = false;
    }
}

async function skipTask(task, reason) {
    const taskType = task.task_type || 'assignment';
    try {
        await submitVote(task.demo.id, reason === 'not_sure' ? 'not_sure' : 'no_match', taskType);
    } catch (err) {
        console.error('Vote failed:', err);
    }

    streak.value = 0;
    awardPoints(1);
    if (taskType === 'verification') {
        completedVerifications.value++;
    } else {
        completedAssignments.value++;
    }
    delete selectedRecords.value[task.demo.id];
    if (confirmingTask.value?.demo.id === task.demo.id) {
        confirmingTask.value = null;
        confirmingRecord.value = null;
    }
    await removeTask(task.demo.id, taskType);
}

// ─── Verification logic ────────────────────────────────
async function confirmCorrect(task) {
    try {
        const result = await submitVote(task.demo.id, 'correct', 'verification');
        completedVerifications.value++;
        streak.value++;
        awardPoints(result.points || 1);
        await removeTask(task.demo.id, 'verification');
    } catch (err) {
        console.error('Vote failed:', err);
        streak.value = 0;
    }
}

async function voteUnassign(task) {
    try {
        const result = await submitVote(task.demo.id, 'unassign', 'verification');
        completedVerifications.value++;
        streak.value++;
        awardPoints(result.points || 1);
        await removeTask(task.demo.id, 'verification');
    } catch (err) {
        console.error('Vote failed:', err);
        streak.value = 0;
    }
}

function tryBetterMatch(task) {
    const record = selectedRecords.value[task.demo.id];
    if (!record) {
        assignHints.value[task.demo.id] = 'Select a better record first';
        setTimeout(() => { delete assignHints.value[task.demo.id]; }, 3000);
        return;
    }
    confirmingTask.value = task;
    confirmingRecord.value = record;
}

// ─── Task removal ──────────────────────────────────────
async function removeTask(demoId, taskType) {
    const queue = taskType === 'verification' ? verificationQueue : assignmentQueue;
    const idx = queue.value.findIndex(t => t.demo.id === demoId);
    if (idx === -1) return;

    completingSlot.value = demoId;
    await sleep(600);
    queue.value.splice(idx, 1);
    completingSlot.value = null;

    // Preload when running low
    const totalRemaining = assignmentQueue.value.length + verificationQueue.value.length + ratingQueue.value.length + tagQueue.value.length;
    if (totalRemaining <= 1) {
        preloadNextRound();
    }

    advancePhase();
}

function advancePhase() {
    if (phase.value === 'assignment' && assignmentQueue.value.length === 0) {
        if (verificationQueue.value.length > 0) phase.value = 'verification';
        else if (ratingQueue.value.length > 0) phase.value = 'rating';
        else if (tagQueue.value.length > 0) { phase.value = 'tagging'; loadAllTags().then(filterTagSuggestions); }
        else loadNextRound();
    } else if (phase.value === 'verification' && verificationQueue.value.length === 0) {
        if (ratingQueue.value.length > 0) phase.value = 'rating';
        else if (tagQueue.value.length > 0) { phase.value = 'tagging'; loadAllTags().then(filterTagSuggestions); }
        else loadNextRound();
    } else if (phase.value === 'rating' && ratingQueue.value.length === 0) {
        if (tagQueue.value.length > 0) phase.value = 'tagging';
        else loadNextRound();
    } else if (phase.value === 'tagging' && tagQueue.value.length === 0) {
        loadNextRound();
    }
}

let preloadedData = null;
let preloadPromise = null;

function preloadNextRound() {
    if (preloadPromise || preloadedData) return;
    preloadPromise = axios.post('/community-tasks/refresh')
        .then(({ data }) => { preloadedData = data; })
        .catch(() => {})
        .finally(() => { preloadPromise = null; });
}

async function loadNextRound() {
    if (loadingMore.value) return;
    loadingMore.value = true;
    roundNumber.value++;

    await autoSaveSession();

    try {
        // Use preloaded data if available, otherwise fetch now
        if (!preloadedData) {
            if (preloadPromise) await preloadPromise;
            else {
                const { data } = await axios.post('/community-tasks/refresh');
                preloadedData = data;
            }
        }

        const data = preloadedData;
        preloadedData = null;

        const hasAny = data.assignmentTasks.length > 0 || (data.verificationTasks?.length > 0)
            || data.difficultyTasks.length > 0 || (data.tagTasks?.length > 0);

        if (!hasAny) {
            phase.value = 'summary';
            triggerFinalCelebration();
            return;
        }

        assignmentQueue.value.push(...data.assignmentTasks);
        verificationQueue.value.push(...(data.verificationTasks || []));
        ratingQueue.value.push(...data.difficultyTasks);
        tagQueue.value.push(...(data.tagTasks || []));
        personalBest.value = data.personalBest;
        leaderboardTop.value = data.leaderboard;

        if (assignmentQueue.value.length > 0) phase.value = 'assignment';
        else if (verificationQueue.value.length > 0) phase.value = 'verification';
        else if (ratingQueue.value.length > 0) phase.value = 'rating';
        else phase.value = 'tagging';
    } catch (err) {
        console.error('Failed to load more tasks:', err);
        phase.value = 'summary';
        triggerFinalCelebration();
    } finally {
        loadingMore.value = false;
    }
}

// ─── Rating logic ──────────────────────────────────────
async function rateMap(map, level) {
    if (ratedMapIds.value.has(map.id) || ratingSubmitting.value === map.id) return;
    ratingSubmitting.value = map.id;

    try {
        await axios.post(`/maps/${map.id}/rate-difficulty`, { rating: level });
        ratedMapIds.value.add(map.id);
        ratedValues.value[map.id] = level;
        completedRatings.value++;
        streak.value++;
        awardPoints(props.weights?.difficulty_ratings || 1);

        await sleep(600);
        ratingQueue.value.shift();
        if (ratingQueue.value.length <= 1) preloadNextRound();
        advancePhase();
    } catch (err) {
        console.error('Rating failed:', err);
        streak.value = 0;
    } finally {
        ratingSubmitting.value = null;
    }
}

async function skipRating(map) {
    streak.value = 0;
    awardPoints(1);
    completedRatings.value++;
    ratingQueue.value.shift();
    if (ratingQueue.value.length <= 1) preloadNextRound();
    advancePhase();
}

async function requestRenderAndSkip(map) {
    renderRequesting.value = map.id;
    try {
        await axios.post('/community-tasks/request-render', { map_id: map.id });
    } catch (err) {
        console.warn('Render request failed:', err.response?.data?.error || err.message);
    }
    renderRequesting.value = null;
    skipRating(map);
}

async function requestTagRenderAndSkip(map) {
    renderRequesting.value = map.id;
    try {
        await axios.post('/community-tasks/request-render', { map_id: map.id });
    } catch (err) {
        console.warn('Render request failed:', err.response?.data?.error || err.message);
    }
    renderRequesting.value = null;
    skipTag();
}

// ─── Tag logic ─────────────────────────────────────────
async function loadAllTags() {
    if (allTags.value.length > 0) {
        filterTagSuggestions();
        return;
    }
    try {
        const { data } = await axios.get('/api/tags');
        allTags.value = data.tags || data;
        filterTagSuggestions();
    } catch (err) {
        console.error('Failed to load tags:', err);
    }
}

function filterTagSuggestions() {
    const q = tagInput.value.toLowerCase().trim();
    const currentMap = currentTagMap.value;
    const existingIds = new Set((currentMap?.tags || []).map(t => t.id));
    const filtered = allTags.value.filter(t => !existingIds.has(t.id));
    tagSuggestions.value = q
        ? filtered.filter(t => t.display_name.toLowerCase().includes(q))
        : filtered;
}

async function addTagToMap(tagName) {
    const map = currentTagMap.value;
    if (!map || addingTag.value) return;
    addingTag.value = true;

    try {
        const { data } = await axios.post('/api/maps/' + map.id + '/tags', { tag_name: tagName });
        map.tags.push(data.tag);
        if (data.parent_tag_added && !map.tags.some(t => t.id === data.parent_tag_added.id)) {
            map.tags.push(data.parent_tag_added);
        }
        map.tags.sort((a, b) => a.display_name.localeCompare(b.display_name));
        tagInput.value = '';
        taggedCurrentMap.value = true;
        filterTagSuggestions();
        streak.value++;
        awardPoints(props.weights?.tags_added || 1);
    } catch (err) {
        console.error('Failed to add tag:', err);
    } finally {
        addingTag.value = false;
    }
}

async function skipTag() {
    if (taggedCurrentMap.value) {
        // Already tagged at least once - count as completed
    } else {
        streak.value = 0;
        awardPoints(1);
    }
    completedTags.value++;
    taggedCurrentMap.value = false;
    tagInput.value = '';
    tagQueue.value.shift();
    if (tagQueue.value.length <= 1) preloadNextRound();
    advancePhase();
}

// ─── Points & Effects ──────────────────────────────────
function awardPoints(points) {
    const oldScore = sessionPoints.value;
    sessionPoints.value += points;
    lastPointsEarned.value = points;

    // Point splash
    showPointSplash.value = true;
    setTimeout(() => { showPointSplash.value = false; }, 1200);

    // Sound
    playPointSound(points);

    // Confetti every 50 points
    const oldFifty = Math.floor(oldScore / 50);
    const newFifty = Math.floor(sessionPoints.value / 50);
    if (newFifty > oldFifty) {
        const intensity = Math.min(streak.value / 3, 3);
        triggerConfetti(40 + streak.value * 10, intensity);
        playMilestoneSound();
    }

    // Streak-based mini confetti
    if (streak.value >= 3 && streak.value % 3 === 0) {
        triggerConfetti(15 + streak.value * 2, 1.5);
    }
}

// ─── Audio (Web Audio API) ─────────────────────────────
function getAudioCtx() {
    if (!audioCtx) {
        try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); }
        catch { return null; }
    }
    return audioCtx;
}

function playTone(freq, duration, type = 'sine', volume = 0.08, delay = 0) {
    const ctx = getAudioCtx();
    if (!ctx) return;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(volume, ctx.currentTime + delay);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + duration);
    osc.connect(gain).connect(ctx.destination);
    osc.start(ctx.currentTime + delay);
    osc.stop(ctx.currentTime + delay + duration);
}

function playPointSound(points) {
    if (points >= 3) {
        // Ascending arpeggio for assignment
        playTone(523, 0.12, 'sine', 0.06);
        playTone(659, 0.12, 'sine', 0.06, 0.08);
        playTone(784, 0.18, 'sine', 0.07, 0.16);
    } else {
        // Simple chime for rating
        playTone(880, 0.15, 'sine', 0.05);
    }
}

function playMilestoneSound() {
    [523, 659, 784, 1047].forEach((freq, i) => {
        playTone(freq, 0.25, 'sine', 0.06, i * 0.12);
    });
}

// ─── Confetti System ───────────────────────────────────
const particles = ref([]);
let animFrameId = null;

function triggerConfetti(count = 40, intensity = 1) {
    const canvas = confettiCanvas.value;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const colors = ['#fbbf24', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#10b981', '#ec4899', '#f97316'];
    const cx = canvas.width / 2;
    const cy = canvas.height * 0.35;

    for (let i = 0; i < count; i++) {
        particles.value.push({
            x: cx + (Math.random() - 0.5) * 200,
            y: cy + (Math.random() - 0.5) * 100,
            vx: (Math.random() - 0.5) * 12 * intensity,
            vy: (Math.random() - 1) * 14 * intensity,
            color: colors[Math.floor(Math.random() * colors.length)],
            size: Math.random() * 5 + 2,
            rotation: Math.random() * 360,
            rotSpeed: (Math.random() - 0.5) * 12,
            life: 1,
            decay: 0.008 + Math.random() * 0.008,
        });
    }

    if (!animFrameId) animateConfetti();
}

function animateConfetti() {
    const canvas = confettiCanvas.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    particles.value = particles.value.filter(p => p.life > 0);

    for (const p of particles.value) {
        p.x += p.vx;
        p.y += p.vy;
        p.vy += 0.25;
        p.vx *= 0.99;
        p.rotation += p.rotSpeed;
        p.life -= p.decay;

        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate((p.rotation * Math.PI) / 180);
        ctx.globalAlpha = Math.max(0, p.life);
        ctx.fillStyle = p.color;
        ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 1.6);
        ctx.restore();
    }

    if (particles.value.length > 0) {
        animFrameId = requestAnimationFrame(animateConfetti);
    } else {
        animFrameId = null;
    }
}

function triggerFinalCelebration() {
    playMilestoneSound();
    setTimeout(() => playMilestoneSound(), 400);
    for (let i = 0; i < 5; i++) {
        setTimeout(() => triggerConfetti(60, 2), i * 300);
    }
    autoSaveSession();
}

// ─── Session management ────────────────────────────────
let lastSavedPoints = 0;

async function autoSaveSession() {
    if (sessionPoints.value === 0 || sessionPoints.value === lastSavedPoints) return;

    try {
        const { data } = await axios.post('/community-tasks/save-session', {
            points: sessionPoints.value,
            assignments_completed: completedAssignments.value,
            ratings_completed: completedRatings.value,
        });
        lastSavedPoints = sessionPoints.value;
        personalBest.value = data.personalBest;
        leaderboardTop.value = data.leaderboard;
    } catch (err) {
        console.error('Failed to save session:', err);
    }
}

async function endSession() {
    await autoSaveSession();
    phase.value = 'summary';
    triggerFinalCelebration();
}

async function startNewSession() {
    try {
        const { data } = await axios.post('/community-tasks/refresh');
        assignmentQueue.value = [...data.assignmentTasks];
        verificationQueue.value = [...(data.verificationTasks || [])];
        ratingQueue.value = [...data.difficultyTasks];
        tagQueue.value = [...(data.tagTasks || [])];
        completedAssignments.value = 0;
        completedVerifications.value = 0;
        completedRatings.value = 0;
        completedTags.value = 0;
        selectedRecords.value = {};
        ratedMapIds.value = new Set();
        ratedValues.value = {};
        taggedCurrentMap.value = false;
        sessionPoints.value = 0;
        streak.value = 0;
        lastSavedPoints = 0;
        roundNumber.value = 1;
        personalBest.value = data.personalBest;
        leaderboardTop.value = data.leaderboard;
        if (data.assignmentTasks.length > 0) phase.value = 'assignment';
        else if (data.verificationTasks?.length > 0) phase.value = 'verification';
        else if (data.difficultyTasks.length > 0) phase.value = 'rating';
        else if (data.tagTasks?.length > 0) phase.value = 'tagging';
        else phase.value = 'summary';
    } catch (err) {
        console.error('Failed to refresh tasks:', err);
    }
}

async function openFullLeaderboard() {
    showLeaderboardModal.value = true;
    if (fullLeaderboard.value.length === 0) {
        loadingLeaderboard.value = true;
        try {
            const { data } = await axios.get('/community-tasks/leaderboard');
            fullLeaderboard.value = data;
        } catch (err) {
            console.error('Failed to load leaderboard:', err);
        } finally {
            loadingLeaderboard.value = false;
        }
    }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

watch(phase, (val) => {
    if (val === 'tagging') {
        loadAllTags();
    }
});

// ─── Lifecycle ─────────────────────────────────────────
function handleBeforeUnload() {
    if (sessionPoints.value > 0 && sessionPoints.value !== lastSavedPoints) {
        navigator.sendBeacon('/community-tasks/save-session',
            new Blob([JSON.stringify({
                points: sessionPoints.value,
                assignments_completed: completedAssignments.value,
                ratings_completed: completedRatings.value,
                _token: document.querySelector('meta[name="csrf-token"]')?.content,
            })], { type: 'application/json' })
        );
    }
}

onMounted(() => {
    totalPointsEver.value = props.communityScore?.total_score || 0;
    window.addEventListener('beforeunload', handleBeforeUnload);

    if (props.assignmentTasks.length === 0) {
        if (props.verificationTasks?.length > 0) phase.value = 'verification';
        else if (props.difficultyTasks.length > 0) phase.value = 'rating';
        else if (props.tagTasks?.length > 0) { phase.value = 'tagging'; loadAllTags(); }
        else phase.value = 'summary';
    }
});

onUnmounted(() => {
    if (animFrameId) cancelAnimationFrame(animFrameId);
    if (audioCtx) audioCtx.close();
    window.removeEventListener('beforeunload', handleBeforeUnload);
    autoSaveSession();
});
</script>

<template>
    <Head title="Community Tasks" />

    <!-- Confetti canvas overlay -->
    <canvas ref="confettiCanvas" class="fixed inset-0 pointer-events-none z-50" />

    <!-- Point splash overlay -->
    <Transition name="point-splash">
        <div v-if="showPointSplash"
            class="fixed inset-0 flex items-center justify-center z-40 pointer-events-none"
            style="top: -10%">
            <div class="text-7xl font-black text-yellow-400 drop-shadow-[0_0_30px_rgba(251,191,36,0.5)]"
                style="animation: pointPop 1.2s ease-out forwards">
                +{{ lastPointsEarned }}
            </div>
        </div>
    </Transition>

    <div class="min-h-[80vh]">
        <!-- Header -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pt-8 pb-4">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-4">
                        <h1 class="text-3xl font-black text-gray-200">Community Tasks</h1>
                        <span v-if="roundNumber > 1" class="text-xs text-gray-500 bg-gray-800 px-2 py-0.5 rounded">Round {{ roundNumber }}</span>
                    </div>
                    <div class="flex items-center gap-3 mt-1">
                        <p class="text-gray-500 text-sm">Assign demos, verify records, rate difficulty, tag maps. All actions earn session points (Tasks Leaderboard). Assigns and ratings also count towards <a :href="route('community')" class="text-gray-400 hover:text-white underline">Community Leaderboard</a>.</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 flex-wrap">
                    <!-- Streak -->
                    <div v-if="streak >= 2" class="text-center animate-pulse">
                        <div class="text-2xl">
                            {{ streak >= 10 ? '&#x1F525;&#x1F525;' : streak >= 5 ? '&#x1F525;' : '&#x26A1;' }}
                        </div>
                        <div class="text-xs font-bold"
                            :class="streak >= 10 ? 'text-red-400' : streak >= 5 ? 'text-orange-400' : 'text-yellow-400'">
                            x{{ streak }}
                        </div>
                    </div>

                    <!-- Personal Best -->
                    <div class="flex items-center gap-3 bg-white/5 backdrop-blur-md rounded-lg px-3 py-2 border border-white/10"
                        :class="sessionPoints > personalBest.best_points && sessionPoints > 0 ? 'border-yellow-500/30' : ''">
                        <div>
                            <div class="text-[10px] uppercase tracking-wider" :class="sessionPoints > personalBest.best_points && sessionPoints > 0 ? 'text-yellow-400' : 'text-gray-400'">
                                {{ sessionPoints > personalBest.best_points && sessionPoints > 0 ? 'New PB!' : 'PB' }}
                            </div>
                            <div class="text-xl font-black tabular-nums transition-all" :class="sessionPoints > personalBest.best_points && sessionPoints > 0 ? 'text-yellow-300' : personalBest.best_points > 0 ? 'text-yellow-400' : 'text-gray-500'">
                                {{ Math.max(personalBest.best_points, sessionPoints) }}
                            </div>
                        </div>
                        <div class="border-l border-white/10 pl-3 text-[10px] text-gray-300">
                            <div>{{ personalBest.total_sessions }} runs</div>
                            <div>{{ personalBest.total_points }} pts</div>
                        </div>
                    </div>

                    <!-- Session score -->
                    <div class="bg-white/5 backdrop-blur-md rounded-lg px-3 py-2 border border-white/10">
                        <div class="text-[10px] text-gray-400 uppercase tracking-wider">Session</div>
                        <div class="text-xl font-black tabular-nums"
                            :class="sessionPoints > personalBest.best_points && sessionPoints > 0 ? 'text-green-400' : sessionPoints > 0 ? 'text-white' : 'text-gray-500'">
                            {{ sessionPoints }}
                        </div>
                    </div>

                    <!-- Leaderboard Top 3 -->
                    <div class="bg-white/5 backdrop-blur-md rounded-lg px-3 py-2 border border-white/10 cursor-pointer hover:border-white/20 transition-colors"
                        @click="openFullLeaderboard">
                        <div class="flex items-center gap-2 mb-1">
                            <div class="text-[10px] text-gray-400 uppercase tracking-wider">Top</div>
                            <span class="text-[10px] text-gray-500 hover:text-white">&#x2192;</span>
                        </div>
                        <div v-if="leaderboardTop.length === 0" class="text-xs text-gray-400">-</div>
                        <div v-else class="flex items-center gap-3">
                            <div v-for="entry in leaderboardTop.slice(0, 3)" :key="entry.user_id"
                                class="flex items-center gap-1">
                                <span class="text-[10px] font-bold" :class="entry.rank === 1 ? 'text-yellow-400' : entry.rank === 2 ? 'text-gray-300' : 'text-amber-600'">
                                    #{{ entry.rank }}
                                </span>
                                <span class="text-[10px] truncate max-w-[80px]" v-html="q3tohtml(entry.name)"></span>
                                <span class="text-[10px] font-bold text-yellow-400 tabular-nums">{{ entry.best_points }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tier progress -->
                    <div class="w-36 hidden lg:block">
                        <div class="flex justify-between text-[10px] text-gray-500 mb-1">
                            <span :style="currentTier ? { color: currentTier.color } : {}">
                                {{ currentTier?.name || 'Unranked' }}
                            </span>
                            <span v-if="nextTier" :style="{ color: nextTier.color }">{{ nextTier.name }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-1000 ease-out"
                                :style="{
                                    width: tierProgress + '%',
                                    background: currentTier ? `linear-gradient(90deg, ${currentTier.color}, ${nextTier?.color || currentTier.color})` : '#fbbf24'
                                }" />
                        </div>
                        <div class="text-[10px] text-gray-600 mt-0.5 text-center tabular-nums">
                            {{ Math.round(badgeScore) }} / {{ nextTier?.min_score || '--' }} pts
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phase progress bar -->
            <div class="mt-4 flex items-center gap-1">
                <div v-for="i in totalTasks" :key="i"
                    class="h-1 flex-1 rounded-full transition-all duration-500"
                    :class="i <= completedTasks ? 'bg-yellow-400/80' : 'bg-gray-800'" />
            </div>
            <div class="mt-1.5 flex items-center justify-between">
                <span class="text-xs text-gray-400">
                    {{ phase === 'assignment' ? 'Demo Assignment' : phase === 'verification' ? 'Verify Assignments' : phase === 'rating' ? 'Difficulty Rating' : phase === 'tagging' ? 'Map Tagging' : 'Complete' }}
                    <span class="text-gray-500 ml-1">{{ completedTasks }}/{{ totalTasks }}</span>
                </span>
                <span v-if="phase === 'assignment'" class="text-xs text-blue-400">
                    +3 pts per assign
                </span>
                <span v-else-if="phase === 'verification'" class="text-xs text-emerald-400">
                    +1 pt confirm / +3 pts better match
                </span>
                <span v-else-if="phase === 'rating'" class="text-xs text-purple-400">
                    +{{ weights?.difficulty_ratings || 1 }} pt per rating
                </span>
                <span v-else-if="phase === 'tagging'" class="text-xs text-amber-400">
                    +{{ weights?.tags_added || 1 }} pt per tag
                </span>
                <button v-if="phase !== 'summary' && sessionPoints > 0" @click="endSession"
                    class="text-xs text-gray-400 hover:text-red-400 bg-gray-800/60 hover:bg-red-500/10 border border-gray-700 hover:border-red-500/40 px-3 py-1 rounded-lg transition-all font-medium">
                    End Session
                </button>
            </div>
        </div>

        <!-- ═══ ASSIGNMENT PHASE ═══ -->
        <div v-if="phase === 'assignment'" class="max-w-lg mx-auto px-4 md:px-6 lg:px-8 pb-8">
            <div v-if="!currentTask" class="text-center py-16 text-gray-500">
                {{ loadingMore ? 'Loading more tasks...' : 'No demos available for assignment right now.' }}
            </div>

            <div v-if="currentTask" v-for="task in [currentTask]" :key="task.demo.id">
                    <div
                        class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-gray-700/40 relative transition-all duration-300"
                        :class="completingSlot === task.demo.id ? 'scale-95 opacity-0' : ''"
                    >

                        <!-- ── Confirm overlay (only after clicking Assign) ── -->
                        <Transition name="confirm-slide">
                            <div v-if="confirmingTask?.demo.id === task.demo.id"
                                class="absolute inset-0 z-20 bg-gray-900 flex flex-col p-4 rounded-xl overflow-y-auto">
                                <div class="text-sm font-bold text-gray-400 mb-3 text-center">Confirm Assignment</div>

                                <div class="bg-gray-800/80 rounded-lg p-3 mb-2">
                                    <div class="text-[10px] text-gray-600 uppercase tracking-wider">Demo</div>
                                    <div class="text-lg font-bold" v-html="q3tohtml(task.demo.player_name || 'Unknown')"></div>
                                    <div class="text-white font-mono text-xl font-black mt-0.5">{{ formatTime(task.demo.time_ms) }}</div>
                                    <div class="text-gray-500 text-xs mt-0.5">
                                        {{ task.demo.map_name }} - {{ task.demo.physics }}
                                    </div>
                                </div>

                                <div class="text-center text-gray-600 text-lg my-1">&#x2193;</div>

                                <div class="bg-gray-800/80 rounded-lg p-3 mb-3">
                                    <div class="text-[10px] text-gray-600 uppercase tracking-wider">Record #{{ confirmingRecord.rank }}</div>
                                    <div class="text-lg font-bold" v-html="q3tohtml(confirmingRecord.player_name || 'Unknown')"></div>
                                    <div class="font-mono text-xl font-black mt-0.5" :class="confirmingRecord.time_diff === 0 ? 'text-green-400' : 'text-white'">{{ formatTime(confirmingRecord.time) }}</div>
                                    <div class="mt-1">
                                        <span v-if="confirmingRecord.time_diff === 0"
                                            class="text-green-400 text-sm font-bold">EXACT MATCH</span>
                                        <span v-else class="text-yellow-400/80 text-sm">
                                            {{ formatTimeDiff(confirmingRecord.time_diff) }} difference
                                        </span>
                                    </div>
                                </div>

                                <div class="bg-yellow-500/15 border border-yellow-500/30 rounded-lg p-3 mb-3 text-xs text-yellow-100 space-y-1.5">
                                    <div class="text-yellow-400 font-bold text-[11px] uppercase tracking-wider mb-1.5">Before confirming, check:</div>
                                    <div class="flex items-start gap-1.5">
                                        <span class="text-yellow-500 mt-0.5">&#x2022;</span>
                                        <span>Are you sure this is the <strong class="text-yellow-300">same player</strong>? Does the nick in the demo belong to this record's player?</span>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <span class="text-yellow-500 mt-0.5">&#x2022;</span>
                                        <span>Is the demo time and record time <strong class="text-yellow-300">matching correctly</strong>? ±1ms rounding differences are <strong class="text-yellow-300">very rare</strong> but can occur - if times don't match exactly, prefer <strong class="text-yellow-300">Not Sure</strong> over a wrong assignment.</span>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <span class="text-yellow-500 mt-0.5">&#x2022;</span>
                                        <span>If unsure, press <strong class="text-yellow-300">Cancel</strong> and use <strong class="text-yellow-300">Not Sure</strong> instead.</span>
                                    </div>
                                </div>

                                <div class="flex gap-2 mt-auto">
                                    <button @click="confirmAssign" :disabled="assigning"
                                        class="flex-1 py-2.5 bg-green-600 hover:bg-green-500 disabled:bg-green-800 disabled:text-green-400 text-white rounded-lg font-bold text-sm transition-colors">
                                        {{ assigning ? 'Assigning...' : 'Confirm' }}
                                    </button>
                                    <button @click="cancelConfirm"
                                        class="flex-1 py-2.5 bg-red-600/80 hover:bg-red-500 text-white rounded-lg font-bold text-sm transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </Transition>

                        <!-- ── Card content ── -->
                        <!-- Map thumbnail -->
                        <div class="relative h-28 overflow-hidden">
                            <img v-if="task.map_thumbnail"
                                :src="'/storage/' + task.map_thumbnail"
                                class="w-full h-full object-cover"
                                :alt="task.demo.map_name" />
                            <div v-else class="w-full h-full bg-gray-700/50 flex items-center justify-center">
                                <span class="text-gray-600 text-sm">No thumbnail</span>
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/20 to-transparent" />
                            <!-- Items overlay -->
                            <div class="absolute bottom-2 right-2 flex flex-col gap-0.5">
                                <div v-if="task.map_weapons" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                    <div v-for="w in task.map_weapons.split(',')" :key="w" :title="w" :class="`sprite-items sprite-${w} w-3 h-3`"></div>
                                </div>
                                <div v-if="task.map_items" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                    <div v-for="i in task.map_items.split(',')" :key="i" :title="i" :class="`sprite-items sprite-${i} w-3 h-3`"></div>
                                </div>
                                <div v-if="task.map_functions" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                    <div v-for="f in task.map_functions.split(',')" :key="f" :title="f" :class="`sprite-items sprite-${f} w-3 h-3`"></div>
                                </div>
                            </div>
                            <div class="absolute bottom-0 left-0 p-2.5 flex items-end gap-2">
                                <a :href="'/maps/' + encodeURIComponent(task.demo.map_name)" target="_blank"
                                    class="text-white font-bold text-sm leading-tight hover:text-blue-400 transition-colors">{{ task.demo.map_name }}</a>
                                <span class="px-1.5 py-0.5 text-[10px] font-bold rounded flex-shrink-0"
                                    :class="task.demo.physics === 'CPM' ? 'bg-purple-500/20 text-purple-400' : 'bg-blue-500/20 text-blue-400'">
                                    {{ task.demo.physics }}
                                </span>
                            </div>
                        </div>

                        <!-- Demo info -->
                        <div class="px-3 py-2.5 border-b border-gray-700/30 flex items-center justify-between">
                            <span class="text-lg font-bold truncate" v-html="q3tohtml(task.demo.player_name || 'Unknown')"></span>
                            <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                <span class="text-white font-mono text-xl font-black">{{ formatTime(task.demo.time_ms) }}</span>
                                <a :href="'/demos/' + task.demo.id + '/download'" target="_blank" class="text-gray-500 hover:text-blue-400 transition-colors" title="Download demo"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>
                            </div>
                        </div>

                        <!-- Closest matches -->
                        <div class="px-2.5 pt-2">
                            <div class="text-[10px] font-semibold text-yellow-500/80 uppercase tracking-wider mb-1">
                                Closest matches
                            </div>
                            <div class="max-h-32 overflow-y-auto custom-scrollbar">
                                <button v-for="record in task.closest_matches" :key="'c-' + record.id"
                                    @click="selectRecord(task, record)"
                                    class="w-full flex items-center justify-between px-1.5 py-1 rounded text-[11px] transition-all group cursor-pointer"
                                    :class="selectedRecords[task.demo.id]?.id === record.id
                                        ? 'bg-blue-500/20 ring-1 ring-blue-400/50'
                                        : record.time_diff === 0
                                            ? 'bg-green-500/10 hover:bg-green-500/20 ring-1 ring-green-500/20'
                                            : 'hover:bg-white/5'">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="w-5 text-right flex-shrink-0" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : record.time_diff === 0 ? 'text-green-400' : 'text-gray-400'">#{{ record.rank }}</span>
                                        <span class="truncate transition-colors" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-300' : record.time_diff === 0 ? 'text-green-300' : 'text-gray-300 group-hover:text-white'" v-html="q3tohtml(record.player_name)"></span>
                                        <a v-if="record.demo_id" :href="'/demos/' + record.demo_id + '/download'" @click.stop target="_blank" class="flex-shrink-0 text-gray-600 hover:text-blue-400 transition-colors" title="Download demo"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>
                                        <a v-if="record.youtube_url" :href="record.youtube_url" @click.stop target="_blank" class="flex-shrink-0 text-gray-600 hover:text-red-400 transition-colors" title="Watch on YouTube"><svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0 ml-2">
                                        <span class="text-[9px] px-1 rounded"
                                            :class="record.time_diff === 0
                                                ? 'bg-green-500/20 text-green-400 font-bold'
                                                : record.time < task.demo.time_ms
                                                    ? 'text-green-400'
                                                    : 'text-red-400'">
                                            {{ formatSignedDiff(record.time, task.demo.time_ms) }}
                                        </span>
                                        <span class="font-mono" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : record.time_diff === 0 ? 'text-white font-semibold' : 'text-gray-300'">{{ formatTime(record.time) }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Separator -->
                        <div class="mx-2.5 border-t border-gray-700/20 my-1.5" />

                        <!-- All records -->
                        <div class="px-2.5 pb-2.5">
                            <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">
                                All records ({{ task.all_records.length }})
                            </div>
                            <div class="max-h-44 overflow-y-auto custom-scrollbar">
                                <button v-for="record in task.all_records" :key="'a-' + record.id"
                                    @click="selectRecord(task, record)"
                                    class="w-full flex items-center justify-between px-1.5 py-1 rounded text-[11px] transition-all group cursor-pointer"
                                    :class="selectedRecords[task.demo.id]?.id === record.id
                                        ? 'bg-blue-500/20 ring-1 ring-blue-400/50'
                                        : record.time_diff === 0
                                            ? 'bg-green-500/10 hover:bg-green-500/20 ring-1 ring-green-500/20'
                                            : 'hover:bg-white/5'">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="w-5 text-right flex-shrink-0" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : record.time_diff === 0 ? 'text-green-400' : 'text-gray-400'">#{{ record.rank }}</span>
                                        <span class="truncate transition-colors" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-300' : record.time_diff === 0 ? 'text-green-300' : 'text-gray-300 group-hover:text-white'" v-html="q3tohtml(record.player_name)"></span>
                                        <a v-if="record.demo_id" :href="'/demos/' + record.demo_id + '/download'" @click.stop target="_blank" class="flex-shrink-0 text-gray-600 hover:text-blue-400 transition-colors" title="Download demo"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>
                                        <a v-if="record.youtube_url" :href="record.youtube_url" @click.stop target="_blank" class="flex-shrink-0 text-gray-600 hover:text-red-400 transition-colors" title="Watch on YouTube"><svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0 ml-2">
                                        <span class="text-[9px]"
                                            :class="record.time_diff === 0
                                                ? 'bg-green-500/20 text-green-400 font-bold px-1 rounded'
                                                : record.time < task.demo.time_ms
                                                    ? 'text-green-400'
                                                    : 'text-red-400'">
                                            {{ formatSignedDiff(record.time, task.demo.time_ms) }}
                                        </span>
                                        <span class="font-mono" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : record.time_diff === 0 ? 'text-white font-semibold' : 'text-gray-300'">{{ formatTime(record.time) }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- ── Bottom action buttons ── -->
                        <div class="px-2.5 pb-2.5 pt-1.5 border-t border-gray-700/30 mt-1">
                            <!-- Hint message -->
                            <Transition name="hint-fade">
                                <div v-if="assignHints[task.demo.id]" class="text-[10px] text-yellow-400/80 text-center mb-1.5">
                                    {{ assignHints[task.demo.id] }}
                                </div>
                            </Transition>
                            <!-- Selected record preview -->
                            <div v-if="selectedRecords[task.demo.id]" class="text-[10px] text-blue-400/70 text-center mb-1.5 truncate">
                                Selected: #{{ selectedRecords[task.demo.id].rank }}
                                <span v-html="q3tohtml(selectedRecords[task.demo.id].player_name)"></span>
                                - {{ formatTime(selectedRecords[task.demo.id].time) }}
                            </div>
                            <div class="flex gap-1.5">
                                <button @click="tryAssign(task)"
                                    class="flex-1 py-1.5 rounded-lg text-xs font-bold transition-all"
                                    :class="selectedRecords[task.demo.id]
                                        ? 'bg-green-600 hover:bg-green-500 text-white'
                                        : 'bg-green-600/30 text-green-400/60 hover:bg-green-600/40'">
                                    Assign
                                </button>
                                <button @click="skipTask(task, 'not_sure')"
                                    class="flex-1 py-1.5 bg-yellow-600/20 hover:bg-yellow-600/30 text-yellow-400/70 hover:text-yellow-400 rounded-lg text-xs transition-all">
                                    Not Sure
                                </button>
                                <button @click="skipTask(task, 'no_match')"
                                    class="flex-1 py-1.5 rounded-lg text-xs transition-all"
                                    :class="!hasExactMatch(task) && !selectedRecords[task.demo.id]
                                        ? 'bg-red-600/40 text-red-300 hover:bg-red-600/50 font-bold animate-pulse-soft'
                                        : 'bg-gray-700/50 hover:bg-gray-700/70 text-gray-500 hover:text-gray-400'">
                                    No Match
                                </button>
                            </div>
                        </div>
                    </div>
            </div>
        </div>

        <!-- ═══ VERIFICATION PHASE ═══ -->
        <div v-if="phase === 'verification'" class="max-w-lg mx-auto px-4 md:px-6 lg:px-8 pb-8">
            <div v-if="!currentTask" class="text-center py-16 text-gray-500">
                {{ loadingMore ? 'Loading more tasks...' : 'No demos to verify.' }}
            </div>

            <div v-if="currentTask" v-for="task in [currentTask]" :key="task.demo.id">
                    <div
                        class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-emerald-700/30 relative transition-all duration-300"
                        :class="completingSlot === task.demo.id ? 'scale-95 opacity-0' : ''"
                    >

                        <!-- Confirm overlay (for Better Match) -->
                        <Transition name="confirm-slide">
                            <div v-if="confirmingTask?.demo.id === task.demo.id"
                                class="absolute inset-0 z-20 bg-gray-900 flex flex-col p-4 rounded-xl overflow-y-auto">
                                <div class="text-sm font-bold text-gray-400 mb-3 text-center">Confirm Better Match</div>

                                <div class="bg-gray-800/80 rounded-lg p-3 mb-2">
                                    <div class="text-[10px] text-gray-600 uppercase tracking-wider">Demo</div>
                                    <div class="text-lg font-bold" v-html="q3tohtml(task.demo.player_name || 'Unknown')"></div>
                                    <div class="text-white font-mono text-xl font-black mt-0.5">{{ formatTime(task.demo.time_ms) }}</div>
                                </div>

                                <div class="text-center text-gray-600 text-lg my-1">&#x2193;</div>

                                <div class="bg-gray-800/80 rounded-lg p-3 mb-3">
                                    <div class="text-[10px] text-gray-600 uppercase tracking-wider">New Record #{{ confirmingRecord.rank }}</div>
                                    <div class="text-lg font-bold" v-html="q3tohtml(confirmingRecord.player_name || 'Unknown')"></div>
                                    <div class="font-mono text-xl font-black mt-0.5" :class="confirmingRecord.time_diff === 0 ? 'text-green-400' : 'text-white'">{{ formatTime(confirmingRecord.time) }}</div>
                                </div>

                                <div class="flex gap-2 mt-auto">
                                    <button @click="confirmAssign" :disabled="assigning"
                                        class="flex-1 py-2.5 bg-green-600 hover:bg-green-500 disabled:bg-green-800 disabled:text-green-400 text-white rounded-lg font-bold text-sm transition-colors">
                                        {{ assigning ? 'Saving...' : 'Confirm' }}
                                    </button>
                                    <button @click="cancelConfirm"
                                        class="flex-1 py-2.5 bg-red-600/80 hover:bg-red-500 text-white rounded-lg font-bold text-sm transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </Transition>

                        <!-- Verification badge -->
                        <div class="absolute top-2 right-2 z-10 px-2 py-0.5 bg-emerald-500/20 text-emerald-400 text-[10px] font-bold rounded-full border border-emerald-500/30">
                            VERIFY
                        </div>

                        <!-- Map thumbnail -->
                        <div class="relative h-28 overflow-hidden">
                            <img v-if="task.map_thumbnail"
                                :src="'/storage/' + task.map_thumbnail"
                                class="w-full h-full object-cover"
                                :alt="task.demo.map_name" />
                            <div v-else class="w-full h-full bg-gray-700/50 flex items-center justify-center">
                                <span class="text-gray-600 text-sm">No thumbnail</span>
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/20 to-transparent" />
                            <!-- Items overlay -->
                            <div class="absolute bottom-2 right-2 flex flex-col gap-0.5">
                                <div v-if="task.map_weapons" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                    <div v-for="w in task.map_weapons.split(',')" :key="w" :title="w" :class="`sprite-items sprite-${w} w-3 h-3`"></div>
                                </div>
                                <div v-if="task.map_items" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                    <div v-for="i in task.map_items.split(',')" :key="i" :title="i" :class="`sprite-items sprite-${i} w-3 h-3`"></div>
                                </div>
                                <div v-if="task.map_functions" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                    <div v-for="f in task.map_functions.split(',')" :key="f" :title="f" :class="`sprite-items sprite-${f} w-3 h-3`"></div>
                                </div>
                            </div>
                            <div class="absolute bottom-0 left-0 p-2.5 flex items-end gap-2">
                                <a :href="'/maps/' + encodeURIComponent(task.demo.map_name)" target="_blank"
                                    class="text-white font-bold text-sm leading-tight hover:text-blue-400 transition-colors">{{ task.demo.map_name }}</a>
                                <span class="px-1.5 py-0.5 text-[10px] font-bold rounded flex-shrink-0"
                                    :class="task.demo.physics === 'CPM' ? 'bg-purple-500/20 text-purple-400' : 'bg-blue-500/20 text-blue-400'">
                                    {{ task.demo.physics }}
                                </span>
                            </div>
                        </div>

                        <!-- Demo info -->
                        <div class="px-3 py-2.5 border-b border-gray-700/30 flex items-center justify-between">
                            <span class="text-lg font-bold truncate" v-html="q3tohtml(task.demo.player_name || 'Unknown')"></span>
                            <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                <span class="text-white font-mono text-xl font-black">{{ formatTime(task.demo.time_ms) }}</span>
                                <a :href="'/demos/' + task.demo.id + '/download'" target="_blank" class="text-gray-500 hover:text-blue-400 transition-colors" title="Download demo"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>
                            </div>
                        </div>

                        <!-- Current assignment highlight -->
                        <div v-if="task.current_record" class="px-3 py-2 bg-emerald-500/10 border-b border-emerald-500/20">
                            <div class="text-[10px] text-emerald-400/70 uppercase tracking-wider mb-1">Currently assigned to</div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-emerald-400 text-xs font-bold">#{{ task.current_record.rank }}</span>
                                    <span class="text-sm font-semibold" v-html="q3tohtml(task.current_record.player_name)"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px]"
                                        :class="task.current_record.time_diff === 0
                                            ? 'text-green-400 font-bold'
                                            : task.current_record.time < task.demo.time_ms
                                                ? 'text-green-400'
                                                : 'text-red-400'">
                                        {{ formatSignedDiff(task.current_record.time, task.demo.time_ms) }}
                                    </span>
                                    <span class="text-white font-mono text-sm font-bold">{{ formatTime(task.current_record.time) }}</span>
                                    <a v-if="task.current_record.youtube_url" :href="task.current_record.youtube_url" target="_blank" class="text-gray-500 hover:text-red-400 transition-colors" title="Watch on YouTube"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>
                                </div>
                            </div>
                        </div>

                        <!-- Closest matches (for better match selection) -->
                        <div class="px-2.5 pt-2">
                            <div class="text-[10px] font-semibold text-yellow-500/80 uppercase tracking-wider mb-1">
                                Closest matches
                            </div>
                            <div class="max-h-28 overflow-y-auto custom-scrollbar">
                                <button v-for="record in task.closest_matches" :key="'vc-' + record.id"
                                    @click="selectRecord(task, record)"
                                    class="w-full flex items-center justify-between px-1.5 py-1 rounded text-[11px] transition-all group cursor-pointer"
                                    :class="selectedRecords[task.demo.id]?.id === record.id
                                        ? 'bg-blue-500/20 ring-1 ring-blue-400/50'
                                        : task.current_record?.id === record.id
                                            ? 'bg-emerald-500/15 ring-1 ring-emerald-400/30'
                                            : record.time_diff === 0
                                                ? 'bg-green-500/10 hover:bg-green-500/20 ring-1 ring-green-500/20'
                                                : 'hover:bg-white/5'">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="w-5 text-right flex-shrink-0" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : task.current_record?.id === record.id ? 'text-emerald-400' : record.time_diff === 0 ? 'text-green-400' : 'text-gray-400'">#{{ record.rank }}</span>
                                        <span class="truncate transition-colors" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-300' : task.current_record?.id === record.id ? 'text-emerald-300' : record.time_diff === 0 ? 'text-green-300' : 'text-gray-300 group-hover:text-white'" v-html="q3tohtml(record.player_name)"></span>
                                        <span v-if="task.current_record?.id === record.id" class="text-[8px] bg-emerald-500/20 text-emerald-400 px-1 rounded font-bold flex-shrink-0">MATCHED</span>
                                        <a v-if="record.demo_id" :href="'/demos/' + record.demo_id + '/download'" @click.stop target="_blank" class="flex-shrink-0 text-gray-600 hover:text-blue-400 transition-colors" title="Download demo"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>
                                        <a v-if="record.youtube_url" :href="record.youtube_url" @click.stop target="_blank" class="flex-shrink-0 text-gray-600 hover:text-red-400 transition-colors" title="Watch on YouTube"><svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0 ml-2">
                                        <span class="text-[9px] px-1 rounded"
                                            :class="record.time_diff === 0
                                                ? 'bg-green-500/20 text-green-400 font-bold'
                                                : record.time < task.demo.time_ms
                                                    ? 'text-green-400'
                                                    : 'text-red-400'">
                                            {{ formatSignedDiff(record.time, task.demo.time_ms) }}
                                        </span>
                                        <span class="font-mono" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : task.current_record?.id === record.id ? 'text-emerald-300' : 'text-gray-500'">{{ formatTime(record.time) }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Separator -->
                        <div class="mx-2.5 border-t border-gray-700/20 my-1.5" />

                        <!-- All records -->
                        <div class="px-2.5 pb-2.5">
                            <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">
                                All records ({{ task.all_records.length }})
                            </div>
                            <div class="max-h-44 overflow-y-auto custom-scrollbar">
                                <button v-for="record in task.all_records" :key="'va-' + record.id"
                                    @click="selectRecord(task, record)"
                                    class="w-full flex items-center justify-between px-1.5 py-1 rounded text-[11px] transition-all group cursor-pointer"
                                    :class="selectedRecords[task.demo.id]?.id === record.id
                                        ? 'bg-blue-500/20 ring-1 ring-blue-400/50'
                                        : task.current_record?.id === record.id
                                            ? 'bg-emerald-500/15 ring-1 ring-emerald-400/30'
                                            : record.time_diff === 0
                                                ? 'bg-green-500/10 hover:bg-green-500/20 ring-1 ring-green-500/20'
                                                : 'hover:bg-white/5'">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="w-5 text-right flex-shrink-0" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : task.current_record?.id === record.id ? 'text-emerald-400' : record.time_diff === 0 ? 'text-green-400' : 'text-gray-400'">#{{ record.rank }}</span>
                                        <span class="truncate transition-colors" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-300' : task.current_record?.id === record.id ? 'text-emerald-300' : record.time_diff === 0 ? 'text-green-300' : 'text-gray-300 group-hover:text-white'" v-html="q3tohtml(record.player_name)"></span>
                                        <span v-if="task.current_record?.id === record.id" class="text-[8px] bg-emerald-500/20 text-emerald-400 px-1 rounded font-bold flex-shrink-0">MATCHED</span>
                                        <a v-if="record.demo_id" :href="'/demos/' + record.demo_id + '/download'" @click.stop target="_blank" class="flex-shrink-0 text-gray-600 hover:text-blue-400 transition-colors" title="Download demo"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>
                                        <a v-if="record.youtube_url" :href="record.youtube_url" @click.stop target="_blank" class="flex-shrink-0 text-gray-600 hover:text-red-400 transition-colors" title="Watch on YouTube"><svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0 ml-2">
                                        <span class="text-[9px]"
                                            :class="record.time_diff === 0
                                                ? 'bg-green-500/20 text-green-400 font-bold px-1 rounded'
                                                : record.time < task.demo.time_ms
                                                    ? 'text-green-400'
                                                    : 'text-red-400'">
                                            {{ formatSignedDiff(record.time, task.demo.time_ms) }}
                                        </span>
                                        <span class="font-mono" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : record.time_diff === 0 ? 'text-white font-semibold' : 'text-gray-300'">{{ formatTime(record.time) }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Bottom action buttons -->
                        <div class="px-2.5 pb-2.5 pt-1.5 border-t border-gray-700/30 mt-1">
                            <Transition name="hint-fade">
                                <div v-if="assignHints[task.demo.id]" class="text-[10px] text-yellow-400/80 text-center mb-1.5">
                                    {{ assignHints[task.demo.id] }}
                                </div>
                            </Transition>
                            <div v-if="selectedRecords[task.demo.id]" class="text-[10px] text-blue-400/70 text-center mb-1.5 truncate">
                                Selected: #{{ selectedRecords[task.demo.id].rank }}
                                <span v-html="q3tohtml(selectedRecords[task.demo.id].player_name)"></span>
                                - {{ formatTime(selectedRecords[task.demo.id].time) }}
                            </div>
                            <div class="flex gap-1.5">
                                <button @click="confirmCorrect(task)"
                                    class="flex-1 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition-all">
                                    Correct
                                </button>
                                <button @click="voteUnassign(task)"
                                    class="flex-1 py-1.5 bg-red-600/30 hover:bg-red-600/40 text-red-400/80 hover:text-red-400 rounded-lg text-xs font-bold transition-all">
                                    Unassign
                                </button>
                                <button @click="tryBetterMatch(task)"
                                    class="flex-1 py-1.5 rounded-lg text-xs font-bold transition-all"
                                    :class="selectedRecords[task.demo.id]
                                        ? 'bg-blue-600 hover:bg-blue-500 text-white'
                                        : 'bg-blue-600/30 text-blue-400/60 hover:bg-blue-600/40'">
                                    Better Match
                                </button>
                            </div>
                        </div>
                    </div>
            </div>
        </div>

        <!-- ═══ RATING PHASE ═══ -->
        <div v-if="phase === 'rating'" class="max-w-6xl mx-auto px-4 md:px-6 lg:px-8 pb-8">
            <div v-if="!currentRating" class="text-center py-16 text-gray-500">
                {{ loadingMore ? 'Loading more tasks...' : 'No maps available for rating.' }}
            </div>

            <div v-if="currentRating" v-for="map in [currentRating]" :key="map.id" class="flex gap-4">
                    <!-- Left: Tips -->
                    <div class="w-56 flex-shrink-0">
                        <div class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-purple-500/20 p-3 space-y-3 text-xs text-gray-400">
                            <div class="text-purple-400 font-bold text-[11px] uppercase tracking-wider">Rating Tips</div>
                            <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-2 text-yellow-200/90 text-[11px]">
                                Rate based on the <strong class="text-yellow-300">lowest skill needed to complete</strong> the map, not the WR time. A beginner map is still beginner even if the WR is insanely optimized.
                            </div>
                            <div class="space-y-2">
                                <p>Rate based on <strong class="text-gray-300">your experience</strong> playing the map, not just how it looks.</p>
                                <p>Not sure? Check the <strong class="text-gray-300">YouTube video</strong> on the right to see actual gameplay and judge the difficulty.</p>
                                <p>No video available? Use <strong class="text-gray-300">Skip + Request Render</strong> to queue a render of the best demo - the next person (or you) will have a video to help decide.</p>
                                <p>When in doubt, <strong class="text-gray-300">Skip</strong> is always fine - you still earn a point!</p>
                            </div>
                        </div>
                    </div>

                    <!-- Center: Map card -->
                    <div class="flex-1 min-w-0">
                    <div
                        class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-gray-700/40 overflow-hidden transition-all duration-500 relative">
                        <!-- Thumbnail -->
                        <div class="relative">
                            <img v-if="map.thumbnail"
                                :src="'/storage/' + map.thumbnail"
                                class="w-full aspect-[4/3] object-cover bg-black"
                                :alt="map.name" />
                            <div v-else class="w-full aspect-[4/3] bg-gray-700/50 flex items-center justify-center">
                                <span class="text-gray-600 text-sm">No image</span>
                            </div>
                            <!-- Items overlay -->
                            <div class="absolute bottom-1 right-1 flex flex-col gap-0.5">
                                <div v-if="map.weapons" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                    <div v-for="w in map.weapons.split(',')" :key="w" :title="w" :class="`sprite-items sprite-${w} w-3 h-3`"></div>
                                </div>
                                <div v-if="map.items" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                    <div v-for="i in map.items.split(',')" :key="i" :title="i" :class="`sprite-items sprite-${i} w-3 h-3`"></div>
                                </div>
                                <div v-if="map.functions" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                    <div v-for="f in map.functions.split(',')" :key="f" :title="f" :class="`sprite-items sprite-${f} w-3 h-3`"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Map name + difficulty buttons -->
                        <div class="p-3">
                            <a :href="'/maps/' + encodeURIComponent(map.name)" target="_blank"
                                class="text-white text-sm font-bold truncate block mb-2 hover:text-blue-400 transition-colors">{{ map.name }}</a>
                            <div class="flex gap-1.5">
                                <div v-for="d in difficultyLabels" :key="d.level" class="flex-1 relative group/btn">
                                    <button
                                        @click="rateMap(map, d.level)"
                                        :disabled="ratedMapIds.has(map.id) || ratingSubmitting === map.id"
                                        class="w-full py-1.5 rounded text-[11px] font-bold transition-all border"
                                        :class="ratedMapIds.has(map.id) && ratedValues[map.id] === d.level
                                            ? d.color + ' text-white ring-1 ' + d.ring + ' border-transparent'
                                            : ratedMapIds.has(map.id)
                                                ? 'bg-gray-800 text-gray-700 border-transparent cursor-default'
                                                : d.bgSubtle + ' border-gray-700/30 hover:text-white ' + d.hover + ' cursor-pointer'">
                                        {{ d.label }}
                                    </button>
                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 px-2.5 py-1.5 bg-gray-900 border border-gray-700 rounded-lg text-[10px] text-gray-300 shadow-xl opacity-0 pointer-events-none group-hover/btn:opacity-100 transition-opacity z-50 whitespace-nowrap">
                                        {{ d.desc }}
                                    </div>
                                </div>
                            </div>
                            <button v-if="!ratedMapIds.has(map.id)"
                                @click="skipRating(map)"
                                class="w-full mt-1.5 py-1.5 bg-gray-700/50 hover:bg-gray-700/70 text-gray-400 hover:text-gray-300 rounded text-xs font-medium transition-all border border-gray-600/30">
                                Skip
                            </button>
                            <button v-if="!ratedMapIds.has(map.id) && !(map.videos?.vq3?.length || map.videos?.cpm?.length) && map.has_demos"
                                @click="requestRenderAndSkip(map)"
                                :disabled="renderRequesting === map.id"
                                class="w-full mt-1.5 py-2 bg-red-600/30 hover:bg-red-600/50 text-red-300 hover:text-red-200 rounded text-xs font-bold transition-all border border-red-500/30 flex items-center justify-center gap-1.5">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2c-.3-1-1-1.8-2-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.5.6c-1 .3-1.8 1.1-2 2.1C0 8.1 0 12 0 12s0 3.9.5 5.8c.3 1 1 1.8 2 2.1 1.9.6 9.5.6 9.5.6s7.6 0 9.5-.6c1-.3 1.8-1.1 2-2.1.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.5 15.6V8.4l6.3 3.6-6.3 3.6z"/></svg>
                                {{ renderRequesting === map.id ? 'Requesting...' : 'Skip + Request Render' }}
                            </button>
                        </div>
                    </div>
                    </div>

                    <!-- Right: YouTube embed videos -->
                    <div class="w-80 flex-shrink-0 space-y-3">
                        <template v-if="map.videos && (map.videos.vq3?.length || map.videos.cpm?.length)">
                            <template v-for="phys in ['vq3', 'cpm']" :key="phys">
                                <div v-if="map.videos[phys]?.length" class="space-y-2">
                                    <div class="text-xs font-bold uppercase tracking-wider"
                                        :class="phys === 'cpm' ? 'text-purple-400' : 'text-blue-400'">{{ phys.toUpperCase() }} Videos</div>
                                    <div v-for="(vid, idx) in map.videos[phys]" :key="idx" class="rounded-lg overflow-hidden border border-gray-700/40">
                                        <iframe v-if="vid.youtube_video_id"
                                            :src="`https://www.youtube.com/embed/${vid.youtube_video_id}`"
                                            class="w-full aspect-video"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen></iframe>
                                        <div class="bg-gray-800/80 px-2 py-1 text-[10px] text-gray-400">
                                            {{ vid.player_name || 'Unknown' }} - {{ formatTime(vid.time_ms) }}
                                            <span v-if="vid.gametype && vid.gametype !== 'run'" class="text-yellow-500/70 ml-1">{{ vid.gametype }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                        <div v-else class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-gray-700/40 p-4 text-center">
                            <svg class="w-8 h-8 text-gray-600 mx-auto mb-2" viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2c-.3-1-1-1.8-2-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.5.6c-1 .3-1.8 1.1-2 2.1C0 8.1 0 12 0 12s0 3.9.5 5.8c.3 1 1 1.8 2 2.1 1.9.6 9.5.6 9.5.6s7.6 0 9.5-.6c1-.3 1.8-1.1 2-2.1.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.5 15.6V8.4l6.3 3.6-6.3 3.6z"/></svg>
                            <div class="text-xs text-gray-500 mb-2">No videos available for this map</div>
                            <div class="text-[10px] text-gray-600">Use "Skip + Request Render" to queue a video for future ratings</div>
                        </div>
                    </div>
            </div>
        </div>

        <!-- ═══ TAGGING PHASE ═══ -->
        <div v-if="phase === 'tagging'" class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-8">
            <div v-if="!currentTagMap" class="text-center py-16 text-gray-500">
                {{ loadingMore ? 'Loading more tasks...' : 'No maps available for tagging.' }}
            </div>

            <div v-if="currentTagMap" :key="currentTagMap.id">
                <!-- Top row: Tips left, Map center, Videos right -->
                <div class="flex gap-4 mb-4 max-w-6xl mx-auto">
                    <!-- Left: Tips -->
                    <div class="w-56 flex-shrink-0">
                        <div class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-amber-500/20 p-3 space-y-3 text-xs text-gray-400">
                            <div class="text-amber-400 font-bold text-[11px] uppercase tracking-wider">Tagging Tips</div>
                            <div class="space-y-2">
                                <p>Add tags that describe the map's <strong class="text-gray-300">gameplay style</strong>, mechanics, and theme.</p>
                                <p>Not sure what the map plays like? Check the <strong class="text-gray-300">YouTube video</strong> on the right to see actual gameplay.</p>
                                <p>No video available? Use <strong class="text-gray-300">Skip + Request Render</strong> to queue a render - future taggers (or you) will have a video to help.</p>
                                <p>Look at the <strong class="text-gray-300">weapons and items</strong> on the thumbnail for clues about the map's mechanics.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Center: Map card -->
                    <div class="flex-1 min-w-0">
                        <div class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-amber-700/30 overflow-hidden">
                            <!-- Thumbnail -->
                            <div class="relative">
                                <img v-if="currentTagMap.thumbnail"
                                    :src="'/storage/' + currentTagMap.thumbnail"
                                    class="w-full aspect-[16/9] object-cover bg-black"
                                    :alt="currentTagMap.name" />
                                <div v-else class="w-full aspect-[16/9] bg-gray-700/50 flex items-center justify-center">
                                    <span class="text-gray-600 text-sm">No image</span>
                                </div>
                                <div class="absolute bottom-1 right-1 flex flex-col gap-0.5">
                                    <div v-if="currentTagMap.weapons" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                        <div v-for="w in currentTagMap.weapons.split(',')" :key="w" :title="w" :class="`sprite-items sprite-${w} w-3 h-3`"></div>
                                    </div>
                                    <div v-if="currentTagMap.items" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                        <div v-for="i in currentTagMap.items.split(',')" :key="i" :title="i" :class="`sprite-items sprite-${i} w-3 h-3`"></div>
                                    </div>
                                    <div v-if="currentTagMap.functions" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                                        <div v-for="f in currentTagMap.functions.split(',')" :key="f" :title="f" :class="`sprite-items sprite-${f} w-3 h-3`"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Map name + existing tags + skip -->
                            <div class="p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <a :href="'/maps/' + encodeURIComponent(currentTagMap.name)" target="_blank"
                                        class="text-lg font-bold text-white hover:text-blue-400 transition-colors">{{ currentTagMap.name }}</a>
                                    <button @click="skipTag"
                                        class="px-6 py-2.5 rounded-lg text-sm font-bold transition-all"
                                        :class="taggedCurrentMap
                                            ? 'bg-green-600 hover:bg-green-500 text-white'
                                            : 'bg-gray-700 hover:bg-gray-600 text-gray-300 border border-gray-600'">
                                        {{ taggedCurrentMap ? 'Next Map' : 'Skip' }}
                                    </button>
                                </div>
                                <div v-if="currentTagMap.tags.length > 0" class="flex flex-wrap gap-1.5">
                                    <span v-for="tag in currentTagMap.tags" :key="tag.id"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                        {{ tag.display_name }}
                                    </span>
                                </div>
                                <div v-else class="text-xs text-gray-600">No tags yet - be the first!</div>
                                <!-- Request render button when no videos -->
                                <button v-if="!(currentTagMap.videos?.vq3?.length || currentTagMap.videos?.cpm?.length) && currentTagMap.has_demos"
                                    @click="requestTagRenderAndSkip(currentTagMap)"
                                    :disabled="renderRequesting === currentTagMap.id"
                                    class="w-full mt-2 py-2 bg-red-600/30 hover:bg-red-600/50 text-red-300 hover:text-red-200 rounded text-xs font-bold transition-all border border-red-500/30 flex items-center justify-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2c-.3-1-1-1.8-2-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.5.6c-1 .3-1.8 1.1-2 2.1C0 8.1 0 12 0 12s0 3.9.5 5.8c.3 1 1 1.8 2 2.1 1.9.6 9.5.6 9.5.6s7.6 0 9.5-.6c1-.3 1.8-1.1 2-2.1.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.5 15.6V8.4l6.3 3.6-6.3 3.6z"/></svg>
                                    {{ renderRequesting === currentTagMap.id ? 'Requesting...' : 'Skip + Request Render' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: YouTube embed videos -->
                    <div class="w-80 flex-shrink-0 space-y-3">
                        <template v-if="currentTagMap.videos && (currentTagMap.videos.vq3?.length || currentTagMap.videos.cpm?.length)">
                            <template v-for="phys in ['vq3', 'cpm']" :key="phys">
                                <div v-if="currentTagMap.videos[phys]?.length" class="space-y-2">
                                    <div class="text-xs font-bold uppercase tracking-wider"
                                        :class="phys === 'cpm' ? 'text-purple-400' : 'text-blue-400'">{{ phys.toUpperCase() }} Videos</div>
                                    <div v-for="(vid, idx) in currentTagMap.videos[phys]" :key="idx" class="rounded-lg overflow-hidden border border-gray-700/40">
                                        <iframe v-if="vid.youtube_video_id"
                                            :src="`https://www.youtube.com/embed/${vid.youtube_video_id}`"
                                            class="w-full aspect-video"
                                            frameborder="0"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen></iframe>
                                        <div class="bg-gray-800/80 px-2 py-1 text-[10px] text-gray-400">
                                            {{ vid.player_name || 'Unknown' }} - {{ formatTime(vid.time_ms) }}
                                            <span v-if="vid.gametype && vid.gametype !== 'run'" class="text-yellow-500/70 ml-1">{{ vid.gametype }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                        <div v-else class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-gray-700/40 p-4 text-center">
                            <svg class="w-8 h-8 text-gray-600 mx-auto mb-2" viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2c-.3-1-1-1.8-2-2.1C19.6 3.5 12 3.5 12 3.5s-7.6 0-9.5.6c-1 .3-1.8 1.1-2 2.1C0 8.1 0 12 0 12s0 3.9.5 5.8c.3 1 1 1.8 2 2.1 1.9.6 9.5.6 9.5.6s7.6 0 9.5-.6c1-.3 1.8-1.1 2-2.1.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.5 15.6V8.4l6.3 3.6-6.3 3.6z"/></svg>
                            <div class="text-xs text-gray-500 mb-2">No videos available for this map</div>
                            <div class="text-[10px] text-gray-600">Use "Skip + Request Render" to queue a video for future taggers</div>
                        </div>
                    </div>
                </div>

                <!-- Search input + Add button -->
                <div class="mb-3 flex gap-2">
                    <input v-model="tagInput"
                        @input="filterTagSuggestions"
                        @keydown.enter.prevent="tagInput.trim() && addTagToMap(tagInput.trim())"
                        class="flex-1 px-4 py-2.5 bg-gray-800/60 border border-gray-700/50 rounded-lg text-sm text-white placeholder-gray-500 focus:border-amber-500/50 focus:outline-none"
                        placeholder="Search tags or type new tag name..." />
                    <button @click="tagInput.trim() && addTagToMap(tagInput.trim())"
                        :disabled="!tagInput.trim() || addingTag"
                        class="px-4 py-2.5 bg-amber-600 hover:bg-amber-500 disabled:bg-gray-700 disabled:text-gray-500 text-white font-bold rounded-lg text-sm transition-colors flex-shrink-0">
                        Add
                    </button>
                </div>

                <!-- ALL available tags - full width, no limit -->
                <div class="flex flex-wrap gap-2">
                    <button v-for="tag in tagSuggestions" :key="tag.id"
                        @click="addTagToMap(tag.display_name)"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-800/60 text-gray-300 border border-gray-700/40 hover:bg-amber-500/20 hover:text-amber-300 hover:border-amber-500/40 transition-all cursor-pointer">
                        {{ tag.display_name }}
                    </button>
                    <div v-if="tagSuggestions.length === 0 && allTags.length > 0" class="text-sm text-gray-600">No matching tags. Press Enter to create "{{ tagInput }}"</div>
                    <div v-if="allTags.length === 0" class="text-sm text-gray-600">Loading tags...</div>
                </div>
            </div>
        </div>

        <!-- ═══ SUMMARY ═══ -->
        <div v-if="phase === 'summary'" class="max-w-lg mx-auto px-4 py-16 text-center">
            <div class="bg-gray-800/40 backdrop-blur-sm rounded-2xl border border-gray-700/30 p-8">
                <div class="text-5xl mb-4" style="animation: pointPop 0.8s ease-out">&#x1F389;</div>
                <h2 class="text-3xl font-black text-white mb-2">Session Complete!</h2>
                <div class="text-5xl font-black text-yellow-400 mb-3 tabular-nums"
                    style="animation: pointPop 1s ease-out 0.3s both">
                    +{{ sessionPoints }} pts
                </div>
                <div class="text-gray-500 text-sm mb-6">
                    {{ completedAssignments }} assigned - {{ completedVerifications }} verified - {{ completedRatings }} rated - {{ completedTags }} tagged
                </div>

                <!-- Tier display -->
                <div v-if="currentTier" class="mb-6">
                    <div class="text-xs text-gray-600 uppercase tracking-wider mb-1">Current Tier</div>
                    <div class="text-lg font-bold" :style="{ color: currentTier.color }">
                        {{ currentTier.key.charAt(0).toUpperCase() + currentTier.key.slice(1) }} {{ currentTier.name }}
                    </div>
                    <div v-if="nextTier" class="mt-2">
                        <div class="h-1.5 bg-gray-700 rounded-full overflow-hidden max-w-xs mx-auto">
                            <div class="h-full rounded-full transition-all duration-1000"
                                :style="{ width: tierProgress + '%', background: currentTier.color }" />
                        </div>
                        <div class="text-[10px] text-gray-600 mt-1">
                            {{ Math.round(nextTier.min_score - badgeScore) }} pts to
                            <span :style="{ color: nextTier.color }">{{ nextTier.key.charAt(0).toUpperCase() + nextTier.key.slice(1) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-center">
                    <button @click="startNewSession"
                        class="px-6 py-2.5 bg-yellow-500 hover:bg-yellow-400 text-black font-bold rounded-xl transition-colors text-sm">
                        Play Again
                    </button>
                    <Link :href="route('community')"
                        class="px-6 py-2.5 bg-gray-700 hover:bg-gray-600 text-white rounded-xl transition-colors text-sm">
                        Leaderboard
                    </Link>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ LEADERBOARD MODAL ═══ -->
    <Transition name="modal">
        <div v-if="showLeaderboardModal" class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @click.self="showLeaderboardModal = false">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showLeaderboardModal = false" />
            <div class="relative bg-gray-900 border border-gray-700/50 rounded-2xl w-full max-w-lg max-h-[80vh] flex flex-col shadow-2xl">
                <!-- Modal header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-700/30">
                    <h3 class="text-lg font-bold text-white">Tasks Leaderboard</h3>
                    <button @click="showLeaderboardModal = false"
                        class="text-gray-500 hover:text-white transition-colors text-xl leading-none">&times;</button>
                </div>

                <!-- Scrollable content -->
                <div class="flex-1 overflow-y-auto custom-scrollbar px-5 py-3">
                    <div v-if="loadingLeaderboard" class="text-center py-8 text-gray-500">Loading...</div>
                    <div v-else-if="fullLeaderboard.length === 0" class="text-center py-8 text-gray-600">No sessions recorded yet.</div>
                    <div v-else class="space-y-1">
                        <div v-for="entry in fullLeaderboard" :key="entry.user_id"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors"
                            :class="entry.user_id === $page.props.auth?.user?.id ? 'bg-yellow-400/10 ring-1 ring-yellow-400/20' : 'hover:bg-white/5'">
                            <!-- Rank -->
                            <span class="w-8 text-right text-sm font-bold flex-shrink-0"
                                :class="entry.rank === 1 ? 'text-yellow-400' : entry.rank === 2 ? 'text-gray-400' : entry.rank === 3 ? 'text-amber-700' : 'text-gray-600'">
                                #{{ entry.rank }}
                            </span>
                            <!-- Player -->
                            <div class="flex-1 min-w-0">
                                <Link :href="'/profile/' + entry.user_id"
                                    class="text-sm truncate block hover:underline" v-html="q3tohtml(entry.name)"></Link>
                            </div>
                            <!-- Stats -->
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <div class="text-right">
                                    <div class="text-sm font-black text-yellow-400 tabular-nums">{{ entry.best_points }}</div>
                                    <div class="text-[9px] text-gray-600">best</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-400 tabular-nums">{{ entry.total_points }}</div>
                                    <div class="text-[9px] text-gray-600">total</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500 tabular-nums">{{ entry.total_sessions }}</div>
                                    <div class="text-[9px] text-gray-600">runs</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
/* Task card transitions */
.task-card-enter-active {
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}
.task-card-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.task-card-enter-from {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
}
.task-card-leave-to {
    opacity: 0;
    transform: translateY(-20px) scale(0.9);
}

/* Confirm slide */
.confirm-slide-enter-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.confirm-slide-leave-active {
    transition: all 0.2s ease-in;
}
.confirm-slide-enter-from {
    opacity: 0;
    transform: scale(0.95);
}
.confirm-slide-leave-to {
    opacity: 0;
    transform: scale(0.95);
}

/* Point splash */
.point-splash-enter-active {
    transition: all 0.3s ease-out;
}
.point-splash-leave-active {
    transition: all 0.8s ease-in;
}
.point-splash-enter-from {
    opacity: 0;
    transform: scale(0.5);
}
.point-splash-leave-to {
    opacity: 0;
    transform: scale(1.5) translateY(-40px);
}

/* Modal */
.modal-enter-active {
    transition: all 0.3s ease-out;
}
.modal-leave-active {
    transition: all 0.2s ease-in;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-from > div:last-child,
.modal-leave-to > div:last-child {
    transform: scale(0.95) translateY(10px);
}

/* Hint fade */
.hint-fade-enter-active {
    transition: all 0.2s ease-out;
}
.hint-fade-leave-active {
    transition: all 0.3s ease-in;
}
.hint-fade-enter-from,
.hint-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* Custom scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 3px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}

.animate-pulse-soft {
    animation: pulseSoft 2s ease-in-out infinite;
}

@keyframes pulseSoft {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

@keyframes pointPop {
    0% { transform: scale(0.5); opacity: 0; }
    50% { transform: scale(1.2); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}
</style>
