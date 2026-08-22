<?php

namespace App\Http\Controllers;

use App\Models\ServerOwnerApplication;
use App\Models\SftpCredential;
use App\Services\StorageVpsProvisioner;
use App\Support\HostIdentity;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use RuntimeException;

class ServerHostingController extends Controller
{
    /** Hard cap of active credentials per user - one per VPS is the intent. */
    private const MAX_CREDENTIALS = 8;

    /**
     * Show the applicant's current state - one of:
     *   - no application yet (show form)
     *   - latest application pending / rejected
     *   - one or more active credentials issued (show each host/user/path)
     *   - revoked credential (show "apply again" hint)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $application = ServerOwnerApplication::where('user_id', $user->id)
            ->latest()
            ->first();

        // A user can hold several credentials - typically one per VPS -
        // each with its own SFTP account, password and declared servers.
        $credentials = SftpCredential::where('user_id', $user->id)
            ->active()
            ->orderBy('id')
            ->get()
            ->map(function (SftpCredential $credential) {
                $payload = $credential->only(['id', 'label', 'sftp_username', 'host', 'port', 'remote_path', 'status', 'servers']);
                // admin_note is admin-only metadata; never leak it to the user.
                $payload['servers'] = array_map(
                    fn ($s) => collect($s)->except('admin_note')->all(),
                    $payload['servers'] ?? []
                );
                // 'encrypted' cast auto-decrypts on read. Surface to the
                // page once - the front-end will POST to acknowledge once
                // the user confirms they've copied it, which nulls the DB.
                try {
                    $payload['pending_password'] = $credential->password_pending;
                } catch (DecryptException) {
                    // Row encrypted under a different APP_KEY (key rotation,
                    // prod dump in local dev) - treat as "no pending password"
                    // instead of 500ing the whole page.
                    $payload['pending_password'] = null;
                }

                return $payload;
            })
            ->values();

        return Inertia::render('ServerHosting', [
            'application' => $application,
            'credentials' => $credentials,
            'countries'   => \App\Support\Countries::options(),
        ]);
    }

    public function acknowledgePassword(Request $request)
    {
        $request->validate([
            'credential_id' => ['required', 'integer'],
        ]);

        $credential = $this->ownedActiveCredential($request);

        $credential->update(['password_pending' => null]);

        return back();
    }

    /**
     * User-initiated password rotation. Same VPS call as the admin's
     * Reset action, but throttled per-user so a compromised account
     * can't flap credentials infinitely.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'credential_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $credential = $this->ownedActiveCredential($request);

        $rateKey = 'sftp-reset:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 6)) {
            $retry = RateLimiter::availableIn($rateKey);
            return back()->with(
                'danger',
                "Too many reset attempts. Try again in {$retry} seconds."
            );
        }
        RateLimiter::hit($rateKey, 3600); // 6 per hour, shared across credentials

        try {
            $response = app(StorageVpsProvisioner::class)
                ->resetPassword($credential->sftp_username);

            $credential->update([
                'password_pending' => $response['password'],
            ]);

            return back()->with('success', 'Password rotated. Copy the new one from the box above - it is shown only once.');
        } catch (RuntimeException $e) {
            Log::error('User-initiated SFTP password reset failed', [
                'credential_id' => $credential->id,
                'user_id'       => $user->id,
                'error'         => $e->getMessage(),
            ]);

            return back()->with(
                'danger',
                'Password reset failed on the storage VPS. Please ask an admin to investigate.'
            );
        }
    }

    public function apply(Request $request)
    {
        $request->validate([
            'message'                 => ['required', 'string', 'min:20', 'max:2000'],
            'servers'                 => ['required', 'array', 'min:1', 'max:20'],
            'servers.*.gametype'      => ['required', 'string', 'in:mixed,cpm,vq3,teamruns,fastcaps,freestyle'],
            'servers.*.ip'            => ['required', 'string', 'max:255'],
            'servers.*.port'          => ['required', 'integer', 'between:1,65535'],
            'servers.*.rcon'          => ['required', 'string', 'max:255'],
            'servers.*.location'      => ['nullable', 'string', 'size:2', 'alpha', \Illuminate\Validation\Rule::in(\App\Support\Countries::CODES)],
            // Consent to the server hosting rules shown on the page is
            // a hard requirement - the timestamp is stored on the row.
            'rules_accepted'          => ['required', 'accepted'],
        ]);

        $user = $request->user();

        // One open application at a time - block resubmission if the
        // most recent is still pending OR already approved & active.
        $existing = ServerOwnerApplication::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->latest()
            ->first();

        if ($existing) {
            return back()->withErrors([
                'message' => match ($existing->status) {
                    'pending'  => 'You already have an application pending review.',
                    'approved' => 'You already have an approved application. Use your existing credentials, or add another VPS credential from this page.',
                    default    => 'Application already exists.',
                },
            ]);
        }

        // Approving this application seeds ONE credential with every server
        // listed here, and one SFTP account is one machine - so the list has
        // to be one machine too. Somebody with two boxes applies for the
        // first and requests a second credential once approved.
        $hosts = collect($request->input('servers'))->pluck('ip')->map(fn ($h) => (string) $h);
        $first = trim((string) $hosts->first());

        if (HostIdentity::isUnresolvable($first)) {
            return back()->withErrors([
                'servers.0.ip' => "Could not resolve {$first}. Check the spelling, or enter the server's IP address instead.",
            ]);
        }

        foreach ($hosts as $index => $host) {
            if (! HostIdentity::sameMachine($first, trim($host))) {
                return back()->withErrors([
                    "servers.{$index}.ip" => "Every server in one application has to be on the same machine as the first one ({$first}). Apply for that box now - once you are approved you can request a credential for each further machine.",
                ]);
            }
        }

        ServerOwnerApplication::create([
            'user_id'           => $user->id,
            'message'           => $request->input('message'),
            'server_info'       => $request->input('servers'),
            'rules_accepted_at' => now(),
            'status'            => 'pending',
        ]);

        return back()->with('success', 'Application submitted. Admins will review it shortly.');
    }

    /**
     * Append a new server entry to one of an approved user's credentials.
     * Does NOT touch SFTP provisioning - the user reuses that credential's
     * account on the storage VPS. Only the per-server metadata
     * (gametype/ip/port/rcon/location) grows. rs_code is left null and
     * admins fill it in from Filament after light review.
     */
    public function addServer(Request $request)
    {
        $request->validate([
            'credential_id' => ['required', 'integer'],
            'gametype'      => ['required', 'string', 'in:mixed,cpm,vq3,teamruns,fastcaps,freestyle'],
            'ip'            => ['required', 'string', 'max:255'],
            'port'          => ['required', 'integer', 'between:1,65535'],
            'rcon'          => ['required', 'string', 'max:255'],
            'location'      => ['nullable', 'string', 'size:2', 'alpha', \Illuminate\Validation\Rule::in(\App\Support\Countries::CODES)],
        ]);

        $user = $request->user();
        $credential = $this->ownedActiveCredential($request);

        // 10/hour turned out to be too tight: a single box can legitimately
        // host ~20 servers (the EU 10gbit box runs 17), and declaring them all
        // right after provisioning is the normal first-time flow.
        $rateKey = 'sftp-add-server:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 40)) {
            $retry = RateLimiter::availableIn($rateKey);
            return back()->with('danger', "Too many additions. Try again in {$retry} seconds.");
        }
        RateLimiter::hit($rateKey, 3600);

        // Normalised, so a host pasted as "box.example.com:27960" or as a
        // URL is stored the way every other row stores it - the port lives
        // in its own column and a duplicate has to be recognisable.
        $ip   = HostIdentity::normalize((string) $request->input('ip'));
        $port = (int) $request->input('port');

        // A name nobody can resolve cannot be checked against anything, and
        // it is a typo far more often than it is a real box.
        if (HostIdentity::isUnresolvable($ip)) {
            return back()->withErrors([
                'ip' => "Could not resolve {$ip}. Check the spelling, or enter the server's IP address instead.",
            ]);
        }

        // A server can only ship demos through one credential - dedupe
        // across ALL of the user's active credentials, not just this one.
        $siblings = SftpCredential::where('user_id', $user->id)->active()->get();
        foreach ($siblings as $sibling) {
            foreach ($sibling->servers ?? [] as $existing) {
                $existingIp = (string) ($existing['ip'] ?? '');

                if ($existingIp === $ip && (int) ($existing['port'] ?? 0) === $port) {
                    return back()->withErrors([
                        'ip' => "You already have a server registered at {$ip}:{$port}.",
                    ]);
                }

                // One box keeps one SFTP account, so a machine already
                // declared under another credential cannot be declared
                // again here - otherwise the same box ships demos through
                // two accounts and neither one accounts for it.
                if ($sibling->id !== $credential->id && HostIdentity::sameMachine($existingIp, $ip)) {
                    $name = $sibling->label ?: $sibling->sftp_username;

                    return back()->withErrors([
                        'ip' => "That machine is already registered under \"{$name}\". Add the server there - one machine keeps one SFTP account.",
                    ]);
                }
            }
        }

        // One SFTP account is one machine. Somebody declared a domain and
        // then a different server's address under the same account, which
        // is how a box nobody approved starts shipping demos through
        // somebody else's credential. The comparison resolves names, so a
        // domain and the address it points at count as the same machine
        // and a second server on the same box is still fine.
        $declared = collect($credential->servers ?? [])
            ->pluck('ip')
            ->filter()
            ->values();

        if ($declared->isNotEmpty() && ! $declared->contains(fn ($host) => HostIdentity::sameMachine((string) $host, $ip))) {
            return back()->withErrors([
                'ip' => "This SFTP account is for {$declared->first()}. A server on a different machine needs its own credential - request one from this page and add it there.",
            ]);
        }

        $servers   = $credential->servers ?? [];
        $servers[] = [
            'gametype' => $request->input('gametype'),
            'ip'       => $ip,
            'port'     => $port,
            'rcon'     => $request->input('rcon'),
            'location' => $request->input('location'),
            'rs_code'  => null,
        ];

        $credential->update(['servers' => $servers]);

        return back()->with('success', 'Server added. Admin will assign an RS code shortly.');
    }

    /**
     * Self-service provisioning of an ADDITIONAL credential for an already
     * approved server owner - one per VPS, so each box gets its own SFTP
     * login and a compromise/rotation on one doesn't touch the others.
     * The first credential still comes from admin approval; this only
     * works when at least one active credential already exists.
     */
    public function requestCredential(Request $request)
    {
        $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:40'],
        ]);

        $user = $request->user();

        $active = SftpCredential::where('user_id', $user->id)->active()->get();

        if ($active->isEmpty()) {
            return back()->with('danger', 'No active credential - you must be an approved server owner before adding more.');
        }

        if ($active->count() >= self::MAX_CREDENTIALS) {
            return back()->with('danger', 'Credential limit reached (' . self::MAX_CREDENTIALS . '). Ask an admin if you really need more.');
        }

        $rateKey = 'sftp-new-credential:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $retry = RateLimiter::availableIn($rateKey);
            return back()->with('danger', "Too many new credentials. Try again in {$retry} seconds.");
        }
        RateLimiter::hit($rateKey, 3600); // 3 per hour

        $label    = trim($request->input('label'));
        $username = $this->uniqueUsername($user, $label);

        try {
            $response = app(StorageVpsProvisioner::class)->create($username);

            $credential = SftpCredential::create([
                'user_id'          => $user->id,
                'application_id'   => ServerOwnerApplication::where('user_id', $user->id)
                    ->where('status', 'approved')->latest()->value('id'),
                'label'            => $label,
                'sftp_username'    => $response['username'],
                'host'             => $response['host'],
                'port'             => $response['port'],
                'remote_path'      => $response['remote_path'],
                'password_pending' => $response['password'],
                'servers'          => [],
                'status'           => 'active',
                'provisioned_at'   => now(),
                'provisioned_by'   => null, // self-service, not admin-issued
            ]);

            // Verify the account end-to-end right away. Never throws;
            // a failure lands as check_status='failed' on the row (red
            // badge in Filament) plus a warning in the log - the user
            // still gets their password either way.
            app(\App\Services\SftpCredentialChecker::class)->check($credential);

            return back()->with('success', "New SFTP account \"{$label}\" provisioned. Copy its password now - it is shown only once.");
        } catch (RuntimeException $e) {
            Log::error('Self-service SFTP credential provisioning failed', [
                'user_id' => $user->id,
                'label'   => $label,
                'error'   => $e->getMessage(),
            ]);

            return back()->with(
                'danger',
                'Provisioning failed on the storage VPS. Please ask an admin to investigate.'
            );
        }
    }

    /**
     * Resolve the credential_id from the request to a credential that is
     * active AND owned by the requesting user - 404s otherwise so users
     * can never act on someone else's credential.
     */
    private function ownedActiveCredential(Request $request): SftpCredential
    {
        return SftpCredential::where('user_id', $request->user()->id)
            ->active()
            ->where('id', (int) $request->input('credential_id'))
            ->firstOrFail();
    }

    /**
     * Derive a unique SFTP username from the user's name plus the
     * credential label, e.g. "neyo-usa". Falls back to numeric suffixes
     * on collision. Result always satisfies the provisioner's
     * ^[a-z][a-z0-9_-]{2,31}$ contract.
     */
    private function uniqueUsername($user, string $label): string
    {
        $base = StorageVpsProvisioner::suggestUsername(
            $user->username ?? $user->name ?? 'user' . $user->id
        );

        $slug = substr(trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($label)), '-'), 0, 12);
        $slug = trim($slug, '-');

        $candidate = $slug !== '' ? "{$base}-{$slug}" : $base;
        $candidate = rtrim(substr($candidate, 0, 32), '-_');

        if (!preg_match('/^[a-z][a-z0-9_-]{2,31}$/', $candidate)) {
            $candidate = $base;
        }

        $username = $candidate;
        $suffix   = 2;
        while (SftpCredential::where('sftp_username', $username)->exists()) {
            $tail     = '-' . $suffix++;
            $username = rtrim(substr($candidate, 0, 32 - strlen($tail)), '-_') . $tail;
        }

        return $username;
    }
}
