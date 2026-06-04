<?php

namespace App\Support;

/**
 * Render a Quake 3 colour-coded string ("^1red ^2green") to HTML using the
 * q3c-* classes the Filament admin theme already defines (resources/css/
 * filament/admin/theme.css). Faithful port of the frontend q3tohtml()
 * (resources/js/app.js) so colours match the rest of the site.
 *
 * Output is escaped per segment, so it is safe to render with {!! !!} for
 * untrusted chat content.
 */
class Q3Color
{
    public static function toHtml(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        $result = '';
        $color = '7';
        $buffer = '';
        $len = strlen($name);

        $flush = function () use (&$result, &$buffer, &$color): void {
            if ($buffer !== '') {
                $result .= '<span class="q3c-' . e($color) . '">' . e($buffer) . '</span>';
                $buffer = '';
            }
        };

        for ($i = 0; $i < $len; $i++) {
            $ch = $name[$i];

            if ($ch === '^') {
                $next = ($i + 1 < $len) ? $name[$i + 1] : '';
                if ($next === '^') {
                    $buffer .= '^';
                } else {
                    $flush();
                    $color = $next;
                    $i++;
                }
            } else {
                $buffer .= $ch;
            }
        }

        $flush();

        return $result;
    }
}
