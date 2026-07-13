<?php

namespace App\Filament\Pages\Concerns;

trait ValidatesStoragePath
{
    /**
     * Anti-traversal check for one path segment of an EXISTING entry
     * (navigation, download, rename source, delete). Deliberately loose about
     * the character set: files on the storage VPS carry names with '+', '(',
     * quotes etc. (community pk3/bsp uploads), and refusing to open or
     * download them just breaks the browser.
     */
    protected function validateSegment(string $name): void
    {
        if ($name === '' || $name === '.' || $name === '..') {
            abort(400, 'Invalid name');
        }

        if (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
            abort(400, 'Invalid name');
        }
    }

    /**
     * Strict charset for names WE create (new folder, rename target): keeps
     * the tree shell- and url-friendly going forward without rejecting the
     * exotic names that already exist.
     */
    protected function validateName(string $name): void
    {
        $this->validateSegment($name);

        if (! preg_match('/^[A-Za-z0-9._\-][A-Za-z0-9._\- ]*$/', $name)) {
            abort(400, 'Name contains forbidden characters');
        }
    }

    protected function validatePath(string $path): void
    {
        $path = trim($path, '/');

        if ($path === '') {
            return;
        }

        foreach (explode('/', $path) as $segment) {
            $this->validateSegment($segment);
        }
    }

    protected function joinPath(string $base, string $name): string
    {
        $base = trim($base, '/');

        return $base === '' ? $name : $base . '/' . $name;
    }
}
