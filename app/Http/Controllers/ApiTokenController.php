<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * JSON API for the api-tokens partial form in profile settings.
 * Each token is a Sanctum personal access token whose `name` is prefixed
 * with "api:" so we can filter for API tokens without adding another
 * column to the shared personal_access_tokens table. Mirrors the launcher
 * tokens flow — same pattern, different prefix + ability.
 */
class ApiTokenController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $tokens = $user->tokens()
            ->where('name', 'like', 'api:%')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'label'        => substr($t->name, strlen('api:')),
                'last_used_at' => $t->last_used_at,
                'created_at'   => $t->created_at,
            ]);

        return response()->json(['tokens' => $tokens]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:50',
        ]);

        $user = Auth::user();
        $token = $user->createToken('api:' . $data['label'], ['api:read']);

        return response()->json([
            'id'           => $token->accessToken->id,
            'label'        => $data['label'],
            'created_at'   => $token->accessToken->created_at,
            'last_used_at' => null,
            // Plaintext token — only returned once, never persisted in cleartext.
            'plain_text_token' => $token->plainTextToken,
        ]);
    }

    public function destroy(Request $request, int $tokenId)
    {
        $user = Auth::user();

        $token = $user->tokens()
            ->where('id', $tokenId)
            ->where('name', 'like', 'api:%')
            ->firstOrFail();

        $token->delete();

        return response()->json(['ok' => true]);
    }
}
