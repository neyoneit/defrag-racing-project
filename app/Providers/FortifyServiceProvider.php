<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function django_hash_password ($password, $salt, $iterations = 260000) {
        $hash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

        $encoded_hash = base64_encode($hash);

        return "pbkdf2_sha256$" . $iterations . "$" . $salt . "$" . $encoded_hash;
    }

    public function django_verify_password ($password, $old_hash) {
        // Defensive checks: old_hash must be a non-empty string and match the
        // expected Django pbkdf2 format: algorithm$iterations$salt$encoded_hash
        if (empty($old_hash) || !is_string($old_hash)) {
            return false;
        }

        // Limit to 4 parts to avoid unexpected splits in salt containing '$'
        $parts = explode('$', $old_hash, 4);
        if (count($parts) < 4) {
            return false;
        }

        list($algorithm, $iterations, $salt, $encoded_hash) = $parts;

        if ($algorithm !== 'pbkdf2_sha256') {
            return false;
        }

        if (!ctype_digit((string) $iterations) || (int) $iterations <= 0) {
            return false;
        }

        // Recompute the formatted hash string using the same algorithm/signature
        $hashed_password = $this->django_hash_password($password, $salt, (int)$iterations);

        return hash_equals($hashed_password, $old_hash);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
        
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('username', $request->username)->first();

            if (! $user) {
                return null;
            }

            // If password is explicitly set to the sentinel 'NOPASS', fall back to
            // verifying against the legacy Django hash stored in `oldhash`.
            if ($user->password === 'NOPASS') {
                if (! $this->django_verify_password($request->password, $user->oldhash)) {
                    return null;
                }

                // Migrate the verified password to Laravel's bcrypt storage.
                $user->password = Hash::make($request->password);
                $user->save();

                return $user;
            }

            // Some legacy accounts may have the Django pbkdf2 hash stored directly
            // in the `password` column (or other non-bcrypt string). Avoid calling
            // Hash::check on values that are not bcrypt hashes because the
            // Bcrypt hasher will raise an exception for unsupported formats.
            $pw = $user->password ?? '';

            $looksLikeBcrypt = str_starts_with($pw, '$2y$') || str_starts_with($pw, '$2a$') || str_starts_with($pw, '$2b$');

            if (! $looksLikeBcrypt) {
                // Use the oldhash if present, otherwise try the password field as a
                // legacy Django-style hash (e.g. "pbkdf2_sha256$...").
                $legacyHash = $user->oldhash ?: $user->password;

                if (! $legacyHash) {
                    return null;
                }

                if (! $this->django_verify_password($request->password, $legacyHash)) {
                    return null;
                }

                // Migrate to bcrypt for future logins.
                $user->password = Hash::make($request->password);
                $user->save();

                return $user;
            }

            // Normal bcrypt password path.
            return Hash::check($request->password, $user->password) ? $user : null;
        });
    }
}
