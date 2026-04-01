<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
    inheritAttrs: false,
}
</script>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick, getCurrentInstance } from 'vue';
import axios from 'axios';

const props = defineProps({
    assignmentTasks: Array,
    verificationTasks: Array,
    difficultyTasks: Array,
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
const phase = ref('assignment'); // 'assignment' | 'verification' | 'rating' | 'summary'
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

// Audio
let audioCtx = null;

// ─── Computed ──────────────────────────────────────────
const totalScore = computed(() => {
    return (props.communityScore?.total_score || 0) + sessionPoints.value;
});

const currentTier = computed(() => {
    if (!props.tiers) return null;
    let tier = null;
    const score = totalScore.value;
    for (const t of props.tiers) {
        if (score >= t.min_score) tier = t;
    }
    return tier;
});

const nextTier = computed(() => {
    if (!props.tiers) return null;
    const score = totalScore.value;
    for (const t of props.tiers) {
        if (score < t.min_score) return t;
    }
    return null;
});

const tierProgress = computed(() => {
    if (!currentTier.value && nextTier.value) {
        return Math.min((totalScore.value / nextTier.value.min_score) * 100, 100);
    }
    if (!nextTier.value) return 100;
    const current = currentTier.value?.min_score || 0;
    const next = nextTier.value.min_score;
    return Math.min(((totalScore.value - current) / (next - current)) * 100, 100);
});

const visibleAssignments = computed(() => {
    return assignmentQueue.value.slice(0, 3);
});

const visibleVerifications = computed(() => {
    return verificationQueue.value.slice(0, 3);
});

const visibleRatings = computed(() => {
    return ratingQueue.value.slice(0, 6);
});

const totalTasks = computed(() => {
    return props.assignmentTasks.length + (props.verificationTasks?.length || 0) + props.difficultyTasks.length;
});

const completedTasks = computed(() => {
    return completedAssignments.value + completedVerifications.value + completedRatings.value;
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
    if (idx !== -1) {
        completingSlot.value = demoId;
        await sleep(600);
        queue.value.splice(idx, 1);
        completingSlot.value = null;
    }

    // Phase transitions
    if (phase.value === 'assignment' && assignmentQueue.value.length === 0) {
        await sleep(800);
        if (verificationQueue.value.length > 0) {
            phase.value = 'verification';
        } else if (ratingQueue.value.length > 0) {
            phase.value = 'rating';
        } else {
            phase.value = 'summary';
            triggerFinalCelebration();
        }
    } else if (phase.value === 'verification' && verificationQueue.value.length === 0) {
        await sleep(800);
        if (ratingQueue.value.length > 0) {
            phase.value = 'rating';
        } else {
            phase.value = 'summary';
            triggerFinalCelebration();
        }
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
        const points = (props.weights?.difficulty_ratings || 1);
        awardPoints(points);

        // Remove from queue after delay
        await sleep(800);
        const idx = ratingQueue.value.findIndex(m => m.id === map.id);
        if (idx !== -1) {
            ratingQueue.value.splice(idx, 1);
        }

        // Check if rating phase is done
        if (ratingQueue.value.length === 0) {
            await sleep(600);
            phase.value = 'summary';
            triggerFinalCelebration();
        }
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

    const idx = ratingQueue.value.findIndex(m => m.id === map.id);
    if (idx !== -1) {
        ratingQueue.value.splice(idx, 1);
    }

    if (ratingQueue.value.length === 0) {
        await sleep(600);
        phase.value = 'summary';
        triggerFinalCelebration();
    }
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
    saveSession();
}

// ─── Session management ────────────────────────────────
async function saveSession() {
    if (sessionSaved.value || sessionPoints.value === 0) return;
    sessionSaved.value = true;

    try {
        const { data } = await axios.post('/community-tasks/save-session', {
            points: sessionPoints.value,
            assignments_completed: completedAssignments.value,
            ratings_completed: completedRatings.value,
        });
        personalBest.value = data.personalBest;
        leaderboardTop.value = data.leaderboard;
    } catch (err) {
        console.error('Failed to save session:', err);
        sessionSaved.value = false;
    }
}

async function startNewSession() {
    try {
        const { data } = await axios.post('/community-tasks/refresh');
        assignmentQueue.value = [...data.assignmentTasks];
        verificationQueue.value = [...(data.verificationTasks || [])];
        ratingQueue.value = [...data.difficultyTasks];
        completedAssignments.value = 0;
        completedVerifications.value = 0;
        completedRatings.value = 0;
        selectedRecords.value = {};
        ratedMapIds.value = new Set();
        ratedValues.value = {};
        sessionPoints.value = 0;
        streak.value = 0;
        sessionSaved.value = false;
        personalBest.value = data.personalBest;
        leaderboardTop.value = data.leaderboard;
        phase.value = data.assignmentTasks.length > 0 ? 'assignment'
            : (data.verificationTasks?.length > 0 ? 'verification'
            : (data.difficultyTasks.length > 0 ? 'rating' : 'summary'));
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

// ─── Lifecycle ─────────────────────────────────────────
onMounted(() => {
    totalPointsEver.value = props.communityScore?.total_score || 0;
    // Smart phase selection based on available tasks
    if (props.assignmentTasks.length === 0) {
        if (props.verificationTasks?.length > 0) {
            phase.value = 'verification';
        } else if (props.difficultyTasks.length > 0) {
            phase.value = 'rating';
        } else {
            phase.value = 'summary';
        }
    }
});

onUnmounted(() => {
    if (animFrameId) cancelAnimationFrame(animFrameId);
    if (audioCtx) audioCtx.close();
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
                    <h1 class="text-3xl font-black text-gray-200">Community Tasks</h1>
                    <p class="text-gray-500 text-sm mt-1">Earn points by assigning demos and rating map difficulty</p>
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
                            {{ Math.round(totalScore) }} / {{ nextTier?.min_score || '--' }} pts
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
                    {{ phase === 'assignment' ? 'Demo Assignment' : phase === 'verification' ? 'Verify Assignments' : phase === 'rating' ? 'Difficulty Rating' : 'Complete' }}
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
            </div>
        </div>

        <!-- ═══ ASSIGNMENT PHASE ═══ -->
        <div v-if="phase === 'assignment'" class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-8">
            <div v-if="visibleAssignments.length === 0" class="text-center py-16 text-gray-500">
                No demos available for assignment right now. Skipping to difficulty ratings...
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <TransitionGroup name="task-card">
                    <div v-for="task in visibleAssignments" :key="task.demo.id"
                        class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-gray-700/40 relative transition-all duration-300"
                        :class="completingSlot === task.demo.id ? 'scale-95 opacity-0' : ''">

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

                                <div class="flex gap-2 mt-auto">
                                    <button @click="confirmAssign" :disabled="assigning"
                                        class="flex-1 py-2.5 bg-green-600 hover:bg-green-500 disabled:bg-green-800 disabled:text-green-400 text-white rounded-lg font-bold text-sm transition-colors">
                                        {{ assigning ? 'Assigning...' : 'Confirm' }}
                                    </button>
                                    <button @click="cancelConfirm"
                                        class="flex-1 py-2.5 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg text-sm transition-colors">
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
                            <div class="text-white font-mono text-xl font-black flex-shrink-0 ml-2">{{ formatTime(task.demo.time_ms) }}</div>
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
                </TransitionGroup>
            </div>
        </div>

        <!-- ═══ VERIFICATION PHASE ═══ -->
        <div v-if="phase === 'verification'" class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-8">
            <div v-if="visibleVerifications.length === 0" class="text-center py-16 text-gray-500">
                No demos to verify. Moving to ratings...
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <TransitionGroup name="task-card">
                    <div v-for="task in visibleVerifications" :key="task.demo.id"
                        class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-emerald-700/30 relative transition-all duration-300"
                        :class="completingSlot === task.demo.id ? 'scale-95 opacity-0' : ''">

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
                                        class="flex-1 py-2.5 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg text-sm transition-colors">
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
                            <div class="text-white font-mono text-xl font-black flex-shrink-0 ml-2">{{ formatTime(task.demo.time_ms) }}</div>
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
                                        : record.time_diff === 0
                                            ? 'bg-green-500/10 hover:bg-green-500/20 ring-1 ring-green-500/20'
                                            : 'hover:bg-white/5'">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="w-5 text-right flex-shrink-0" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : record.time_diff === 0 ? 'text-green-400' : 'text-gray-400'">#{{ record.rank }}</span>
                                        <span class="truncate transition-colors" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-300' : record.time_diff === 0 ? 'text-green-300' : 'text-gray-300 group-hover:text-white'" v-html="q3tohtml(record.player_name)"></span>
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
                                        <span class="font-mono" :class="selectedRecords[task.demo.id]?.id === record.id ? 'text-blue-400' : 'text-gray-500'">{{ formatTime(record.time) }}</span>
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
                </TransitionGroup>
            </div>
        </div>

        <!-- ═══ RATING PHASE ═══ -->
        <div v-if="phase === 'rating'" class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-8">
            <div v-if="visibleRatings.length === 0" class="text-center py-16 text-gray-500">
                No maps available for rating right now.
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <TransitionGroup name="task-card">
                    <div v-for="map in visibleRatings" :key="map.id"
                        class="bg-gray-800/60 backdrop-blur-sm rounded-xl border border-gray-700/40 overflow-hidden transition-all duration-500"
                        :class="ratedMapIds.has(map.id) ? 'scale-95 opacity-40' : ''">
                        <!-- Thumbnail - full aspect ratio -->
                        <div class="relative">
                            <img v-if="map.thumbnail"
                                :src="'/storage/' + map.thumbnail"
                                class="w-full aspect-video object-contain bg-black"
                                :alt="map.name" />
                            <div v-else class="w-full aspect-video bg-gray-700/50 flex items-center justify-center">
                                <span class="text-gray-600 text-xs">No image</span>
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
                                <button v-for="d in difficultyLabels" :key="d.level"
                                    @click="rateMap(map, d.level)"
                                    :disabled="ratedMapIds.has(map.id) || ratingSubmitting === map.id"
                                    class="flex-1 py-1.5 rounded text-[11px] font-bold transition-all border"
                                    :class="ratedMapIds.has(map.id) && ratedValues[map.id] === d.level
                                        ? d.color + ' text-white ring-1 ' + d.ring + ' border-transparent'
                                        : ratedMapIds.has(map.id)
                                            ? 'bg-gray-800 text-gray-700 border-transparent cursor-default'
                                            : d.bgSubtle + ' border-gray-700/30 hover:text-white ' + d.hover + ' cursor-pointer'"
                                    :title="d.desc">
                                    {{ d.label }}
                                </button>
                            </div>
                            <button v-if="!ratedMapIds.has(map.id)"
                                @click="skipRating(map)"
                                class="w-full mt-1.5 py-1.5 bg-gray-700/50 hover:bg-gray-700/70 text-gray-400 hover:text-gray-300 rounded text-xs font-medium transition-all border border-gray-600/30">
                                Skip
                            </button>
                        </div>
                    </div>
                </TransitionGroup>
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
                    {{ completedAssignments }} assigned - {{ completedVerifications }} verified - {{ completedRatings }} rated
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
                            {{ Math.round(nextTier.min_score - totalScore) }} pts to
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
    position: absolute;
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
