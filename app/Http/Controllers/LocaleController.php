<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * The cookie is written for everyone, signed in or not, so the choice
     * survives signing out and so a guest gets one at all. The column is the
     * one that matters for a logged-in reader, because it follows them to
     * another machine and is what anything sent to them can be written in.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'locale' => [
                'required',
                'string',
                'in:' . implode(',', array_keys(config('locales.supported'))),
            ],
        ]);

        $request->user()?->forceFill(['locale' => $validated['locale']])->save();

        Cookie::queue(SetLocale::COOKIE, $validated['locale'], SetLocale::COOKIE_MINUTES);

        return back();
    }
}
