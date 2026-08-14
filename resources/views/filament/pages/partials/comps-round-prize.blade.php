{{--
    What this one round pays, per physics.

    Separate from the default under Schedule on purpose: a donation earmarked
    for a single weekly has to raise that weekly without raising every week
    after it, and without rewriting what the weeks before it paid.
--}}
<div class="mt-3 flex flex-wrap items-end gap-2 border-t border-gray-200 dark:border-white/10 pt-3"
     x-data="{ eur: {{ (int) ($round->prize_eur ?? 0) }} }">
    <div>
        <label class="block text-xs text-gray-500 mb-1">This round pays, per physics (EUR)</label>
        <input type="number" x-model.number="eur" min="0" max="10000"
               class="w-24 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
    </div>
    <button type="button"
            x-on:click="$wire.setRoundPrize({{ $round->id }}, eur)"
            class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-bold text-white hover:bg-primary-500">
        Set
    </button>
    <p class="text-xs text-gray-500 basis-full">
        Total for the week is twice this, one payout per physics. Changing it here leaves the default alone.
    </p>
</div>
