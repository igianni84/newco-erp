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

];
