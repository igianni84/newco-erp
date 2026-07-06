<?php

// Parties (Module K) operator-facing copy — IT. Italian rendering for the Producer separation-of-duties
// governance surfaced by ActivateProducer (change parties-producer-approval-sod, task 1.1; design D1/D4;
// DEC-127). This file covers ONLY the `approval` group; every other `parties.*` key is authored in
// lang/en/parties.php and resolves under `it` via per-key EN fallback (Laravel chain [it, en]). Product-domain
// terms (Producer) stay in English even in Italian copy, per Crurated convention (CRURATED/CLAUDE.md —
// terminologia tecnica di prodotto può restare in inglese). Every key here MUST have an `en` counterpart —
// the DEC-127 baseline invariant (asserted by PartiesApprovalCopyTest).
return [
    'approval' => [
        // Mirrors the EN `approval` group. :entity ('Producer') is the entity-type label, not PII; the copy names
        // only the violated rule (the acting principal lives on the event/audit row).
        'requires_operator_principal' => 'L\'attivazione di questo :entity richiede un operatore autenticato; un attore di sistema non può soddisfare il vincolo di separazione dei compiti.',
        'creator_may_not_approve' => 'Separazione dei compiti su questo :entity: chi lo ha creato non può anche attivarlo.',
    ],
];
