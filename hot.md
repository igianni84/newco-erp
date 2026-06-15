---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 18:51 (ralph iteration 11/20 — `parties-core` task 6.2 DONE → CHANGE COMPLETE).** Final task: the full-chain integration test + the cross-engine close. **TEST-ONLY** (no production code, no migration). New `tests/Feature/Modules/Parties/SpineCreationChainTest.php` drives the WHOLE spine through its `Create*` actions in dependency order (Producer→Club→ProducerAgreement, Customer+Account, Profile, Supplier) via one typed helper `createPartiesSpine()`, with 4 focused `it()`s: (1) **exactly five `*Created`** recorded (`count()===5` + `pluck('name')->toEqualCanonicalizing` the 5 names) all tagged `parties`/`System`, **zero** `entity_type IN ('Supplier','Account')`; (2) **zero** `%Activated%`/`%Approved%`/`%Suspended%`/`%Sunset%`/`%Retired%`/`%Superseded%`/`%Terminated%`/`OriginatingClubLocked`; (3) **birth states** pending/active/draft/active/draft/applied (+ Supplier marker, no status col); (4) **PII-free `CustomerCreated`** (exact key set + no email/name/phone/DOB) and the silent Account. **11 of 11 done.**

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`.** Green on BOTH engines: pint ✅ · filtered **4/31** ✅ · full **428/1649 ✅ SQLite AND 428/1649 ✅ PostgreSQL 17** (`php -d memory_limit=512M vendor/bin/pest`) · phpstan max **0** ✅ · pint --test ✅ · `openspec validate parties-core --strict` valid ✅ · composer diff vs main empty ✅. Focused PG17: Parties Feature+Unit **69/302** ✅, arch `ModuleBoundariesTest`+`ModulePersistenceConventionsTest` **3/154** ✅ **unamended**.

## Active Change & Next Task
- **`parties-core` — ALL 11 TASKS DONE.** Capability `party-registry`: 7 entities, 5 `*Created` events, 2 deliberate event silences (Supplier, Account), partial-unique Profile index, OC born-NULL seam. The reply this iteration is **`<promise>CHANGE_COMPLETE</promise>`**.
- **Next (HUMAN, not the loop):** review/merge `ralph/parties-core` → semantic-verify (GUIDE §2.7) → `openspec archive parties-core --yes`. The loop does NOT archive or merge.

## Blockers & Decisions Needed
- **None.** D8 (partial-unique portability) resolved at 5.1. All scope-guard asymmetries held through the close: Supplier & Account emit no `*Created`; OC = field only (born NULL, no setter); no lifecycle/`*Activated`/`OriginatingClubLocked` event anywhere in the slice (proven by the integration test).

## Open Patterns
- **Whole-slice integration test = an event-SET assertion** (6.2): drive every entity through its real action, then `DomainEvent::count()` + `pluck('name')->toEqualCanonicalizing([the *Created set])` proves both "all fired" AND "nothing extra fired"; pair with `entity_type IN (silent types)===0`. The closing task of every module spine.
- **Typed free helper > `$this`/`beforeEach`** for a shared fixture: `function x(): array{...}` returns a PHPStan-max-indexable shape (explicit array literal of typed locals, NOT `compact()`). **`pluck('col')` on a builder is the RAW column** — hydrate (`get()->every(...)`) to see the cast enum.
- **Cross-engine close at scale = `php -d memory_limit=512M vendor/bin/pest` on BOTH engines** (Arch OOM + pao stdout fatal are runner artifacts — knowledge/testing). Full suite (428) on PG ~24s; it covers the full Parties set + arch tests = the definitive gate.
- Spine DB-entity template, partial-unique index, co-provisioning, two-FK idiom, party-type marker (no default), PII discipline (payload + rejection copy), date/Money/marker idioms, `{@see}`-FQN-import trap, PG17 gate every DB task — all proven across 2.1→5.1. `lang/en/parties.php` = shared rejection copy. **log.md via `memlog.sh` only; hot.md ≤550 words; APPROVED = human-only.**
