{{--
    The prompt for one work type, ready to hand to an assistant.

    It is a textarea rather than a <pre> so the whole thing can be selected
    with a keyboard as well as with the button, and readonly so a stray
    keystroke cannot quietly change what gets pasted.
--}}
<div x-data="{ copied: false }" class="space-y-3">
    <textarea
        x-ref="prompt"
        readonly
        rows="18"
        class="w-full rounded-lg border-gray-300 bg-gray-50 font-mono text-xs leading-relaxed text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
    >{{ $prompt }}</textarea>

    <div class="flex items-center gap-3">
        <button
            type="button"
            x-on:click="
                $refs.prompt.select();
                navigator.clipboard.writeText($refs.prompt.value).catch(() => document.execCommand('copy'));
                copied = true;
                setTimeout(() => copied = false, 2000);
            "
            class="fi-btn inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
            </svg>
            <span x-text="copied ? 'Copied' : 'Copy the prompt'"></span>
        </button>

        <p class="text-sm text-gray-500">
            Paste it into a chat. What comes back goes into <strong>Paste translations</strong>
            on this same row, or into the command the answer includes.
        </p>
    </div>
</div>
