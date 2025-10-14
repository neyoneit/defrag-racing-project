<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WikiController extends Controller
{
    /**
     * Proxy all requests to MediaWiki container
     */
    public function proxy(Request $request, $path = '')
    {
        $wikiHost = 'http://mediawiki:80';
        $fullPath = $path ? "/{$path}" : '';
        $queryString = $request->getQueryString();
        $url = $wikiHost . $fullPath . ($queryString ? "?{$queryString}" : '');

        try {
            // Forward the request to MediaWiki
            $response = Http::withHeaders([
                'X-Forwarded-For' => $request->ip(),
                'X-Forwarded-Host' => $request->getHost(),
                'X-Forwarded-Proto' => $request->getScheme(),
            ])
            ->timeout(30)
            ->send($request->method(), $url, [
                'body' => $request->getContent(),
                'headers' => $this->getProxyHeaders($request),
            ]);

            // Return the response from MediaWiki
            return response($response->body(), $response->status())
                ->withHeaders($this->filterResponseHeaders($response->headers()));

        } catch (\Exception $e) {
            return response('Wiki temporarily unavailable. Please try again later.', 503);
        }
    }

    /**
     * Get headers to forward to MediaWiki
     */
    private function getProxyHeaders(Request $request): array
    {
        $headers = [];

        // Forward important headers
        $forwardHeaders = [
            'Content-Type',
            'Content-Length',
            'Cookie',
            'Accept',
            'Accept-Language',
            'User-Agent',
        ];

        foreach ($forwardHeaders as $header) {
            if ($request->hasHeader($header)) {
                $headers[$header] = $request->header($header);
            }
        }

        return $headers;
    }

    /**
     * Filter response headers from MediaWiki
     */
    private function filterResponseHeaders(array $headers): array
    {
        $filtered = [];

        // Headers to forward back to client
        $allowedHeaders = [
            'Content-Type',
            'Content-Length',
            'Set-Cookie',
            'Cache-Control',
            'Expires',
            'Last-Modified',
            'ETag',
        ];

        foreach ($allowedHeaders as $header) {
            $headerLower = strtolower($header);
            if (isset($headers[$headerLower])) {
                $filtered[$header] = $headers[$headerLower];
            }
        }

        return $filtered;
    }
}
