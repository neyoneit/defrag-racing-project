<?php

use App\Models\DefragliveContest;
use App\Models\DefragliveWatchSession;
use App\Services\DefragliveWatchService;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

test('leaderboard credits only the session time inside the contest window', function () {
    Carbon::setTestNow('2026-07-10 12:00:00');

    $contest = DefragliveContest::create([
        'title' => 'Boundary test',
        'starts_at' => '2026-07-10 10:00:00',
        'ends_at' => '2026-07-10 11:00:00',
        'status' => DefragliveContest::STATUS_ACTIVE,
    ]);

    foreach ([
        ['2026-07-10 09:50:00', '2026-07-10 10:10:00'],
        ['2026-07-10 10:50:00', '2026-07-10 11:10:00'],
        ['2026-07-10 11:10:00', '2026-07-10 11:20:00'],
    ] as [$start, $end]) {
        DefragliveWatchSession::create([
            'player_name' => '^1Runner',
            'player_name_clean' => 'runner',
            'seconds' => 1200,
            'started_at' => $start,
            'last_seen_at' => $end,
            'ended_at' => $end,
        ]);
    }

    $entries = app(DefragliveWatchService::class)->leaderboard($contest, null);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['seconds'])->toBe(1200)
        ->and($entries[0]['tickets'])->toBe(20);
});

test('leaderboard counts an open session live and clips its start to the contest', function () {
    Carbon::setTestNow('2026-07-10 10:30:00');

    $contest = DefragliveContest::create([
        'title' => 'Open session test',
        'starts_at' => '2026-07-10 10:00:00',
        'ends_at' => '2026-07-10 11:00:00',
        'status' => DefragliveContest::STATUS_ACTIVE,
    ]);

    DefragliveWatchSession::create([
        'player_name' => '^2LiveRunner',
        'player_name_clean' => 'liverunner',
        'seconds' => 0,
        'started_at' => '2026-07-10 09:50:00',
        'last_seen_at' => '2026-07-10 10:29:00',
        'ended_at' => null,
    ]);

    $entries = app(DefragliveWatchService::class)->leaderboard($contest, null);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['seconds'])->toBe(1800)
        ->and($entries[0]['tickets'])->toBe(30);
});
