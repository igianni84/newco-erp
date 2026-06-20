<?php

// Operator console UI copy — IT (operator-console-catalog-master, task 2.1; design L8; DEC-127).
// Italian baseline for the OperatorPanel Filament surface. Per-key EN fallback: any key absent here
// resolves to its lang/en/operator_console.php value. Product-domain terms (Product Master) stay in
// English even in Italian copy, per Crurated convention (CRURATED/CLAUDE.md — terminologia tecnica).
return [

    'product_master' => [
        'label' => 'Product Master',
        'plural_label' => 'Product Master',

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
            'reopen' => 'Riapri',
        ],

        'affordance' => [
            'second_actor' => 'L’attivazione deve essere approvata da un operatore diverso da quello che ha creato o revisionato questo Product Master.',
        ],

        'notifications' => [
            'submitted' => 'Product Master inviato in revisione.',
            'rejected' => 'Rifiuto registrato; il Product Master resta in revisione.',
            'activated' => 'Product Master attivato.',
            'retired' => 'Product Master ritirato.',
            'reopened' => 'Product Master riaperto per la revisione.',
            'action_failed' => 'Impossibile completare l’azione.',
        ],

        'producer_unprojected' => 'Non proiettato',
    ],

];
