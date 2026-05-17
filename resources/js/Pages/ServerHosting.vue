<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    application: Object,
    credential: Object,
    pendingPassword: { type: String, default: null },
});

const page = usePage();
const successMsg = computed(() => page.props.success);
const dangerMsg = computed(() => page.props.danger);

const passwordRevealed = ref(false);
const passwordCopied = ref(false);

const ackForm = useForm({});
const resetForm = useForm({});

// Single styled confirm modal, driven by state. Replaces native
// browser confirm() — same blocking semantics via the .onConfirm cb.
const modal = ref({
    open: false,
    title: '',
    body: '',
    confirmLabel: 'Confirm',
    confirmTone: 'emerald', // tailwind color stub: emerald | amber | red | blue
    onConfirm: () => {},
});

const openConfirm = (opts) => {
    modal.value = {
        open: true,
        title: opts.title ?? 'Are you sure?',
        body: opts.body ?? '',
        confirmLabel: opts.confirmLabel ?? 'Confirm',
        confirmTone: opts.confirmTone ?? 'emerald',
        onConfirm: opts.onConfirm ?? (() => {}),
    };
};

const closeModal = () => { modal.value.open = false; };

const confirmModal = () => {
    const cb = modal.value.onConfirm;
    closeModal();
    cb();
};

// Color presets so we don't ship dynamic Tailwind classes (purged at build).
const toneClasses = {
    emerald: 'bg-emerald-500/20 hover:bg-emerald-500/30 border-emerald-500/40 text-emerald-200',
    amber:   'bg-amber-500/20  hover:bg-amber-500/30  border-amber-500/40  text-amber-200',
    red:     'bg-red-500/20    hover:bg-red-500/30    border-red-500/40    text-red-200',
    blue:    'bg-blue-500/20   hover:bg-blue-500/30   border-blue-500/40   text-blue-200',
};

const requestReset = () => {
    openConfirm({
        title: 'Generate a new password?',
        body: 'Your current one will stop working immediately. The new password is shown only once on this page — make sure you can save it.',
        confirmLabel: 'Generate new password',
        confirmTone: 'amber',
        onConfirm: () => {
            resetForm.post(route('server-hosting.reset-password'), {
                preserveScroll: true,
            });
        },
    });
};

const copyPassword = async () => {
    if (!props.pendingPassword) return;
    try {
        await navigator.clipboard.writeText(props.pendingPassword);
        passwordCopied.value = true;
        setTimeout(() => { passwordCopied.value = false; }, 2500);
    } catch (e) {
        // Fallback for non-https contexts: just leave the value visible
        // so the user can select+copy manually.
        passwordRevealed.value = true;
    }
};

const acknowledgePassword = () => {
    openConfirm({
        title: 'Wipe the password from this page?',
        body: "Make sure you've saved it somewhere safe — after confirming, it cannot be shown again. You'd need to generate a new one (or ask an admin to rotate).",
        confirmLabel: "Yes, I've saved it",
        confirmTone: 'emerald',
        onConfirm: () => {
            ackForm.post(route('server-hosting.acknowledge-password'), {
                preserveScroll: true,
            });
        },
    });
};

const GAMETYPES = [
    { value: 'mixed',     label: 'Mixed' },
    { value: 'cpm',       label: 'CPM' },
    { value: 'vq3',       label: 'VQ3' },
    { value: 'teamruns',  label: 'Teamruns' },
    { value: 'fastcaps',  label: 'Fastcaps' },
    { value: 'freestyle', label: 'Freestyle' },
];

const blankServer = () => ({
    gametype: 'mixed',
    ip: '',
    port: 27960,
    rcon: '',
    location: '',
});

const form = useForm({
    message: '',
    servers: [blankServer()],
});

const addServer = () => form.servers.push(blankServer());
const removeServer = (i) => {
    if (form.servers.length > 1) form.servers.splice(i, 1);
};

const submit = () => {
    form.post(route('server-hosting.apply'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const state = computed(() => {
    if (props.credential) return 'active';
    if (!props.application) return 'none';
    return props.application.status;
});

// server_info on the API side is cast to array — render defensively in
// case an older record still has a plain string in there.
const submittedServers = computed(() => {
    const raw = props.application?.server_info;
    if (Array.isArray(raw)) return raw;
    return [];
});

const submittedServerInfoString = computed(() => {
    const raw = props.application?.server_info;
    return typeof raw === 'string' ? raw : '';
});

const gametypeLabel = (value) => GAMETYPES.find(g => g.value === value)?.label ?? value;
</script>

<template>
    <Head title="Server hosting access" />

    <div class="container mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-bold text-white mb-2">Server hosting access</h1>
        <p class="text-sm text-gray-400 mb-3">
            If you host (or plan to host) a public defrag server, apply here for an SFTP account
            on our storage VPS. Approved server owners get a chroot-isolated drop-box where the
            <a href="https://github.com/Defrag-racing/defrag-server-bundle"
               target="_blank" rel="noopener"
               class="text-emerald-300 hover:text-emerald-200 underline decoration-dotted">
                defrag-server-bundle
            </a>
            can ship <code>.dm_68</code> demos for indexing on defrag.racing.
        </p>
        <p class="text-xs text-gray-500 mb-8">
            Install the bundle on your defrag host first, then plug your SFTP credentials into
            <code>sv.conf</code> — we'll give them to you after approval.
        </p>

        <div v-if="successMsg"
             class="mb-6 rounded-xl border border-green-500/30 bg-black/40 backdrop-blur-sm px-4 py-3 text-sm text-green-300 shadow-2xl">
            {{ successMsg }}
        </div>
        <div v-if="dangerMsg"
             class="mb-6 rounded-xl border border-red-500/30 bg-black/40 backdrop-blur-sm px-4 py-3 text-sm text-red-300 shadow-2xl">
            {{ dangerMsg }}
        </div>

        <!-- STATE: ACTIVE CREDENTIAL --------------------------------------- -->
        <section v-if="state === 'active'"
                 class="rounded-xl border border-emerald-500/30 bg-black/40 backdrop-blur-sm p-6 shadow-2xl">
            <div class="flex items-center gap-2 mb-4">
                <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                <h2 class="text-lg font-semibold text-emerald-300">Active SFTP credentials</h2>
            </div>

            <p class="text-sm text-gray-400 mb-4">
                Use these in your <code>sv.conf</code>. The password is shown <strong class="text-emerald-300">only once</strong>
                — copy it now, or ask an admin to reset it later if you lose it.
            </p>

            <!-- One-time password reveal box (only when pending) -->
            <div v-if="pendingPassword"
                 class="mb-6 rounded-lg border border-yellow-400/40 bg-yellow-500/5 p-4">
                <div class="flex items-start gap-3">
                    <span class="text-yellow-300 text-xl leading-none">⚠</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-yellow-200 mb-1">Save your password now</div>
                        <p class="text-xs text-gray-400 mb-3">
                            This is the only time you'll see it. After clicking "I've saved it", we wipe it from our DB —
                            it lives only on the storage VPS in hashed form. If you lose it, ask an admin to rotate.
                        </p>

                        <div class="flex items-center gap-2 mb-3">
                            <code class="flex-1 px-3 py-2 bg-black/60 border border-white/10 rounded-lg text-sm font-mono text-emerald-200 select-all break-all">
                                <span v-if="passwordRevealed">{{ pendingPassword }}</span>
                                <span v-else class="text-gray-500">●●●●●●●●●●●●●●●●●●●●●●●●</span>
                            </code>
                            <button type="button"
                                    @click="passwordRevealed = !passwordRevealed"
                                    class="px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-gray-300 transition whitespace-nowrap">
                                {{ passwordRevealed ? 'Hide' : 'Reveal' }}
                            </button>
                            <button type="button"
                                    @click="copyPassword"
                                    class="px-3 py-2 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/30 text-xs text-blue-200 transition whitespace-nowrap">
                                {{ passwordCopied ? 'Copied ✓' : 'Copy' }}
                            </button>
                        </div>

                        <button type="button"
                                @click="acknowledgePassword"
                                :disabled="ackForm.processing"
                                class="px-3 py-2 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/30 text-xs text-emerald-200 transition disabled:opacity-50">
                            ✓ I've saved it — wipe from this page
                        </button>
                    </div>
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Host</dt>
                    <dd class="text-gray-200 font-mono">{{ credential.host }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Port</dt>
                    <dd class="text-gray-200 font-mono">{{ credential.port }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Username</dt>
                    <dd class="text-gray-200 font-mono">{{ credential.sftp_username }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Remote path</dt>
                    <dd class="text-gray-200 font-mono">{{ credential.remote_path }}</dd>
                </div>
                <div v-if="!pendingPassword" class="sm:col-span-2">
                    <dt class="text-gray-500">Password</dt>
                    <dd class="flex items-center gap-3 flex-wrap">
                        <span class="text-gray-400 italic">no longer shown</span>
                        <button type="button"
                                @click="requestReset"
                                :disabled="resetForm.processing"
                                class="px-3 py-1 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-xs text-amber-200 transition disabled:opacity-50">
                            {{ resetForm.processing ? 'Resetting…' : 'Generate new password' }}
                        </button>
                        <span class="text-xs text-gray-500">(rate-limited — max 3/hour)</span>
                    </dd>
                </div>
            </dl>

            <!-- Per-server RS codes (filled in by admin after approval) -->
            <div v-if="credential.servers && credential.servers.length" class="mt-6">
                <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Your servers</div>
                <div class="rounded-lg border border-white/10 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-white/5 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Gametype</th>
                                <th class="px-3 py-2 text-left">IP : Port</th>
                                <th class="px-3 py-2 text-left">Location</th>
                                <th class="px-3 py-2 text-left">RS code</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="(s, i) in credential.servers" :key="i">
                                <td class="px-3 py-2 font-medium text-emerald-200/80">{{ (s.gametype || '').toUpperCase() }}</td>
                                <td class="px-3 py-2 font-mono text-gray-300">{{ s.ip }}:{{ s.port }}</td>
                                <td class="px-3 py-2 font-mono text-gray-300">
                                    <span v-if="s.location" class="inline-flex items-center gap-1.5">
                                        <img :src="`/images/flags/${s.location}.png`" class="w-4 h-3 rounded shadow-sm" :alt="s.location" onerror="this.style.display='none'">
                                        {{ s.location }}
                                    </span>
                                    <span v-else class="text-gray-500 italic">not set</span>
                                </td>
                                <td class="px-3 py-2 font-mono">
                                    <span v-if="s.rs_code" class="text-emerald-200">{{ s.rs_code }}</span>
                                    <span v-else class="text-gray-500 italic">awaiting admin</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    Put each RS code into <code>sv.conf</code> as <code>rs&lt;PORT&gt;=&lt;rs_code&gt;</code> (set <code>MDD_ENABLED=1</code> too).
                </p>
            </div>

            <details class="mt-6 group">
                <summary class="cursor-pointer text-sm text-emerald-300 hover:text-emerald-200 select-none">
                    Example <code>sv.conf</code> snippet
                </summary>
                <pre class="mt-3 text-xs bg-black/60 border border-white/10 rounded-lg p-4 overflow-x-auto text-gray-300"
><code>MDD_ENABLED=1
<template v-for="s in (credential.servers || [])" :key="s.port">rs{{ s.port }}={{ s.rs_code || '0' }}
</template>
DEMO_SFTP_ENABLED=1
DEMO_SFTP_HOST={{ credential.host }}
DEMO_SFTP_PORT={{ credential.port }}
DEMO_SFTP_USER={{ credential.sftp_username }}
DEMO_SFTP_PASS={{ pendingPassword || '<your password>' }}
DEMO_SFTP_REMOTEDIR={{ credential.remote_path }}</code></pre>
            </details>
        </section>

        <!-- STATE: PENDING APPLICATION -------------------------------------- -->
        <section v-else-if="state === 'pending'"
                 class="rounded-xl border border-yellow-500/30 bg-black/40 backdrop-blur-sm p-6 shadow-2xl">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block h-2 w-2 rounded-full bg-yellow-400 animate-pulse"></span>
                <h2 class="text-lg font-semibold text-yellow-300">Application pending review</h2>
            </div>
            <p class="text-sm text-gray-400 mb-4">
                Submitted {{ new Date(application.created_at).toLocaleString() }}. An admin
                will be in touch — you don't need to resubmit.
            </p>
            <blockquote class="text-sm text-gray-300 border-l-2 border-yellow-500/30 pl-3 whitespace-pre-line">{{ application.message }}</blockquote>

            <div v-if="submittedServers.length" class="mt-4">
                <div class="text-xs text-gray-500 mb-2">Declared servers</div>
                <ul class="space-y-1 text-sm text-gray-300 font-mono">
                    <li v-for="(s, i) in submittedServers" :key="i" class="flex flex-wrap gap-x-3 gap-y-1">
                        <span class="inline-block min-w-16 text-yellow-300/80">{{ gametypeLabel(s.gametype) }}</span>
                        <span>{{ s.ip }}:{{ s.port }}</span>
                        <span class="text-gray-500">rcon: <span class="text-gray-400">●●●●●●</span></span>
                    </li>
                </ul>
            </div>
            <div v-else-if="submittedServerInfoString" class="mt-3 text-xs text-gray-500 whitespace-pre-line">
                Server info: {{ submittedServerInfoString }}
            </div>
        </section>

        <!-- STATE: REJECTED — banner only; form is re-rendered below ------ -->
        <section v-if="state === 'rejected'"
                 class="rounded-xl border border-red-500/30 bg-black/40 backdrop-blur-sm p-6 mb-6 shadow-2xl">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block h-2 w-2 rounded-full bg-red-400"></span>
                <h2 class="text-lg font-semibold text-red-300">Previous application rejected</h2>
            </div>
            <p v-if="application.review_note" class="text-sm text-gray-300 whitespace-pre-line">
                <span class="text-gray-500">Note from reviewer:</span> {{ application.review_note }}
            </p>
        </section>

        <!-- STATE: NO APPLICATION or REJECTED (show form) ------------------ -->
        <section v-if="state === 'none' || state === 'rejected'"
                 class="rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm p-6 shadow-2xl">
            <h2 class="text-lg font-semibold text-white mb-4">
                {{ state === 'rejected' ? 'Submit a new application' : 'Apply for SFTP access' }}
            </h2>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Message -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Why do you want server hosting access?
                        <span class="text-red-400">*</span>
                    </label>
                    <textarea v-model="form.message"
                              rows="4"
                              maxlength="2000"
                              placeholder="Briefly describe your defrag.racing history, why you want to host, anything we should know."
                              class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm text-gray-200 focus:border-blue-500 focus:ring-0 transition"></textarea>
                    <div class="text-xs text-gray-500 mt-1 flex justify-between">
                        <span :class="form.errors.message ? 'text-red-400' : ''">{{ form.errors.message || 'Minimum 20 characters.' }}</span>
                        <span>{{ form.message.length }}/2000</span>
                    </div>
                </div>

                <!-- Servers (gametype + IP + port + rcon, repeatable) -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-300">
                            Your defrag servers <span class="text-red-400">*</span>
                        </label>
                        <span class="text-xs text-gray-500">add one row per running server</span>
                    </div>

                    <div class="space-y-2">
                        <div v-for="(s, i) in form.servers" :key="i"
                             class="grid grid-cols-12 gap-2 items-start">
                            <div class="col-span-2">
                                <select v-model="s.gametype"
                                        class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 focus:border-blue-500 focus:ring-0 transition appearance-none cursor-pointer
                                               bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22%23a1a1aa%22><path d=%22M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z%22/></svg>')]
                                               bg-no-repeat bg-[right_0.5rem_center] bg-[length:1rem] pr-8">
                                    <option v-for="g in GAMETYPES" :key="g.value" :value="g.value"
                                            class="bg-slate-900 text-gray-200">{{ g.label }}</option>
                                </select>
                                <p v-if="form.errors[`servers.${i}.gametype`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.gametype`] }}</p>
                            </div>
                            <div class="col-span-3">
                                <input v-model="s.ip"
                                       type="text"
                                       placeholder="123.45.67.89"
                                       class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono focus:border-blue-500 focus:ring-0 transition" />
                                <p v-if="form.errors[`servers.${i}.ip`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.ip`] }}</p>
                            </div>
                            <div class="col-span-2">
                                <input v-model.number="s.port"
                                       type="number"
                                       min="1" max="65535"
                                       placeholder="27960"
                                       class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono focus:border-blue-500 focus:ring-0 transition" />
                                <p v-if="form.errors[`servers.${i}.port`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.port`] }}</p>
                            </div>
                            <div class="col-span-2">
                                <input v-model="s.rcon"
                                       type="text"
                                       placeholder="rcon"
                                       class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono focus:border-blue-500 focus:ring-0 transition" />
                                <p v-if="form.errors[`servers.${i}.rcon`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.rcon`] }}</p>
                            </div>
                            <div class="col-span-2">
                                <input v-model="s.location"
                                       type="text"
                                       maxlength="2"
                                       placeholder="US"
                                       @input="s.location = (s.location || '').toUpperCase()"
                                       class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono uppercase focus:border-blue-500 focus:ring-0 transition" />
                                <p v-if="form.errors[`servers.${i}.location`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.location`] }}</p>
                            </div>
                            <div class="col-span-1 flex justify-end">
                                <button type="button"
                                        @click="removeServer(i)"
                                        :disabled="form.servers.length <= 1"
                                        class="h-9 w-9 inline-flex items-center justify-center rounded-lg bg-red-500/10 hover:bg-red-500/20 disabled:opacity-30 disabled:cursor-not-allowed border border-red-500/30 text-red-300 text-lg leading-none transition"
                                        title="Remove this row">
                                    −
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button"
                            @click="addServer"
                            class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-sm transition">
                        <span class="text-base leading-none">+</span> Add another server
                    </button>

                    <p v-if="form.errors.servers" class="text-xs text-red-400 mt-2">{{ form.errors.servers }}</p>
                </div>

                <!-- Submit -->
                <div class="flex justify-end pt-2 border-t border-white/5">
                    <button type="submit"
                            :disabled="form.processing || form.message.length < 20"
                            class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-400 disabled:bg-gray-700 disabled:cursor-not-allowed text-white text-sm font-medium transition">
                        {{ form.processing ? 'Submitting…' : 'Submit application' }}
                    </button>
                </div>
            </form>
        </section>

    </div>

    <!-- Styled confirm modal — replaces native window.confirm() -->
    <Teleport to="body">
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="modal.open"
                 class="fixed inset-0 z-[200] flex items-center justify-center px-4"
                 @click.self="closeModal">
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

                <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-black/70 backdrop-blur-md shadow-2xl"
                     @keydown.esc.stop="closeModal">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">{{ modal.title }}</h3>
                        <p class="text-sm text-gray-400 whitespace-pre-line">{{ modal.body }}</p>
                    </div>
                    <div class="px-6 py-4 border-t border-white/5 flex justify-end gap-2">
                        <button type="button"
                                @click="closeModal"
                                class="px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-gray-300 transition">
                            Cancel
                        </button>
                        <button type="button"
                                @click="confirmModal"
                                :class="[toneClasses[modal.confirmTone] || toneClasses.emerald,
                                         'px-4 py-2 rounded-lg border text-sm font-medium transition']">
                            {{ modal.confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
