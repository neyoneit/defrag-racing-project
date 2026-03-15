<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        if (! $user->mdd_id || ! ctype_digit($user->mdd_id)) {
            return $request->wantsJson()
                ? response()->json(['two_factor' => false])
                : redirect()->intended('/link-account');
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended('/');
    }
}
