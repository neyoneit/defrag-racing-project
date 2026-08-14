<?php

namespace App\Observers;

use App\Models\UploadedDemo;
use App\Services\Comps\SubmissionValidator;

/**
 * Watches uploads for the ones that are comps entries, and settles them the
 * moment the parser is finished with them.
 *
 * Hooked on the model rather than inside the processing job on purpose: a demo
 * reaches its final status from several places - the job, a retry, an admin
 * reassigning it - and an entry should settle whichever of those got there.
 */
class UploadedDemoCompsObserver
{
    public function __construct(private SubmissionValidator $validator)
    {
    }

    public function updated(UploadedDemo $demo): void
    {
        // Only when the parse outcome itself moved. Every other edit - an
        // assignment, a download counter - leaves entries alone.
        if (! $demo->wasChanged('status')) {
            return;
        }

        $this->validator->settleFor($demo);
    }
}
