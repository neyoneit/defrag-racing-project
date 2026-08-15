<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t } from '@/utils/i18n';

// The rules themselves, kept as data so the numbering is derived rather than
// typed. Renumbering by hand is how a ruleset ends up with two rule 4.2s, and
// the numbers matter here - people cite them when they report somebody.
const sections = computed(() => [
    {
        title: t('Client and binaries'),
        accent: 'red',
        rules: [
            t('You may not modify the Quake 3 or DeFRaG binaries in any way.'),
            t('You may not modify the memory DeFRaG uses with external programs while it runs.'),
            t('You may not drop or delay network packets on purpose.'),
            t('Allowed clients are oDFe and iDFe. Records set on anything else are not accepted.'),
        ],
    },
    {
        title: t('Scripting and automation'),
        accent: 'red',
        rules: [
            t('You may not run a sequence of commands, triggered by software or hardware, that affects your movement with predefined timing or order.'),
            t('You may not use anything external to time your actions: jumps, shots, view changes.'),
            t('This covers game scripts, macro keyboards and mice, autoclickers and tools such as AutoIt.'),
            t('DeFRaG is about doing something precisely, many times over. Anything that does the precision for you is out.'),
        ],
    },
    {
        title: t('Accounts'),
        accent: 'blue',
        rules: [
            t('One account per person.'),
            t('You may not share your login with anyone.'),
            t('You may not play on somebody else\'s account.'),
            t('A record belongs to a person, not to a name. An account that gets shared makes every record on it worthless.'),
        ],
    },
    {
        title: t('Behaviour that counts as cheating'),
        accent: 'red',
        rules: [
            t('You may not change your view angles by any means other than moving the mouse. +left, +right and similar are not allowed.'),
            t('You may not bind movement actions to the mouse wheel.'),
            t('You may not gain speed across several frames without changing position.'),
            t('Something not being listed here does not make it allowed. If a trick feels like it is doing the work for you, ask before you put it in a record.'),
        ],
    },
    {
        title: t('Network settings'),
        accent: 'blue',
        rules: [
            t('Keep packet loss as low as you can.'),
            t('Recommended: cl_maxpackets 125, snaps 125, rate 25000, cl_packetdup 2 or higher.'),
            t('Extreme settings that exploit network dependent physics are a breach of section 1, not a configuration choice.'),
        ],
    },
    {
        title: t('Records and demos'),
        accent: 'blue',
        rules: [
            t('A record must come from a run you played yourself, from start to finish.'),
            t('Servers record a demo of every finished run. Those demos are kept and may be reviewed.'),
            t('A record found to break these rules is removed.'),
        ],
    },
    {
        // Comps enters people by itself, from a demo they uploaded for any
        // reason at all. Nobody signs up, so nobody is ever shown terms - which
        // makes this the only place somebody can find out before it happens
        // rather than after.
        title: t('Comps'),
        accent: 'blue',
        rules: [
            t('Any run you record on a map comps is playing that week is entered automatically when you upload the demo - there is no sign-up.'),
            t('The demo stays hidden until the round ends, then appears like any other.'),
            t('If you do not want to be in it, do not upload a demo of that map until the round is over.'),
            t('Entering, and voting, need a Q3DF.org profile linked to your account.'),
        ],
    },
    {
        title: t('Reporting'),
        accent: 'blue',
        rules: [
            t('Anyone with an account may report a record, up to 20 per hour.'),
            t('Report what you saw, not who you dislike.'),
            t('Every report is checked by an admin before anybody reviews a demo, and a single report on its own never moves anything.'),
            t('Reporting in bulk to bury somebody achieves nothing and will cost you the ability to report at all.'),
        ],
    },
    {
        title: t('Conduct'),
        accent: 'green',
        rules: [
            t('Be polite, respect other players, play fair.'),
            t('Players here come from all over the world. Speak whatever language you like.'),
        ],
    },
    {
        title: t('Enforcement'),
        accent: 'amber',
        rules: [
            t('Breaking these rules costs you the affected records, and repeatedly the account.'),
            t('Decisions are made by admins after a demo review, never automatically.'),
        ],
    },
]);

// The rules above are the rules. These are the questions people actually ask,
// answered by name - "no scripts" tells nobody whether their own bind is one.
const examples = computed(() => ({
    forbidden: [
        t('A bind that fires a rocket and jumps from one keypress.'),
        t('A bind that plays back several actions in order, however short.'),
        t('Macro keys on a keyboard or mouse that replay a recorded sequence.'),
        t('Any external program that presses keys for you or times them.'),
        t('+left, +right, or anything else that turns your view without the mouse.'),
        t('Jump or crouch bound to the mouse wheel.'),
        t('A modified or unofficial client build.'),
        t('Network settings chosen to lag triggers rather than to play well.'),
    ],
    allowed: [
        t('Binds that do one thing per press: fire, jump, switch weapon, say a message.'),
        t('Toggling a setting with a key, as long as it does not move you.'),
        t('Config tweaks: sensitivity, fov, colours, sounds, picmip, huds.'),
        t('Doing the whole trick yourself, however many keys it takes.'),
        t('Practising a run as many times as you like. Precision by repetition is the game.'),
    ],
}));

const accents = {
    red: 'border-red-500/30 text-red-300 bg-red-500/15',
    blue: 'border-blue-400/30 text-blue-300 bg-blue-500/15',
    green: 'border-green-400/30 text-green-300 bg-green-500/15',
    amber: 'border-amber-400/30 text-amber-300 bg-amber-500/15',
};
</script>

<template>
    <Head :title="$t('Rules')" />

    <div class="min-h-screen py-10">
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">

            <!-- Prose stays capped even though the page is not: a line of body
                 text running the full width of a monitor is unreadable. -->
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl font-black text-white mb-3">{{ $t('Rules') }}</h1>
                <p class="text-gray-300 text-lg leading-relaxed max-w-3xl">
                    {{ $t('These apply to every player and every record on defrag.racing. They exist so that a time on this site means the same thing whoever set it.') }}
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

            <!-- The rules are the rules; this is the same thing named out
                 loud. "No scripts" tells nobody whether their own bind counts
                 as one, and that is the question that actually gets asked. -->
            <div class="mt-6 bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl">
                <h2 class="text-xl font-bold text-white mb-2">{{ $t('In practice') }}</h2>
                <p class="text-gray-400 text-sm mb-5 max-w-3xl [&_span]:text-white [&_span]:font-semibold"
                   v-html="$t('One line decides nearly every case: <span>a key may do one thing when you press it, and the timing has to be yours.</span> Doing the whole trick yourself is a technique, no matter how hard it is. Having software do part of it for you is not.')"></p>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-red-300 mb-3">{{ $t('Not allowed') }}</h3>
                        <ul class="space-y-2">
                            <li v-for="item in examples.forbidden" :key="item" class="flex gap-2.5 text-sm text-gray-300">
                                <span class="shrink-0 text-red-400 font-bold">&times;</span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-green-300 mb-3">{{ $t('Allowed') }}</h3>
                        <ul class="space-y-2">
                            <li v-for="item in examples.allowed" :key="item" class="flex gap-2.5 text-sm text-gray-300">
                                <span class="shrink-0 text-green-400 font-bold">&check;</span>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <p class="text-gray-500 text-xs mt-5">
                    {{ $t('Not sure whether something of yours counts? Ask before you use it in a record. Asking has never cost anybody a time; assuming has.') }}
                </p>
            </div>

            <p class="text-gray-500 text-sm mt-8 max-w-3xl [&_a]:text-blue-400 [&_a:hover]:text-blue-300"
               v-html="$t('Running a server has its own set of rules, on the <a href=:url>server hosting</a> page.', { url: route('server-hosting.index') })"></p>
        </div>
    </div>
</template>
