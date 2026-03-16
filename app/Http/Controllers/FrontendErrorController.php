<?php

namespace App\Http\Controllers;

use App\Models\FrontendError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FrontendErrorController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:js_error,api_error,vue_error',
            'message' => 'required|string|max:1000',
            'stack' => 'nullable|string|max:5000',
            'url' => 'required|string|max:500',
            'endpoint' => 'nullable|string|max:500',
            'status_code' => 'nullable|integer',
            'request_data' => 'nullable|string|max:5000',
            'response_data' => 'nullable|string|max:5000',
            'component' => 'nullable|string|max:200',
        ]);

        FrontendError::create([
            'user_id' => Auth::id(),
            'type' => $request->type,
            'message' => $request->message,
            'stack' => $request->stack,
            'url' => $request->url,
            'endpoint' => $request->endpoint,
            'status_code' => $request->status_code,
            'request_data' => $request->request_data,
            'response_data' => $request->response_data,
            'component' => $request->component,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        return response()->json(['ok' => true]);
    }
}
