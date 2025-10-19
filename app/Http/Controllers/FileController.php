<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends Controller
{
    /**
     * Serve files case-insensitively from storage
     * This prevents hundreds of 404s in the browser console
     */
    public function serveFile(Request $request, $path)
    {
        // Decode the path
        $path = urldecode($path);

        // Get the public storage path
        $publicPath = storage_path('app/public');
        $fullPath = $publicPath . '/' . $path;

        // If file exists with exact case, serve it immediately
        if (file_exists($fullPath)) {
            return response()->file($fullPath);
        }

        // Try case-insensitive lookup
        $resolved = $this->findFileCaseInsensitive($publicPath, $path);

        if ($resolved && file_exists($resolved)) {
            return response()->file($resolved);
        }

        // File not found
        abort(404);
    }

    /**
     * Serve baseq3 files case-insensitively
     * All baseq3 model folders are lowercase
     */
    public function serveBaseq3File(Request $request, $path)
    {
        // Decode the path
        $path = urldecode($path);

        // Get the public baseq3 path
        $publicPath = public_path('baseq3');
        $fullPath = $publicPath . '/' . $path;

        // If file exists with exact case, serve it immediately
        if (file_exists($fullPath)) {
            return response()->file($fullPath);
        }

        // Try case-insensitive lookup
        $resolved = $this->findFileCaseInsensitive($publicPath, $path);

        if ($resolved && file_exists($resolved)) {
            return response()->file($resolved);
        }

        // File not found
        abort(404);
    }

    /**
     * Find a file case-insensitively
     */
    private function findFileCaseInsensitive($basePath, $relativePath)
    {
        $parts = explode('/', $relativePath);
        $currentPath = $basePath;

        foreach ($parts as $part) {
            if (empty($part)) continue;

            // Check if exact match exists
            $exactPath = $currentPath . '/' . $part;
            if (file_exists($exactPath)) {
                $currentPath = $exactPath;
                continue;
            }

            // Try case-insensitive match
            if (!is_dir($currentPath)) {
                return null;
            }

            $files = scandir($currentPath);
            $found = false;

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;

                if (strcasecmp($file, $part) === 0) {
                    $currentPath .= '/' . $file;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return null;
            }
        }

        return $currentPath;
    }
}
