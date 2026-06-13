<?php

use App\Platform\I18n\SupportedLocale;

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | The launch-supported locales and the final fallback, derived from the
    | App\Platform\I18n\SupportedLocale enum — the single source of truth (the
    | "typed anchor", design D4). The enum holds the cases; this file exposes their
    | array form for config-style access. Change the set in ONE place: add an enum
    | case and its lang/<locale>/ resources — never restate it here, and never a
    | schema migration (DEC-031: "adding a locale post-launch is configuration, not
    | migration"). config/app.php already sets locale/fallback_locale to 'en'.
    |
    | See design D4; i18n capability — Requirement: Supported Locales.
    |
    */

    'supported' => SupportedLocale::values(),

    'fallback' => SupportedLocale::fallback()->value,

];
