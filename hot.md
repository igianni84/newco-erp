---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 18:41 (ralph iteration 10/20 — `parties-core` task 6.1 Docs DONE).** DOCS-ONLY (no code). Extended root `CONTEXT.md` (glossary of record) with the resolved **Parties spine**: a new `## Parties (Party Registry)` section (after Catalog, before Commerce & Membership) — lead + entries for **Party/party-type marker** (marker-on-subtype), **Customer** (PII-free event), **Account** (billing container, NOT a money ledger, event-silent), **Supplier** (minimal subtype), **Producer** (not a Party), **ProducerAgreement** (single-active = activation-time, not enforced at creation), **Profile** (membership in one Club, NO Membership entity) — plus a `### Parties spine creation events — payload contract` table (5 `*Created` events, keys **verified firsthand vs the `Events\*Created` classes**, spec § order) + explicit **Supplier/Account event-silence** note. **Enriched in place** (one definition per term): Club gained Module-K-registry facts (immutable Producer link, fee as Money, registration-flow classifier); Originating Club gained the born-NULL/no-setter/locked-at-first-approval-deferred seam note. **10 of 11 done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`.** Docs-only task → pint ✅ · pint --test ✅ · `openspec validate parties-core --strict` ✅ · composer diff vs main empty ✅. No PHP touched → test/type_check no-op, no PG17 run (no DB change). Prior code state stands: full **424/1618 SQLite** ✅ + **47/47 Parties on PG17** ✅ (last proven at 5.1).

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present. 10/11 done.
- **Next: task 6.2 — Full-chain integration + cross-engine close (FINAL task).** Write `tests/Feature/Modules/Parties/SpineCreationChainTest.php` driving the whole spine (Producer→Club→ProducerAgreement, Customer+Account, Profile, Supplier): assert the **five** `*Created` recorded, **zero** Supplier/Account events (`entity_type IN ('Supplier','Account')` count 0), **zero** `%Activated%`/lifecycle/`OriginatingClubLocked`, birth states hold (`pending`/`active`/`draft`/`active`/`draft`/`applied`), `CustomerCreated` PII-free, `ModuleBoundariesTest`+`ModulePersistenceConventionsTest` green (no amendment). Then the **full Parties suite on PG17** via `php -d memory_limit=512M vendor/bin/pest` (runner-artifact workaround — see patterns) recorded in progress.md. Then `<promise>CHANGE_COMPLETE</promise>`.

## Blockers & Decisions Needed
- **None.** D8 (partial-unique portability) RESOLVED at 5.1. Asymmetries holding: Supplier & Account emit **no** `*Created`; Originating Club = field only (born NULL, no setter); no lifecycle/`*Activated` events this slice.

## Open Patterns
- **CONTEXT.md module-section template** (proven twice — Catalog, Parties): `## <Module>` H2 lead → bold-term entries + `_Avoid_:` lines → `### <Module> spine creation events — payload contract` H3 with lead-note + event/entity_type/payload-keys table. **Docs that restate a code contract MUST re-verify keys vs the live classes** (the payload table was transcribed from the five `Events\*Created::payload()` this iteration — not from the hot-cache digest). **Glossary-of-record: enrich in place, never duplicate a term.**
- **Spine DB-entity template** (migration+model+factory+event+action), **partial unique index for live-only uniqueness** (5.1), **co-provisioning** (4.1), **two-FK/optional-FK idiom**, **party-type marker = no default/CHECK from full cases()**, **PII discipline** (event payloads + rejection copy), **date/Money/marker idioms**, **`{@see}`-FQN-import trap**, **PG17 gate every DB task**, **PG full-suite needs `-d memory_limit=512M vendor/bin/pest`** — all hold. `lang/en/parties.php` = shared rejection copy (`club`/`producer_agreement`/`customer`/`profile`). **log.md via `memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.**
