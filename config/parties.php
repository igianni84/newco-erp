<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hero Package Capacity
    |--------------------------------------------------------------------------
    |
    | The membership capacity of a Club's Hero Package — the number of seats its
    | seat-occupying Profiles (`Active` + `Suspended`) may fill before further
    | approvals divert to the waiting list (parties-hero-package, design D1/D2;
    | party-registry — Requirement: Hero Package Capacity Is Read from Module A,
    | Never Stored in Module K; ADR
    | 2026-07-09-hero-package-capacity-seat-set-and-waitinglist § 9).
    |
    | THE NUMBER IS NOT OURS. It is the Hero-Package Allocation's `qty`, owned by
    | MODULE A (canon MVP-DEC-020: Module A owns the number, Module K owns the
    | invariant). Module K stores no capacity value of any kind — no column on
    | `parties_clubs`, no capacity table, no read-model (AC-K-XM-20, verified by
    | schema inspection). This file is the LAUNCH ADAPTER's source, read by
    | App\Modules\Parties\Reads\ConfigHeroPackageCapacityReader behind the
    | Contracts\HeroPackageCapacityReader port, because Module A is a two-file
    | stub. When Module A lands, only the adapter is replaced — one line in
    | PartiesServiceProvider — and this file goes away. It is configuration
    | standing in for a module, not a Module K attribute.
    |
    | `default` is the capacity every Club carries; `by_club_id` optionally pins
    | one Club (`parties_clubs` has no slug, and ids are not stable outside
    | DemoSeeder). An entry in `by_club_id` wins on presence, so an explicit
    | `null` there pins one Club uncapped beneath a capped default.
    |
    | null (unset, or non-numeric) means UNCAPPED — the shipped production
    | posture, a dark launch: the seat gate is inert and every transition behaves
    | as it did before the gate existed. `0` is a real capacity of zero seats, not
    | an absence. `env()` yields a string, so the reader casts to int.
    |
    */

    'hero_package' => [
        'capacity' => [
            'default' => env('PARTIES_HERO_PACKAGE_CAPACITY'),
            'by_club_id' => [],
        ],
    ],

];
