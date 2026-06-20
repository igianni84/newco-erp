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
            'appellation' => 'Denominazione',
            'region' => 'Regione',
            'winery_story' => 'Storia della cantina',
        ],

        'producer_unprojected' => 'Non proiettato',
    ],

];
