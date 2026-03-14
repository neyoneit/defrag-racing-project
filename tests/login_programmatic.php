<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

$client = new Client(["base_uri" => "http://localhost", "http_errors" => false]);
$jar = new CookieJar();

// GET /login to populate cookies
$res = $client->request('GET', '/login', [ 'cookies' => $jar ]);
echo "GET /login -> " . $res->getStatusCode() . "\n";

// find XSRF-TOKEN cookie
$xsrf = null;
foreach ($jar->toArray() as $cookie) {
    if (isset($cookie['Name']) && $cookie['Name'] === 'XSRF-TOKEN') {
        $xsrf = $cookie['Value'];
        break;
    }
}

if (! $xsrf) {
    echo "No XSRF-TOKEN cookie found. Cookies: \n";
    var_export($jar->toArray());
    exit(2);
}

// Try to extract CSRF token from HTML form first (hidden input name="_token")
$body = (string) $res->getBody();
$decoded = null;
if (preg_match('/name="_token"\s+value="([^"]+)"/', $body, $m)) {
    $decoded = html_entity_decode($m[1]);
    echo "Found _token in HTML, length=" . strlen($decoded) . " prefix=" . substr($decoded,0,40) . "\n";
} else {
    // URL-decode the token (cookie values are URL-encoded) and send both header and form field
    $decoded = rawurldecode($xsrf);
    echo "Found XSRF cookie token length=" . strlen($xsrf) . " prefix=" . substr($xsrf,0,40) . " decoded_prefix=" . substr($decoded,0,40) . "\n";
}

// Debug: print cookie jar
echo "Cookie jar: \n";
var_export($jar->toArray());
echo "\n";

// POST login with cookie jar and header (send decoded token in header and as _token form param)
$res = $client->request('POST', '/login', [
    'cookies' => $jar,
    'headers' => [ 'X-XSRF-TOKEN' => $decoded, 'X-CSRF-TOKEN' => $decoded ],
    'form_params' => [ '_token' => $decoded, 'username' => 'neyoneit', 'password' => 'projekt', 'remember' => '' ],
    'allow_redirects' => false,
]);

echo "POST /login -> " . $res->getStatusCode() . "\n";
foreach ($res->getHeaders() as $k => $v) {
    echo $k . ": " . implode(', ', $v) . "\n";
}

$body = (string) $res->getBody();
echo "--- body head ---\n" . substr($body,0,1000) . "\n";

// Show session cookie value if set
foreach ($jar->toArray() as $cookie) {
    if ($cookie['Name'] === 'defrag_racing_session') {
        echo "Session cookie present.\n";
    }
}
// Persist the defrag_racing_session cookie to a temp file for server-side inspection
foreach ($jar->toArray() as $cookie) {
    if ($cookie['Name'] === 'defrag_racing_session') {
        @file_put_contents('/tmp/last_session_cookie', $cookie['Value']);
    }
}
