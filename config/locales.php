<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Languages the site can be read in
    |--------------------------------------------------------------------------
    |
    | The key is the locale code and the file name under lang/, the value is
    | the language's own name for itself - a Czech speaker looks for
    | "Čeština", not for "Czech", and someone who lands on a language they
    | cannot read has to be able to find their way out of it.
    |
    | English is the source language. Its strings are written into the
    | templates directly, so there is no lang/en.json and there never needs to
    | be one; every other locale is a lang/<code>.json that overrides the
    | English it has a translation for and falls through to English for the
    | rest. That is what makes an unfinished translation safe to ship.
    |
    */

    'supported' => [
        'en' => 'English',
        'cs' => 'Čeština',
        'ru' => 'Русский',
        'pl' => 'Polski',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
        'uk' => 'Українська',
        'nl' => 'Nederlands',
        'sv' => 'Svenska',
    ],

];
