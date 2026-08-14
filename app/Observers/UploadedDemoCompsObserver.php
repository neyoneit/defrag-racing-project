<?php

namespace App\Observers;

use App\Models\UploadedDemo;
use App\Services\Comps\SubmissionValidator;
use App\Services\Comps\UploadGuard;

/**
 * Watches uploads for the ones that are comps entries, and settles them the
 * moment the parser is finished with them.
 *
 * Hooked on the model rather than inside the processing job on purpose: a demo
 * reaches its final status from several places - the job, a retry, an admin
 * reassigning it - and an entry should settle whichever of those got there.
 *
 * Two jobs, in this order. First the entries somebody made get their verdict.
 * Then every other demo is measured against what comps is currently using, and
 * held or entered if it turns out to be a run of one of those maps - see
 * UploadGuard.
 */
class UploadedDemoCompsObserver
{
    public function __construct(
        private SubmissionValidator $validator,
        private UploadGuard $guard,
    ) {
    }

    public function updated(UploadedDemo $demo): void
    {
        // Only when the parse outcome itself moved. Every other edit - an
        // assignment, a download counter, the hold the guard writes below -
        // leaves entries alone. This is also what stops the guard's own update
        // from re-entering here.
        if (! $demo->wasChanged('status')) {
            return;
        }

        $this->validator->settleFor($demo);
        $this->guard->apply($demo);
    }
}
