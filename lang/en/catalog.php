<?php

// Catalog (Module 0) operator-facing copy — domain-rejection reasons surfaced by the creation Actions
// (catalog-product-spine; CLAUDE.md invariant 12 — no hardcoded user-facing strings).
//
// English is the authored baseline AND the final fallback (DEC-127): every key is defined here in full.
// The other five supported locales (lang/{it,fr,de,ja,zh_Hans}/catalog.php) MAY cover a subset and fall
// back here per key — Laravel resolves the chain [active-locale, en] for each key (AC-0-XM-4 allows partial
// coverage). Convention: PHP-array group files with dotted keys (__('catalog.product_master.…')), :name
// placeholders replaced from the call site. See docs/i18n.md.

return [
    'product_master' => [
        // BR-Identity-1 dedup rejection (design D6). :name / :appellation / :producer are operator-facing
        // identity (not PII).
        'duplicate_identity' => 'A WINE Product Master already exists for producer :producer with the name ":name" and appellation ":appellation". The identity key (producer + product name + appellation) must be unique.',
        // Fail-closed non-WINE rejection (design D2). :type is the rejected token.
        'unsupported_product_type' => 'Unsupported Product Type ":type". At launch the only supported Product Type is WINE.',
    ],
    'composite_sku' => [
        // N ≥ 2 rejection (design D9 / BR-SKU-2). :count is the number of distinct constituents provided.
        'insufficient_constituents' => 'A Composite SKU requires at least two distinct constituent Product References; :count was provided.',
    ],
];
