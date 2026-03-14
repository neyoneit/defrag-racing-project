<?php
// Usage: COOKIE='<raw cookie value>' php decode_session_cookie.php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$cookie = getenv('COOKIE');
if (! $cookie && file_exists('/tmp/last_session_cookie')) {
    $cookie = file_get_contents('/tmp/last_session_cookie');
}

if (! $cookie) {
    echo "Set COOKIE env var or create /tmp/last_session_cookie with the raw cookie value (URL-decoded if needed)\n";
    exit(2);
}

// URL-decode in case it was taken from a Cookie header
$cookie = rawurldecode($cookie);

$encrypter = $app->make(Illuminate\Contracts\Encryption\Encrypter::class);
try {
    $sessionId = $encrypter->decrypt($cookie);
} catch (Throwable $e) {
    echo "Failed to decrypt cookie: " . $e->getMessage() . "\n";
    exit(3);
}

echo "Session ID: $sessionId\n";

$row = DB::table('sessions')->where('id', $sessionId)->first();
if (! $row) {
    echo "No session row for id\n";
    exit(4);
}

echo "Found session last_activity={$row->last_activity}\n";

$payload = $row->payload;
// Try to decode payload: often it's base64-encoded serialized data
$decoded = base64_decode($payload);
if ($decoded !== false) {
    $un = @unserialize($decoded);
    if ($un !== false) {
        echo "Unserialized session payload:\n";
        print_r($un);
        // Try to find csrf token in common locations
        if (isset($un['_token'])) {
            echo "Session _token: " . $un['_token'] . "\n";
        } elseif (isset($un['csrf_token'])) {
            echo "Session csrf_token: " . $un['csrf_token'] . "\n";
        }
        exit(0);
    }
}

echo "Could not decode/unserialize payload. Dumping raw payload (truncated):\n";
echo substr($payload, 0, 400) . "\n";
