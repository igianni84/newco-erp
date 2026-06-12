<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery Sweep
    |--------------------------------------------------------------------------
    |
    | Tunables for the `events:sweep` command — the at-least-once guarantee over
    | the per-consumer delivery ledger (App\Platform\Events\InlineDeliveryExecutor).
    | The event-substrate ADR fixes only the SHAPE of retry (exponential backoff,
    | capped, dead-letter at a maximum); these are the launch numbers, overridable
    | per environment. The executor reads each key via Config::integer() with the
    | SAME default baked into its fallback, so it stays correct even if a key is
    | unset — this file makes the numbers explicit and env-tunable.
    |
    | See decisions/2026-06-12-event-substrate-and-audit-store.md and design D6.
    |
    */

    'sweep' => [

        // Delivery attempts before a row is dead-lettered (status `failed`, no longer swept).
        'max_attempts' => (int) env('EVENTS_SWEEP_MAX_ATTEMPTS', 5),

        // Backoff base in seconds; the window after attempt N is base * 2^(N-1)…
        'backoff_base_seconds' => (int) env('EVENTS_SWEEP_BACKOFF_BASE_SECONDS', 30),

        // …capped at this many seconds (one hour).
        'backoff_cap_seconds' => (int) env('EVENTS_SWEEP_BACKOFF_CAP_SECONDS', 3600),

    ],

];
