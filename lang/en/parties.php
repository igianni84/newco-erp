<?php

// Parties (Module K) operator-facing copy — domain-rejection reasons surfaced by the creation Actions
// (parties-core; CLAUDE.md invariant 12 — no hardcoded user-facing strings).
//
// English is the authored baseline AND the final fallback (DEC-127): every key is defined here in full.
// The other five supported locales (lang/{it,fr,de,ja,zh_Hans}/parties.php) MAY cover a subset and fall
// back here per key — Laravel resolves the chain [active-locale, en] for each key. Convention: PHP-array
// group files with dotted keys (__('parties.club.…')), :producer placeholders replaced from the call site.
// See docs/i18n.md.

return [
    'club' => [
        // BR-K-Club-1 rejection (design D3/D4): a Club requires exactly one EXISTING operating Producer.
        // :producer is the operator-facing producer-id reference (not PII).
        'missing_producer' => 'Cannot create a Club: no operating Producer exists for reference :producer. A Club requires exactly one existing operating Producer.',
    ],
];
