<?php

namespace App\Http\Controllers;

use App\Services\DemoSettingsChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Would this demo's settings count?" - answered without uploading anything.
 *
 * Deliberately not part of the upload flow. Nothing here creates a row, joins
 * a queue or reaches storage: the file is written to a temporary path, read,
 * and deleted before the response leaves. That is the whole difference between
 * a tool people use freely and a second way to fill the disk.
 */
class DemoCheckController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Demos/Check');
    }

    /**
     * Answers as JSON rather than as an Inertia redirect: the page asks a
     * question about a file and draws the answer, with nothing to remember
     * afterwards. A redirect would need the result carried in the session and
     * would put it back on screen on every reload of a page that has no file.
     */
    public function check(Request $request, DemoSettingsChecker $checker): JsonResponse
    {
        $request->validate([
            // By extension rather than mime: a .dm_68 has no registered type
            // and every mime guess lands on application/octet-stream, which
            // would let through anything at all.
            'demo' => ['required', 'file', 'max:30720'],
        ]);

        $file = $request->file('demo');

        if (! preg_match('/\.dm_\d{2}$/i', (string) $file->getClientOriginalName())) {
            return response()->json([
                'message' => __('That is not a demo file. A demo ends in .dm_68.'),
            ], 422);
        }

        // getRealPath() is the upload's own temporary file, which PHP removes
        // when the request ends - so there is nothing to clean up and no path
        // for a half-finished request to leave behind.
        try {
            $result = $checker->check($file->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result + [
            'filename' => $file->getClientOriginalName(),
        ]);
    }
}
