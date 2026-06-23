<?php

// Operator console UI copy — IT (operator-console-catalog-master, task 2.1; design L8; DEC-127).
// Italian baseline for the OperatorPanel Filament surface. Per-key EN fallback: any key absent here
// resolves to its lang/en/operator_console.php value. Product-domain terms (Product Master) stay in
// English even in Italian copy, per Crurated convention (CRURATED/CLAUDE.md — terminologia tecnica).
return [

    'product_master' => [
        // 'label' / 'plural_label' are intentionally ABSENT here: "Product Master" is an English-invariant
        // structural domain term (CONTEXT.md), so per-key EN fallback (DEC-127) renders it from
        // lang/en/operator_console.php under every locale — duplicating the English string in `it` would be
        // redundant. This is also the live demonstration of the per-key fallback (ProductMasterConsoleI18nTest).

        'columns' => [
            'name' => 'Nome',
            'product_type' => 'Tipo prodotto',
            'lifecycle_state' => 'Stato',
            'producer' => 'Produttore',
            'version' => 'Versione',
        ],

        'fields' => [
            'name' => 'Nome',
            'producer' => 'Produttore',
            'appellation' => 'Denominazione',
            'region' => 'Regione',
            'winery_story' => 'Storia della cantina',
            'winery_story_help' => 'Facoltativo. Inserito in inglese, la lingua di base.',
            'rejection_notes' => 'Note di rifiuto',
        ],

        'actions' => [
            'create' => 'Nuovo Product Master',
            'submit' => 'Invia in revisione',
            'reject' => 'Rifiuta',
            'activate' => 'Attiva',
            'retire' => 'Ritira',
            'retire_cascade' => 'Ritira (a cascata)',
            'reopen' => 'Riapri',
        ],

        'affordance' => [
            'second_actor' => 'L’attivazione deve essere approvata da un operatore diverso da quello che ha creato o revisionato questo Product Master.',
            'cascade_warning' => 'Il ritiro a cascata ritira anche tutti i discendenti attivi di questo Product Master — varianti, reference e SKU — in un solo passaggio. Usa Ritira per ritirare solo il Product Master.',
        ],

        'notifications' => [
            'submitted' => 'Product Master inviato in revisione.',
            'rejected' => 'Rifiuto registrato; il Product Master resta in revisione.',
            'activated' => 'Product Master attivato.',
            'retired' => 'Product Master ritirato.',
            'cascade_retired' => 'Product Master e i suoi discendenti attivi ritirati.',
            'reopened' => 'Product Master riaperto per la revisione.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],

        'producer_unprojected' => 'Non proiettato',
    ],

    'format' => [
        // 'label' / 'plural_label' intentionally ABSENT: "Format" is an English-invariant structural domain
        // term (CONTEXT.md), so per-key EN fallback (DEC-127) renders it under every locale.

        'columns' => [
            'name' => 'Nome',
            'size_label' => 'Etichetta formato',
            'volume_ml' => 'Volume (ml)',
            'lifecycle_state' => 'Stato',
            'version' => 'Versione',
        ],

        'fields' => [
            'name' => 'Nome',
            'size_label' => 'Etichetta formato',
            'volume_ml' => 'Volume (ml)',
            'rejection_notes' => 'Note di rifiuto',
        ],

        'actions' => [
            'create' => 'Nuovo Format',
            'submit' => 'Invia in revisione',
            'reject' => 'Rifiuta',
            'activate' => 'Attiva',
            'retire' => 'Ritira',
            'reopen' => 'Riapri',
        ],

        'affordance' => [
            'second_actor' => 'L’attivazione deve essere approvata da un operatore diverso da quello che ha creato o revisionato questo Format.',
        ],

        'notifications' => [
            'submitted' => 'Format inviato in revisione.',
            'rejected' => 'Rifiuto registrato; il Format resta in revisione.',
            'activated' => 'Format attivato.',
            'retired' => 'Format ritirato.',
            'reopened' => 'Format riaperto per la revisione.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

    'case_configuration' => [
        // 'label' / 'plural_label' intentionally ABSENT: "Case Configuration" is an English-invariant structural
        // domain term (CONTEXT.md), so per-key EN fallback (DEC-127) renders it under every locale.

        'columns' => [
            'name' => 'Nome',
            'units_per_case' => 'Unità per cassa',
            'packaging_type' => 'Tipo di confezione',
            'lifecycle_state' => 'Stato',
            'version' => 'Versione',
        ],

        'fields' => [
            'name' => 'Nome',
            'units_per_case' => 'Unità per cassa',
            'packaging_type' => 'Tipo di confezione',
            'rejection_notes' => 'Note di rifiuto',
        ],

        'actions' => [
            'create' => 'Nuova Case Configuration',
            'submit' => 'Invia in revisione',
            'reject' => 'Rifiuta',
            'activate' => 'Attiva',
            'retire' => 'Ritira',
            'reopen' => 'Riapri',
        ],

        'affordance' => [
            'second_actor' => 'L’attivazione deve essere approvata da un operatore diverso da quello che ha creato o revisionato questa Case Configuration.',
        ],

        'notifications' => [
            'submitted' => 'Case Configuration inviata in revisione.',
            'rejected' => 'Rifiuto registrato; la Case Configuration resta in revisione.',
            'activated' => 'Case Configuration attivata.',
            'retired' => 'Case Configuration ritirata.',
            'reopened' => 'Case Configuration riaperta per la revisione.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

    'product_variant' => [
        // 'label' / 'plural_label' intentionally ABSENT: "Product Variant" is an English-invariant structural
        // domain term (CONTEXT.md), so per-key EN fallback (DEC-127) renders it under every locale.

        'columns' => [
            'variant_identifier' => 'Identificativo variante',
            'master' => 'Product Master',
            'vintage' => 'Annata',
            'lifecycle_state' => 'Stato',
            'version' => 'Versione',
        ],

        'fields' => [
            'product_master' => 'Product Master',
            'variant_identifier' => 'Identificativo variante',
            'vintage_year' => 'Anno di annata',
            'non_vintage' => 'Senza annata',
            'tasting_notes' => 'Note di degustazione',
            'tasting_notes_help' => 'Facoltativo. Inserito in inglese, la lingua di base.',
            'rejection_notes' => 'Note di rifiuto',
        ],

        'values' => [
            'non_vintage' => 'Senza annata',
            'yes' => 'Sì',
            'no' => 'No',
        ],

        'actions' => [
            'create' => 'Nuovo Product Variant',
            'submit' => 'Invia in revisione',
            'reject' => 'Rifiuta',
            'activate' => 'Attiva',
            'retire' => 'Ritira',
            'reopen' => 'Riapri',
        ],

        'affordance' => [
            'second_actor' => 'L’attivazione deve essere approvata da un operatore diverso da quello che ha creato o revisionato questo Product Variant.',
        ],

        'notifications' => [
            'submitted' => 'Product Variant inviato in revisione.',
            'rejected' => 'Rifiuto registrato; il Product Variant resta in revisione.',
            'activated' => 'Product Variant attivato.',
            'retired' => 'Product Variant ritirato.',
            'reopened' => 'Product Variant riaperto per la revisione.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

    'product_reference' => [
        // 'label' / 'plural_label' intentionally ABSENT: "Product Reference" is an English-invariant structural
        // domain term (CONTEXT.md), so per-key EN fallback (DEC-127) renders it under every locale.

        'columns' => [
            'variant' => 'Product Variant',
            'format' => 'Format',
            'lifecycle_state' => 'Stato',
            'version' => 'Versione',
        ],

        'fields' => [
            'product_variant' => 'Product Variant',
            'format' => 'Format',
            'rejection_notes' => 'Note di rifiuto',
        ],

        // Colon-free, like the EN copy (the test asserts the rendered message verbatim).
        'duplicate_reference' => 'Esiste già un Product Reference per questo Product Variant e Format. Ogni combinazione di Variant e Format deve essere unica.',

        'actions' => [
            'create' => 'Nuovo Product Reference',
            'submit' => 'Invia in revisione',
            'reject' => 'Rifiuta',
            'activate' => 'Attiva',
            'retire' => 'Ritira',
            'reopen' => 'Riapri',
        ],

        'affordance' => [
            'second_actor' => 'L’attivazione deve essere approvata da un operatore diverso da quello che ha creato o revisionato questo Product Reference.',
        ],

        'notifications' => [
            'submitted' => 'Product Reference inviato in revisione.',
            'rejected' => 'Rifiuto registrato; il Product Reference resta in revisione.',
            'activated' => 'Product Reference attivato.',
            'retired' => 'Product Reference ritirato.',
            'reopened' => 'Product Reference riaperto per la revisione.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

    'sellable_sku' => [
        // 'label' / 'plural_label' intentionally ABSENT: "Sellable SKU" is an English-invariant structural domain
        // term (CONTEXT.md), so per-key EN fallback (DEC-127) renders it under every locale.

        'columns' => [
            'reference' => 'Product Reference',
            'case_configuration' => 'Case Configuration',
            'commercial_name' => 'Nome commerciale',
            'lifecycle_state' => 'Stato',
            'version' => 'Versione',
        ],

        'fields' => [
            'product_reference' => 'Product Reference',
            'case_configuration' => 'Case Configuration',
            'commercial_name' => 'Nome commerciale',
            'marketing_copy' => 'Testo di marketing',
            'rejection_notes' => 'Note di rifiuto',
        ],

        'actions' => [
            'create' => 'Nuovo Sellable SKU',
            'submit' => 'Invia in revisione',
            'reject' => 'Rifiuta',
            'activate' => 'Attiva',
            'retire' => 'Ritira',
            'reopen' => 'Riapri',
        ],

        'affordance' => [
            'second_actor' => 'L’attivazione deve essere approvata da un operatore diverso da quello che ha creato o revisionato questo Sellable SKU.',
        ],

        'notifications' => [
            'submitted' => 'Sellable SKU inviato in revisione.',
            'rejected' => 'Rifiuto registrato; il Sellable SKU resta in revisione.',
            'activated' => 'Sellable SKU attivato.',
            'retired' => 'Sellable SKU ritirato.',
            'reopened' => 'Sellable SKU riaperto per la revisione.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

    'composite_sku' => [
        // 'label' / 'plural_label' intentionally ABSENT: "Composite SKU" is an English-invariant structural
        // domain term (CONTEXT.md), so per-key EN fallback (DEC-127) renders it under every locale.

        'columns' => [
            'constituent_count' => 'Costituenti',
            'lifecycle_state' => 'Stato',
            'version' => 'Versione',
        ],

        'fields' => [
            'constituents' => 'Costituenti',
            'constituents_help' => 'Seleziona due o più Product Reference, in ordine di bundle.',
            'rejection_notes' => 'Note di rifiuto',
        ],

        'actions' => [
            'create' => 'Nuovo Composite SKU',
            'submit' => 'Invia in revisione',
            'reject' => 'Rifiuta',
            'activate' => 'Attiva',
            'retire' => 'Ritira',
            'reopen' => 'Riapri',
        ],

        'affordance' => [
            'second_actor' => 'L’attivazione deve essere approvata da un operatore diverso da quello che ha creato o revisionato questo Composite SKU.',
        ],

        'notifications' => [
            'submitted' => 'Composite SKU inviato in revisione.',
            'rejected' => 'Rifiuto registrato; il Composite SKU resta in revisione.',
            'activated' => 'Composite SKU attivato.',
            'retired' => 'Composite SKU ritirato.',
            'reopened' => 'Composite SKU riaperto per la revisione.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

    // Producer — registro identità del produttore (Module K § 4.4). `label` / `plural_label` assenti → fallback
    // per-chiave su EN (DEC-127): «Producer» è il termine di dominio canonico, invariato in italiano.
    'producer' => [
        'columns' => [
            'name' => 'Nome',
            'region' => 'Regione',
            'country' => 'Paese',
            'status' => 'Stato',
            'kyc_status' => 'Stato KYC',
            'version' => 'Versione',
        ],

        'fields' => [
            'name' => 'Nome',
            'region' => 'Regione',
            'country' => 'Paese',
            'appellation' => 'Denominazione',
            'website' => 'Sito web',
            'description' => 'Descrizione',
            'clubs' => 'Club operati',
        ],

        'actions' => [
            'create' => 'Nuovo Producer',
            'activate' => 'Attiva',
            'retire' => 'Ritira',
            'require_kyc' => 'Richiedi KYC',
            'waive_kyc' => 'Esonera KYC',
            'verify_kyc' => 'Verifica KYC',
            'reject_kyc' => 'Rifiuta KYC',
        ],

        'notifications' => [
            'activated' => 'Producer attivato.',
            'retired' => 'Producer ritirato.',
            'kyc_required' => 'KYC del Producer richiesto.',
            'kyc_waived' => 'KYC del Producer esonerato.',
            'kyc_verified' => 'KYC del Producer verificato.',
            'kyc_rejected' => 'KYC del Producer rifiutato.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

    // Club — programma di membership operato da un Producer (Module K § 4.3). `label` / `plural_label` assenti →
    // fallback per-chiave su EN (DEC-127): «Club» è il termine di dominio canonico, invariato in italiano.
    'club' => [
        'columns' => [
            'display_name' => 'Nome',
            'producer' => 'Produttore',
            'registration_flow_type' => 'Flusso di registrazione',
            'status' => 'Stato',
            'version' => 'Versione',
        ],

        'fields' => [
            'display_name' => 'Nome',
            'producer' => 'Produttore operante',
            'registration_flow_type' => 'Flusso di registrazione',
            'amount' => 'Importo quota (unità minori)',
            'currency' => 'Valuta quota',
            'fee' => 'Quota',
            'generates_credit' => 'Genera credito',
            'invite_only' => 'Solo su invito',
        ],

        'actions' => [
            'create' => 'Nuovo Club',
            'sunset' => 'Dismetti',
            'close' => 'Chiudi',
        ],

        'notifications' => [
            'sunset' => 'Club dismesso.',
            'closed' => 'Club chiuso.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

    'producer_agreement' => [
        'columns' => [
            'producer' => 'Produttore',
            'club' => 'Club di riferimento',
            'status' => 'Stato',
            'term_start' => 'Inizio termine',
            'term_end' => 'Fine termine',
            'version' => 'Versione',
        ],

        'producer_wide' => 'Valido per tutto il produttore',

        'fields' => [
            'producer' => 'Produttore',
            'club' => 'Club di riferimento',
            'term_start' => 'Inizio termine',
            'term_end' => 'Fine termine',
            'settlement_cadence' => 'Cadenza di liquidazione',
        ],

        'actions' => [
            'create' => 'Nuovo accordo',
            'activate' => 'Attiva',
            'terminate' => 'Termina',
        ],

        'notifications' => [
            'activated' => 'Accordo produttore attivato.',
            'terminated' => 'Accordo produttore terminato.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

    // Customer — registro delle persone fisiche di NewCo (Module K § 4.1). `label` / `plural_label` assenti →
    // fallback per-chiave su EN (DEC-127): «Customer» è il termine di dominio canonico, invariato in italiano.
    'customer' => [
        'columns' => [
            'name' => 'Nome',
            'email' => 'Indirizzo email',
            'status' => 'Stato',
            'kyc_status' => 'Stato KYC',
            'sanctions_status' => 'Stato sanzioni',
            'account_status' => 'Stato account',
            'profiles' => 'Profili',
            'version' => 'Versione',
        ],

        'fields' => [
            'email' => 'Indirizzo email',
            'name' => 'Nome',
            'phone' => 'Telefono',
            'date_of_birth' => 'Data di nascita',
            'preferred_currency' => 'Valuta preferita',
            'preferred_locale' => 'Lingua preferita',
            // Input del form Applica/Rimuovi blocco (operator-console-parties-holds). «Hold» → «blocco» (non
            // «sospensione»: il blocco è distinto dal verbo di stato Sospendi, che un blocco può però innescare).
            'hold_type' => 'Tipo di blocco',
            'hold_scope' => 'Ambito del blocco',
            'profile' => 'Profilo',
            'reason' => 'Motivo',
            'lift_reason' => 'Motivo della rimozione',
            // Input del form di screening sanzioni (operator-console-parties-kyc-sanctions). «Screening» resta
            // invariato (termine di dominio, come «KYC»); «verdict» → «esito», «trigger source» → «origine».
            'screening_verdict' => 'Esito screening',
            'screening_source' => 'Origine screening',
        ],

        'actions' => [
            'create' => 'Nuovo cliente',
            'activate' => 'Attiva',
            'suspend' => 'Sospendi',
            'reactivate' => 'Riattiva',
            'close' => 'Chiudi',
            'place_hold' => 'Applica blocco',
            'lift_hold' => 'Rimuovi blocco',
            // Verbi KYC + sanzioni (operator-console-parties-kyc-sanctions). «record» → «Registra» (verbi di
            // registrazione audit, distinti dal «Verifica/Rifiuta KYC» del Producer); «screening» invariato.
            'require_kyc' => 'Richiedi KYC',
            'record_kyc_verified' => 'Registra KYC verificato',
            'record_kyc_rejected' => 'Registra KYC rifiutato',
            'record_screening' => 'Registra screening sanzioni',
        ],

        'notifications' => [
            'activated' => 'Cliente attivato.',
            'suspended' => 'Cliente sospeso.',
            'reactivated' => 'Cliente riattivato.',
            'closed' => 'Cliente chiuso.',
            'hold_placed' => 'Blocco applicato al cliente.',
            'hold_lifted' => 'Blocco rimosso dal cliente.',
            'kyc_required' => 'KYC richiesto; il cliente è sospeso in attesa di verifica.',
            'kyc_verified' => 'KYC verificato; il cliente è riattivato.',
            'kyc_rejected' => 'KYC rifiutato; il cliente resta bloccato.',
            'screening_recorded' => 'Screening sanzioni registrato.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],

        'holds' => [
            'columns' => [
                'hold_type' => 'Tipo',
                'scope_type' => 'Ambito',
                'status' => 'Stato',
                'reason' => 'Motivo',
                'placed_by' => 'Applicato da',
                'placed_at' => 'Applicato il',
                'lifted_by' => 'Rimosso da',
                'lifted_at' => 'Rimosso il',
            ],
        ],
    ],

    // Console MEMBERSHIP lato domanda (operator-console-parties-membership). `label`/`plural_label` omessi →
    // fallback EN per-chiave (DEC-127); tutte le altre chiavi tradotte.
    'profile' => [
        'columns' => [
            'customer' => 'Cliente',
            'club' => 'Club',
            'state' => 'Stato',
            'version' => 'Versione',
        ],

        'fields' => [
            'tier' => 'Livello',
            'lapsed_at' => 'Scaduto il',
            'cancellation_reason' => 'Motivo di cancellazione',
            'customer' => 'Cliente',
            'club' => 'Club',
        ],

        'tabs' => [
            'pending' => 'In attesa',
            'all' => 'Tutti',
        ],

        // Verbi del ciclo di vita: `approve`/`decline` (gruppo 3) sull'azione di approvazione dell'adesione;
        // `activate`/`suspend`/`reactivate` (gruppo 4) sui verbi di stato; `lapse`/`renew`/`cancel`/`deactivate`
        // (gruppo 5) sui verbi di scadenza/rinnovo/terminali. «decline» → «Rifiuta» (rifiuto della candidatura,
        // distinto da «Termina»/«Ritira» di altri domini); «reactivate» → «Riattiva»; «lapse» → «Fai scadere» (porta
        // l'adesione a `scaduta`); «cancel» → «Annulla» (annullamento terminale); «deactivate» → «Disattiva».
        'actions' => [
            'create' => 'Nuovo Profilo',
            'approve' => 'Approva',
            'decline' => 'Rifiuta',
            'activate' => 'Attiva',
            'suspend' => 'Sospendi',
            'reactivate' => 'Riattiva',
            'lapse' => 'Fai scadere',
            'renew' => 'Rinnova',
            'cancel' => 'Annulla',
            'deactivate' => 'Disattiva',
        ],

        // Notifiche di esito per i verbi di membership. «membership» → «adesione» (parola italiana naturale, come
        // «cliente» per Customer). `action_failed` è il titolo di errore condiviso (corpo dal messaggio localizzato
        // della rejection — lang/*/parties.php). Gruppo 4 aggiunge `activated`/`suspended`/`reactivated` (i passaggi
        // di stato dell'adesione); gruppo 5 aggiunge `lapsed`/`renewed`/`cancelled`/`deactivated` (scadenza, rinnovo
        // e terminali) — `action_failed` è raggiungibile dall'interfaccia solo da un `renew` fuori dal periodo di
        // grazia (design D5).
        'notifications' => [
            'approved' => 'Adesione approvata.',
            'declined' => 'Candidatura all’adesione rifiutata.',
            'activated' => 'Adesione attivata.',
            'suspended' => 'Adesione sospesa.',
            'reactivated' => 'Adesione riattivata.',
            'lapsed' => 'Adesione scaduta.',
            'renewed' => 'Adesione rinnovata.',
            'cancelled' => 'Adesione annullata.',
            'deactivated' => 'Adesione disattivata.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],
    ],

];
