<div class="space-y-5 text-sm">
    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Why they want it</div>
        <blockquote class="border-l-2 border-blue-500/40 pl-3 whitespace-pre-line text-gray-800 dark:text-gray-200">{{ $record->motivation }}</blockquote>
    </div>

    @if ($record->experience)
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Experience</div>
            <blockquote class="border-l-2 border-gray-400/40 pl-3 whitespace-pre-line text-gray-800 dark:text-gray-200">{{ $record->experience }}</blockquote>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4">
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Around</div>
            <div class="text-gray-800 dark:text-gray-200">{{ $record->availability ?: '-' }}</div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Contact</div>
            <div class="text-gray-800 dark:text-gray-200">{{ $record->contact ?: '-' }}</div>
        </div>
    </div>

    @if ($record->review_note)
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">Review note</div>
            <div class="text-gray-800 dark:text-gray-200 whitespace-pre-line">{{ $record->review_note }}</div>
        </div>
    @endif
</div>
