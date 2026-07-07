---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 4.3 (Club-6) DONE, committed — 13/23.** Three parts: (1) `open_registration` (latent enum case) made NON-SELECTABLE via a `Club::booted()` **`saving` guard** → new value-domain exception `ClubRegistrationFlowNotSelectable::forFlow` (rejected on create AND update, EVERY write path — the factory + DemoSeeder both `Club::create()` directly, bypassing `CreateClub`, so an action-only guard would leak it); (2) "no registration_flow_type auto-approves" PINNED as a characterization test (`CreateProfile` born `Applied` regardless of flow — zero code; `ApproveProfile` reaches `Active`); (3) `invite_only` sweep completed → grep-empty (app/tests/database/lang).

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 4.3 full loop **green**: focused ClubRegistrationFlow+Club 15/15 → SQLite full suite **2035/2035** (true baseline 2029 measured via `git stash -u`; +8 new −2 removed-invite_only-i18n-dataset-cases) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid · DemoSeeder smoke 11/11.
- **No PG17 run for 4.3** — pure-PHP model `saving` guard, NO schema/SQL (factory/seeder swap one valid enum token for another). `registration_flow_type` + cast were PG17-verified at table creation; the enum keeps 4 cases so any `cases()`-derived DB CHECK is unaffected. **PG17 recipe (DB-schema tasks only):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB).

## Active Change & Next Task
- **`parties-module-k-br-guards` — 13/23 done. NEXT = task 4.4 (RM-19):** `RetireProducer` Profile-leg cascade — after each `ClubSunset`, query Profiles by the sunsetting Club ids (NO `Club→Profile` relation) + drive `CancelProfile` for every `Active`/`Lapsed` Profile, in-txn, parent-before-child, **NO new event** (audit-only, assert `%ProfileCancelled%===0`); already-terminal + non-`Active`/`Lapsed` Profiles untouched. Grep `RetireProducer` callers (`ViewProducer`, `ProducerLifecycleTest`, `SupplyLifecycleChainTest`, `CatalogLifecycleChainTest`) and migrate any asserting a Profile-free retirement. _Acceptance:_ `ProducerLifecycleTest`/`SupplyLifecycleChainTest`.
- **Scope after 4.4:** §5 (5.1 Identity-6 age-gate in `CreateCustomer` → wires 2.4 `BelowMinimumRegistrationAge`; 5.2 Producer-5 `Producer::booted()` `updating` content-lock → wires 2.4 `ProducerReviewGovernedContentLocked` — the SIBLING model-guard to 4.3's Club `saving` guard) → §6 console+i18n → §7 close (human-gated). 2.4 exceptions still 3/5 wired (4.3 authored a fresh value-domain exc, not a 2.4 one).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Model `saving` guard for a latent/non-selectable value on EVERY write path (4.3):** when a value must reject on create AND update AND the primary writers bypass the `Create*` action (factory/seeder do direct `Model::create()`), enforce in `Model::booted()` `saving` (not the action) — the write-path-complete complement to 3.1's action-boundary value-domain reject. Throws a fresh value-domain exc (`InvalidSettlementCadence` shape). MANDATORY ripple: migrate the factory DEFAULT + seeder rows off the rejected value (else the whole factory-using suite reds). i18n key removal drops N-per-locale dataset cases — reconcile suite count `baseline+new−(removed×locales)`, measure baseline via `git stash -u`. "No auto-approve" half = characterization (born-state already correct), not enforcement.
- Earlier patterns (in progress.md): audit-only preference writer + Larastan-non-null-`first()`→ternary (4.2) · required-reference-guard UNCONDITIONAL (4.1) · business-rule guard on related-entity state (3.2) · behaviour-inversion guard (3.3) · value-domain-reject vs business-rule-guard (3.1) · cast blast-radius (2.1) · additive-NOT-NULL-needs-default (2.2) · column-DROP-atomic-sweep (2.3) · localized-guard-exception-SoD-shape (2.4).
