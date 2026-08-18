<?php

namespace App\Services;

use App\Models\UploadedDemo;
use App\Services\Comps\SubmissionValidator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Reads a demo somebody dropped on the site and says whether the settings in
 * it would count, without keeping the file.
 *
 * **Why this exists.** The settings a run has to be made with are written into
 * the demo, and until now the only way to find out what yours said was to
 * upload a real run and wait for a refusal. One player spent four days
 * believing a config was loaded when it was not, and the demo had been telling
 * the truth the whole time - nobody could read it.
 *
 * **Nothing is stored.** The upload goes to a temporary file, the parser reads
 * it, the file is deleted in the same request. No row, no queue, no B2. This
 * is a tool, not a second upload route, and treating it as one would fill the
 * disk with demos nobody meant to keep.
 *
 * **The verdict is not re-implemented here.** Whether a cvar is wrong is
 * decided by the parser, exactly as it is for a real upload: a rule appears in
 * `validity` or it does not. This class only decides how to show that, so the
 * checker cannot drift away from what the site actually does - which would be
 * worse than having no checker, because people would trust it.
 */
class DemoSettingsChecker
{
    /**
     * The rules, in the order somebody should read them.
     *
     * `pmove_fixed` first because it is the one people get wrong, and the only
     * one with two right answers.
     */
    private const RULES = [
        'pmove_fixed',
        'com_maxfps',
        'sv_fps',
        'pmove_msec',
        'timescale',
        'g_speed',
        'g_gravity',
        'g_knockback',
        'handicap',
        'sv_cheats',
        'df_mp_interferenceoff',
        'g_killWallbug',
    ];

    public function __construct(private SubmissionValidator $validator)
    {
    }

    /**
     * @return array{ok: bool, rules: array, run: array, summary: ?string, unknown: int}
     */
    public function check(string $path): array
    {
        $meta = $this->parse($path);
        $settings = array_change_key_case((array) ($meta['settings'] ?? []));
        $validity = (array) ($meta['validity'] ?? []);
        $broken = array_change_key_case($validity);

        $rules = [];

        foreach (self::RULES as $cvar) {
            $key = strtolower($cvar);

            // The parser's own answer, not a second opinion. A cvar the demo
            // does not carry is `unknown`, never a failure: plenty of demos
            // simply do not record handicap or g_killWallbug, and painting
            // those red would send people hunting for a problem they do not
            // have.
            $state = match (true) {
                array_key_exists($key, $broken) => 'bad',
                array_key_exists($key, $settings) => 'ok',
                default => 'unknown',
            };

            $rules[] = [
                'cvar' => $cvar,
                'needed' => $cvar === 'pmove_fixed'
                    ? __('1, or g_synchronousClients 1')
                    : (SubmissionValidator::EXPECTED[$cvar] ?? '?'),
                'found' => $settings[$key] ?? null,
                'state' => $state,
            ];
        }

        // Shown beside pmove_fixed, because "pmove_fixed 0" on its own reads as
        // a mistake even when it is the dfcomp ruleset being followed exactly.
        $rules[0]['companion'] = $settings['g_synchronousclients'] ?? null;

        $flagged = array_diff_key($validity, array_flip(['client_finish', 'tool_assisted']));

        return [
            'ok' => $validity === [],
            'rules' => $rules,
            'unknown' => count(array_filter($rules, fn ($r) => $r['state'] === 'unknown')),
            'run' => [
                'map' => $meta['map_name'] ?? null,
                'physics' => $meta['physics'] ?? null,
                'player' => $meta['player_name'] ?? null,
                'time_ms' => isset($meta['time_seconds']) ? (int) round($meta['time_seconds'] * 1000) : null,
                'engine' => $settings['version'] ?? null,
                'defrag' => $settings['defrag_vers'] ?? null,
            ],
            'summary' => $validity
                ? $this->validator->validityReason(new UploadedDemo(['validity' => $validity]))
                : null,
            'other' => array_keys(array_diff_key($flagged, array_flip(array_map('strval', self::RULES)))),
        ];
    }

    /**
     * @throws \RuntimeException when the file is not a demo this can read
     */
    private function parse(string $path): array
    {
        $script = app_path('Services/DemoProcessor/bin/process_single_demo.py');

        $process = new Process(
            ['python3', '-W', 'ignore', $script, $path, '--json'],
            dirname($script),
        );

        // Short on purpose. A settings check runs while somebody waits, and
        // the answer lives in the first kilobytes of the file - a demo that
        // needs longer than this is not one to hold a request open for.
        $process->setTimeout(30);
        $process->run();

        $meta = json_decode(trim($process->getOutput()), true);

        if (! is_array($meta) || ! array_key_exists('settings', $meta)) {
            Log::info('[demo-check] unreadable file', [
                'exit' => $process->getExitCode(),
                'stderr' => mb_substr($process->getErrorOutput(), 0, 300),
            ]);

            throw new \RuntimeException(__('This file could not be read as a Quake 3 demo.'));
        }

        return $meta;
    }
}
