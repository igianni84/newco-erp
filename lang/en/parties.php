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
    'producer_agreement' => [
        // § 4.6 rejection (design D3/D4): a ProducerAgreement references exactly one EXISTING Producer.
        // :producer is the operator-facing producer-id reference (not PII).
        'missing_producer' => 'Cannot create a ProducerAgreement: no Producer exists for reference :producer. A ProducerAgreement requires exactly one existing Producer.',
    ],
    'customer' => [
        // § 4.1 / BR-K-Identity-1 rejection (design D5): a Customer's email is globally unique. The reason
        // names the rule and DELIBERATELY omits the email — an email is PII (GDPR) and this message can reach
        // logs (unlike the producer-id references above, which are not PII). The operator supplied the value, so
        // the rule alone is fully actionable.
        'duplicate_email' => 'Cannot create a Customer: a Customer with this email address already exists. Each Customer email must be globally unique.',
    ],
    'profile' => [
        // BR-K-Identity-2 rejection (design D8): a Customer holds at most one NON-TERMINAL Profile per Club, so
        // a second live Profile for a (Customer, Club) pair is rejected. :customer / :club are operator-facing
        // id references (not PII), so they are interpolated to make the reason self-documenting (unlike the
        // duplicate_email reason, which omits the PII email).
        'duplicate_for_club' => 'Cannot create a Profile: Customer :customer already has a live Profile in Club :club. A Customer may hold at most one non-terminal Profile per Club.',
    ],
];
