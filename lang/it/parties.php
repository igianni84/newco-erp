<?php

// Parties (Module K) operator-facing copy — IT. Italian rendering for the Producer separation-of-duties
// governance surfaced by ActivateProducer (change parties-producer-approval-sod, task 1.1; design D1/D4;
// DEC-127) AND the module-K business-rule guards of change parties-module-k-br-guards (tasks 2.4 + 3.1): the
// ProducerAgreement scope-conflict / Club-not-active / settlement-cadence closed-set reject, Club
// not-accepting-memberships, registration age-gate and Producer content-lock rejection reasons. This file
// authors a SUBSET of `parties.*`; every other key is
// authored in lang/en/parties.php and resolves under `it` via per-key EN fallback (Laravel chain [it, en]).
// Product-domain terms (Producer, Club, Profile, ProducerAgreement) stay in English even in Italian copy, per
// Crurated convention (CRURATED/CLAUDE.md — terminologia tecnica di prodotto può restare in inglese). Every key
// here MUST have an `en` counterpart — the DEC-127 baseline invariant (asserted by PartiesApprovalCopyTest).
return [
    'approval' => [
        // Mirrors the EN `approval` group. :entity ('Producer') is the entity-type label, not PII; the copy names
        // only the violated rule (the acting principal lives on the event/audit row).
        'requires_operator_principal' => 'L\'attivazione di questo :entity richiede un operatore autenticato; un attore di sistema non può soddisfare il vincolo di separazione dei compiti.',
        'creator_may_not_approve' => 'Separazione dei compiti su questo :entity: chi lo ha creato non può anche attivarlo.',
    ],
    'producer' => [
        // Review-governed content lock (BR-K-Producer-5 / canon MVP-DEC-022 — interim). Mirrors the EN key. The
        // review-governed field names (name/description/region/website) and the status token `active` stay in
        // English (product-domain terms). :producer is the operator-facing id reference (not PII).
        'review_governed_content_locked' => 'Impossibile modificare i contenuti soggetti a revisione (name, description, region, website) del Producer :producer mentre è active. Questi contenuti sono immutabili su un Producer attivo — una modifica richiederebbe un nuovo passaggio di revisione, non ancora disponibile.',
    ],
    'club' => [
        // New-membership block at Profile creation (BR-K-Club-3 / AC-K-FSM-6). Mirrors the EN key. :club is the
        // operator-facing id reference (not PII); :state is the offending ClubStatus token (a business enum, not PII).
        'not_accepting_memberships' => 'Impossibile creare un Profile nel Club :club: il Club è :state e non accetta più nuove membership. Le nuove membership sono accettate solo da un Club attivo.',
    ],
    'producer_agreement' => [
        // Per-Club scope requires an active Club (BR-K-Agreement-4 / canon MVP-DEC-009). Mirrors the EN key.
        // :club is the operator-facing id reference (not PII); :state is the offending ClubStatus token (not PII).
        'club_not_active' => 'Impossibile associare un ProducerAgreement al Club :club: il Club è :state, non attivo. Un accordo per-Club richiede un Club attivo — gli accordi Producer-wide non sono soggetti a questo vincolo.',
        // Cross-shape mutual exclusion at activation (BR-K-Agreement-1 clause 2). Mirrors the two EN reasons.
        // :producer is the operator-facing id reference (not PII); the copy names only the rule.
        'scope_conflict_producer_wide' => 'Impossibile attivare un accordo Producer-wide per il Producer :producer: un accordo per-Club è già attivo. Gli accordi Producer-wide e per-Club di un Producer si escludono a vicenda — termina o sostituisci prima l\'accordo per-Club attivo.',
        'scope_conflict_club_scope' => 'Impossibile attivare un accordo per-Club per il Producer :producer: un accordo Producer-wide è già attivo. Gli accordi Producer-wide e per-Club di un Producer si escludono a vicenda — termina o sostituisci prima l\'accordo Producer-wide attivo.',
        // Settlement-cadence closed-set reject (BR-K-Agreement-2 / canon MVP-DEC-010). Mirrors the EN key. The three
        // accepted tokens (quarterly/monthly/semi_annual, enum backing values) stay in English; :cadence echoes the
        // offending operator-supplied token — a cadence token is NOT PII (the :producer / :club id discipline).
        'invalid_settlement_cadence' => 'Impossibile creare un ProducerAgreement: ":cadence" non è una cadenza di regolamento valida. La cadenza di regolamento deve essere una tra quarterly, monthly o semi_annual.',
    ],
    'customer' => [
        // Registration age gate (BR-K-Identity-6 / canon MVP-DEC-022; BMD § 2.8). Mirrors the two EN reasons. The
        // copy interpolates ONLY the :min_age platform constant (a public config value, not PII); the date of birth
        // and the derived age are PII and are DELIBERATELY never surfaced (the duplicate_email / gate_not_met discipline).
        'below_minimum_registration_age' => 'Impossibile registrare questo Customer: la data di nascita autodichiarata è inferiore all\'età minima di registrazione della piattaforma di :min_age. La registrazione richiede un\'età di almeno :min_age anni.',
        'missing_date_of_birth' => 'Impossibile registrare questo Customer: è richiesta una data di nascita autodichiarata per verificare l\'età minima di registrazione di :min_age. La registrazione richiede una data di nascita corrispondente ad almeno :min_age anni.',
    ],
];
