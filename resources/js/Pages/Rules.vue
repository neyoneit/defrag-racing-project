<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head } from '@inertiajs/vue3';

// The rules themselves, kept as data so the numbering is derived rather than
// typed. Renumbering by hand is how a ruleset ends up with two rule 4.2s, and
// the numbers matter here - people cite them when they report somebody.
const sections = [
    {
        title: 'Client and binaries',
        accent: 'red',
        rules: [
            'You may not modify the Quake 3 or DeFRaG binaries in any way.',
            'You may not modify the memory DeFRaG uses with external programs while it runs.',
            'You may not drop or delay network packets on purpose.',
            'Allowed clients are oDFe and iodfe. Records set on anything else are not accepted.',
        ],
    },
    {
        title: 'Scripting and automation',
        accent: 'red',
        rules: [
            'You may not run a sequence of commands, triggered by software or hardware, that affects your movement with predefined timing or order.',
            'You may not use anything external to time your actions: jumps, shots, view changes.',
            'This covers game scripts, macro keyboards and mice, autoclickers and tools such as AutoIt.',
            'DeFRaG is about doing something precisely, many times over. Anything that does the precision for you is out.',
        ],
    },
    {
        title: 'Accounts',
        accent: 'blue',
        rules: [
            'One account per person.',
            'You may not share your login with anyone.',
            'You may not play on somebody else\'s account.',
            'A record belongs to a person, not to a name. An account that gets shared makes every record on it worthless.',
        ],
    },
    {
        title: 'Behaviour that counts as cheating',
        accent: 'red',
        rules: [
            'You may not change your view angles by any means other than moving the mouse. +left, +right and similar are not allowed.',
            'You may not bind movement actions to the mouse wheel.',
            'You may not gain speed across several frames without changing position.',
            'Something not being listed here does not make it allowed. If a trick feels like it is doing the work for you, ask before you put it in a record.',
        ],
    },
    {
        title: 'Network settings',
        accent: 'blue',
        rules: [
            'Keep packet loss as low as you can.',
            'Recommended: cl_maxpackets 125, snaps 125, rate 25000, cl_packetdup 2 or higher.',
            'Extreme settings that exploit network dependent physics are a breach of section 1, not a configuration choice.',
        ],
    },
    {
        title: 'Records and demos',
        accent: 'blue',
        rules: [
            'A record must come from a run you played yourself, from start to finish.',
            'Servers record a demo of every finished run. Those demos are kept and may be reviewed.',
            'A record found to break these rules is removed.',
        ],
    },
    {
        title: 'Reporting',
        accent: 'blue',
        rules: [
            'Anyone with an account may report a record, up to 20 per hour.',
            'Report what you saw, not who you dislike.',
            'Every report is checked by an admin before anybody reviews a demo, and a single report on its own never moves anything.',
            'Reporting in bulk to bury somebody achieves nothing and will cost you the ability to report at all.',
        ],
    },
    {
        title: 'Conduct',
        accent: 'green',
        rules: [
            'Be polite, respect other players, play fair.',
            'Players here come from all over the world. Speak whatever language you like.',
        ],
    },
    {
        title: 'Enforcement',
        accent: 'amber',
        rules: [
            'Breaking these rules costs you the affected records, and repeatedly the account.',
            'Decisions are made by admins after a demo review, never automatically.',
        ],
    },
];

const accents = {
    red: 'border-red-500/30 text-red-300 bg-red-500/15',
    blue: 'border-blue-400/30 text-blue-300 bg-blue-500/15',
    green: 'border-green-400/30 text-green-300 bg-green-500/15',
    amber: 'border-amber-400/30 text-amber-300 bg-amber-500/15',
};
</script>

<template>
    <Head title="Rules" />

    <div class="min-h-screen py-10">
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">

            <!-- Prose stays capped even though the page is not: a line of body
                 text running the full width of a monitor is unreadable. -->
            <div class="mb-8">
                <h1 class="text-3xl md:text-4xl font-black text-white mb-3">Rules</h1>
                <p class="text-gray-300 text-lg leading-relaxed max-w-3xl">
                    These apply to every player and every record on defrag.racing. They exist so that
                    a time on this site means the same thing whoever set it.
                </p>
            </div>

            <!-- Two columns from lg: the sections are short, and stacking nine
                 of them makes the page look longer than the ruleset is. -->
            <div class="grid gap-6 lg:grid-cols-2 items-start">
                <section
                    v-for="(section, i) in sections"
                    :key="section.title"
                    class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl"
                >
                    <div class="flex items-center gap-3 mb-4">
                        <span
                            class="shrink-0 w-8 h-8 rounded-full border flex items-center justify-center text-sm font-black"
                            :class="accents[section.accent]"
                        >{{ i + 1 }}</span>
                        <h2 class="text-xl font-bold text-white">{{ section.title }}</h2>
                    </div>

                    <ul class="space-y-3">
                        <li v-for="(rule, j) in section.rules" :key="j" class="flex gap-3 text-sm text-gray-300">
                            <span class="shrink-0 font-mono text-gray-500">{{ i + 1 }}.{{ j + 1 }}</span>
                            <span>{{ rule }}</span>
                        </li>
                    </ul>
                </section>
            </div>

            <p class="text-gray-500 text-sm mt-8 max-w-3xl">
                Running a server has its own set of rules, on the
                <a :href="route('server-hosting.index')" class="text-blue-400 hover:text-blue-300">server hosting</a>
                page.
            </p>
        </div>
    </div>
</template>
