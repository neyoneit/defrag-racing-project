<?php
// boots the app and dumps last 10 sessions as JSON
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('sessions')->orderBy('last_activity', 'desc')->limit(10)->get();
echo json_encode($rows->map(function($r){
    return [
        'id' => $r->id,
        'payload' => base64_encode($r->payload),
        'last_activity' => $r->last_activity,
    ];
})->toArray(), JSON_PRETTY_PRINT);
