<?php

namespace App\Http\Controllers;

use App\Models\ServerOwnerApplication;
use App\Models\SftpCredential;
use App\Services\StorageVpsProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use RuntimeException;

class ServerHostingController extends Controller
{
    /**
     * Show the applicant's current state — one of:
     *   - no application yet (show form)
     *   - latest application pending / rejected
     *   - active credential issued (show host/user/path)
     *   - revoked credential (show "apply again" hint)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $application = ServerOwnerApplication::where('user_id', $user->id)
            ->latest()
            ->first();

        $credential = SftpCredential::where('user_id', $user->id)
            ->active()
            ->latest()
            ->first();

        $pendingPassword = null;
        if ($credential && $credential->password_pending) {
            // 'encrypted' cast auto-decrypts on read. Surface to the
            // page once — the front-end will POST to acknowledge once
            // the user confirms they've copied it, which nulls the DB.
            $pendingPassword = $credential->password_pending;
        }

        return Inertia::render('ServerHosting', [
            'application'     => $application,
            'credential'      => $credential
                ? $credential->only(['id', 'sftp_username', 'host', 'port', 'remote_path', 'status', 'servers'])
                : null,
            'pendingPassword' => $pendingPassword,
            'countries'       => \App\Support\Countries::options(),
        ]);
    }

    public function acknowledgePassword(Request $request)
    {
        $credential = SftpCredential::where('user_id', $request->user()->id)
            ->active()
            ->latest()
            ->firstOrFail();

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
        $user = $request->user();

        $credential = SftpCredential::where('user_id', $user->id)
            ->active()
            ->latest()
            ->firstOrFail();

        $rateKey = 'sftp-reset:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $retry = RateLimiter::availableIn($rateKey);
            return back()->with(
                'danger',
                "Too many reset attempts. Try again in {$retry} seconds."
            );
        }
        RateLimiter::hit($rateKey, 3600); // 3 per hour

        try {
            $response = app(StorageVpsProvisioner::class)
                ->resetPassword($credential->sftp_username);

            $credential->update([
                'password_pending' => $response['password'],
            ]);

            return back()->with('success', 'Password rotated. Copy the new one from the box above — it is shown only once.');
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
        ]);

        $user = $request->user();

        // One open application at a time — block resubmission if the
        // most recent is still pending OR already approved & active.
        $existing = ServerOwnerApplication::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->latest()
            ->first();

        if ($existing) {
            return back()->withErrors([
                'message' => match ($existing->status) {
                    'pending'  => 'You already have an application pending review.',
                    'approved' => 'You already have an approved application. Use your existing credentials.',
                    default    => 'Application already exists.',
                },
            ]);
        }

        ServerOwnerApplication::create([
            'user_id'     => $user->id,
            'message'     => $request->input('message'),
            'server_info' => $request->input('servers'),
            'status'      => 'pending',
        ]);

        return back()->with('success', 'Application submitted. Admins will review it shortly.');
    }

    /**
     * Append a new server entry to an already-approved user's credential.
     * Does NOT touch SFTP provisioning — the user reuses their existing
     * account on the storage VPS. Only the per-server metadata
     * (gametype/ip/port/rcon/location) grows. rs_code is left null and
     * admins fill it in from Filament after light review.
     */
    public function addServer(Request $request)
    {
        $request->validate([
            'gametype' => ['required', 'string', 'in:mixed,cpm,vq3,teamruns,fastcaps,freestyle'],
            'ip'       => ['required', 'string', 'max:255'],
            'port'     => ['required', 'integer', 'between:1,65535'],
            'rcon'     => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'size:2', 'alpha', \Illuminate\Validation\Rule::in(\App\Support\Countries::CODES)],
        ]);

        $user = $request->user();

        $credential = SftpCredential::where('user_id', $user->id)
            ->active()
            ->latest()
            ->first();

        if (!$credential) {
            return back()->with('danger', 'No active credential — you must be an approved server owner to add servers.');
        }

        $rateKey = 'sftp-add-server:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $retry = RateLimiter::availableIn($rateKey);
            return back()->with('danger', "Too many additions. Try again in {$retry} seconds.");
        }
        RateLimiter::hit($rateKey, 3600);

        $servers = $credential->servers ?? [];

        $ip   = trim($request->input('ip'));
        $port = (int) $request->input('port');

        foreach ($servers as $existing) {
            if (($existing['ip'] ?? null) === $ip && (int) ($existing['port'] ?? 0) === $port) {
                return back()->withErrors([
                    'ip' => "You already have a server registered at {$ip}:{$port}.",
                ]);
            }
        }

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
}
