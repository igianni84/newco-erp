<?php

// Welcome / holding-page copy (foundations-money-i18n-flags — task 2.4 renders it; design D4).
//
// English is the authored baseline AND the final fallback (DEC-127): every key the app
// introduces is defined here in full. The other five supported locales
// (lang/{it,fr,de,ja,zh_Hans}/welcome.php) MAY cover a subset and fall back here per key —
// Laravel resolves the chain [active-locale, en] for each key, so a key absent in another
// locale silently uses the English value (AC-0-XM-4 allows partial coverage; CLAUDE.md
// invariant 12 — no hardcoded user-facing strings).
//
// Convention: PHP-array group files with dotted keys (__('welcome.headline')), not JSON.
// See docs/i18n.md for why and for how to add a locale.

return [
    'headline' => 'NewCo',
    'tagline' => 'Fine wine, direct from the producers.',
    'coming_soon' => 'Our storefront is being prepared. Please check back soon.',
];
