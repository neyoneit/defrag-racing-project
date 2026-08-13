<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    /**
     * A year - the language you read the site in is not a session preference,
     * and a guest who picked one should not be back in English tomorrow.
     */
    public const COOKIE = 'locale';

    public const COOKIE_MINUTES = 60 * 24 * 365;

    public function handle(Request $request, Closure $next)
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    /**
     * A saved choice always wins over a guessed one, and the guess is only
     * ever the browser's own Accept-Language. Anything unknown falls through
     * to English rather than erroring - a locale can be dropped from the
     * config while people still carry its cookie.
     */
    private function resolve(Request $request): string
    {
        $supported = array_keys(config('locales.supported'));

        $chosen = $request->user()?->locale;

        if (is_string($chosen) && in_array($chosen, $supported, true)) {
            return $chosen;
        }

        $cookie = $request->cookie(self::COOKIE);

        if (is_string($cookie) && in_array($cookie, $supported, true)) {
            return $cookie;
        }

        return $this->fromBrowser($request, $supported) ?? config('app.locale');
    }

    /**
     * Match on the language alone: someone sending cs-CZ, and someone sending
     * plain cs, want the same file, and we do not translate per region.
     */
    private function fromBrowser(Request $request, array $supported): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $short = strtolower(substr(str_replace('_', '-', $language), 0, 2));

            if (in_array($short, $supported, true)) {
                return $short;
            }
        }

        return null;
    }
}
