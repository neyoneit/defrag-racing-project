<?php

namespace App\Filament\Pages\Concerns;

trait ValidatesStoragePath
{
    protected function validateName(string $name): void
    {
        if ($name === '' || $name === '.' || $name === '..') {
            abort(400, 'Invalid name');
        }

        if (str_contains($name, '/') || str_contains($name, "\0")) {
            abort(400, 'Invalid name');
        }

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
            $this->validateName($segment);
        }
    }

    protected function joinPath(string $base, string $name): string
    {
        $base = trim($base, '/');

        return $base === '' ? $name : $base . '/' . $name;
    }
}
