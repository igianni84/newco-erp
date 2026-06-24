<?php

declare(strict_types=1);

/*
 | Curated wine-geography picklist for the operator console (NOT domain truth).
 |
 | The Catalog domain stores a Product Master's `region` and `appellation` as free strings (Module 0 PRD §3.9 /
 | AC-0-GEN-2 — appellation is part of the BR-Identity-1 key `producer + name + appellation`). The spec is silent
 | on any geography reference vocabulary, so this list is a PRESENTATION concern only: it powers a cascading
 | Country → Region select and a region-scoped appellation autocomplete on the create form, killing the free-text
 | typo-variants ('Bordeaux' vs 'bordeaux') that would silently split the dedup key — without introducing a new
 | reference table or DB schema (the "light cascade" decision, 2026-06-24). It lives under OperatorPanel-facing
 | config (read via config('wine_geography')), never as a Catalog domain entity.
 |
 | Structure: country => [ region => [appellation, ...] ]. Region granularity matches how producers carry region
 | (e.g. 'Côte de Nuits', 'Médoc') so the producer prefill lands on a real option. Appellation entry stays FREE
 | (the autocomplete only SUGGESTS these), so a new wine's appellation is never blocked.
 */

return [
    'France' => [
        'Bordeaux' => ['Pessac-Léognan', 'Graves', 'Sauternes', 'Barsac', 'Entre-Deux-Mers'],
        'Médoc' => ['Margaux 1er Grand Cru Classé', 'Margaux', 'Pauillac', 'Saint-Julien', 'Saint-Estèphe', 'Haut-Médoc', 'Listrac-Médoc', 'Moulis-en-Médoc'],
        'Saint-Émilion' => ['Saint-Émilion Grand Cru Classé', 'Saint-Émilion Grand Cru', 'Pomerol', 'Lalande-de-Pomerol'],
        'Côte de Nuits' => ['Romanée-Conti Grand Cru', 'La Tâche Grand Cru', 'Richebourg Grand Cru', 'Échezeaux Grand Cru', 'Vosne-Romanée Grand Cru', 'Vosne-Romanée', 'Gevrey-Chambertin', 'Chambolle-Musigny', 'Morey-Saint-Denis', 'Nuits-Saint-Georges', 'Vougeot', 'Clos de Vougeot Grand Cru'],
        'Côte de Beaune' => ['Montrachet Grand Cru', 'Corton-Charlemagne Grand Cru', 'Puligny-Montrachet Grand Cru', 'Puligny-Montrachet', 'Chassagne-Montrachet', 'Meursault', 'Pommard', 'Volnay', 'Beaune', 'Corton Grand Cru'],
        'Chablis' => ['Chablis Grand Cru', 'Chablis Premier Cru', 'Chablis', 'Petit Chablis'],
        'Champagne' => ['Champagne Grand Cru', 'Champagne Premier Cru', 'Champagne'],
        'Northern Rhône' => ['Côte-Rôtie', 'Hermitage', 'Cornas', 'Condrieu', 'Crozes-Hermitage', 'Saint-Joseph'],
        'Southern Rhône' => ['Châteauneuf-du-Pape', 'Gigondas', 'Vacqueyras', 'Tavel'],
        'Loire' => ['Sancerre', 'Pouilly-Fumé', 'Vouvray', 'Chinon', 'Savennières', 'Muscadet'],
        'Alsace' => ['Alsace Grand Cru', 'Alsace'],
        'Provence' => ['Bandol', 'Côtes de Provence'],
    ],
    'Italy' => [
        'Piedmont' => ['Barolo DOCG', 'Barbaresco DOCG', "Barbera d'Alba DOC", 'Langhe DOC', 'Gattinara DOCG', 'Roero DOCG'],
        'Tuscany' => ['Brunello di Montalcino DOCG', 'Chianti Classico DOCG', 'Bolgheri Sassicaia DOC', 'Bolgheri DOC', 'Vino Nobile di Montepulciano DOCG', 'Toscana IGT'],
        'Veneto' => ['Amarone della Valpolicella DOCG', 'Valpolicella DOC', 'Soave DOC', 'Prosecco DOC'],
        'Lombardy' => ['Franciacorta DOCG', 'Valtellina Superiore DOCG'],
        'Friuli-Venezia Giulia' => ['Collio DOC', 'Friuli DOC'],
        'Sicily' => ['Etna DOC', 'Sicilia DOC'],
    ],
    'Spain' => [
        'Castilla y León' => ['Ribera del Duero DO', 'Toro DO', 'Rueda DO'],
        'Rioja' => ['Rioja DOCa'],
        'Catalonia' => ['Priorat DOCa', 'Penedès DO', 'Cava DO'],
        'Galicia' => ['Rías Baixas DO'],
    ],
    'Germany' => [
        'Mosel' => ['Mosel'],
        'Rheingau' => ['Rheingau'],
        'Pfalz' => ['Pfalz'],
        'Nahe' => ['Nahe'],
    ],
    'Portugal' => [
        'Douro' => ['Port', 'Douro DOC'],
        'Dão' => ['Dão DOC'],
        'Bairrada' => ['Bairrada DOC'],
    ],
    'United States' => [
        'Napa Valley' => ['Oakville', 'Rutherford', 'Stags Leap District', 'Howell Mountain', 'Spring Mountain District', 'Napa Valley'],
        'Sonoma County' => ['Russian River Valley', 'Sonoma Coast', 'Alexander Valley'],
        'Oregon' => ['Willamette Valley'],
        'Washington' => ['Columbia Valley', 'Walla Walla Valley'],
    ],
    'Australia' => [
        'South Australia' => ['Barossa Valley', 'McLaren Vale', 'Coonawarra', 'Eden Valley', 'Clare Valley'],
        'Victoria' => ['Yarra Valley', 'Mornington Peninsula'],
        'Western Australia' => ['Margaret River'],
    ],
    'Argentina' => [
        'Mendoza' => ['Uco Valley', 'Luján de Cuyo', 'Maipú'],
    ],
    'Chile' => [
        'Aconcagua' => ['Aconcagua Valley', 'Casablanca Valley'],
        'Valle Central' => ['Maipo Valley', 'Colchagua Valley', 'Cachapoal Valley'],
    ],
];
