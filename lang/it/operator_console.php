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

];
