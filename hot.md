---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 16:49 (ralph iteration 1/20 — `parties-core` task 1.1 DONE).** Wrote the one new ADR this change introduces: `decisions/2026-06-15-party-type-marker-on-subtype.md` (marker-on-subtype; unified `parties_parties` registry + `third_party_owner` entity + marker overlap deferred to a future `parties-party-registry` slice; Producer carries no marker). INDEX row added. Every spec citation verified firsthand (DEC-067, DEC-073, Module K PRD §4.4/§4.5, BR-K-Identity-5). Docs-only — format + lint + `openspec validate --strict` green.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`**, 1 of 11 tasks done. This iteration touched only `decisions/**` + the change artifacts (no PHP, no DB). Pint format ✅ · Pint --test ✅ · `openspec validate parties-core --strict` ✅ · composer diff vs main empty. Full test/phpstan not run (nothing in PHP changed — docs-only task).

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present.
- **Next: task 1.2 — Identity & account enums** (no DB): `App\Modules\Parties\Enums\{PartyType, CustomerStatus, AccountStatus, AccountType}`. `PartyType` MUST carry all 3 markers (`customer`/`supplier`/`third_party_owner`) per the ADR + BR-K-Identity-5; `AccountType` sole case `personal` (assert `cases()` length 1). Test: `tests/Unit/Modules/Parties/Enums/EnumsTest.php`, mirror `tests/Unit/Modules/Catalog/Enums/EnumsTest.php` (no RefreshDatabase; map `cases()`→`name=>value`, order-sensitive `->toBe([...])`).

## Blockers & Decisions Needed
- **None.** Marker representation now decided (ADR above). This slice steps through no open ADR gate.
- Spec-faithful asymmetries to keep: Supplier & Account emit **no** `*Created` (PRD §15 names none); Originating Club = **field only** (`originating_club_id` born NULL, no setter — lock deferred to membership lifecycle).
- Open ADR gates (future): queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/Sanctum (Module S) · authority-tier RBAC · lifecycle FSM.

## Open Patterns
- **Spine DB-entity template** (progress.md `## Codebase Patterns`, top — read first): per evented entity = `parties_*` migration (+ driver-guarded enum `CHECK`) + `Models\X` (`$table='parties_X'`, `$guarded=[]`, typed `newFactory()`) + `Database\Factories\Parties\XFactory` + `Events\XCreated` (final, static `payload()` PII-free) + `Actions\CreateX` (`DB::transaction` insert → `DomainEventRecorder::record(...)`). `catalog-product-spine` archive is the worked precedent for every idiom.
- **Two event silences** (D7): `CreateSupplier` + Account leg of `CreateCustomer` record NO event. **PII-free `CustomerCreated`** (omit email/name/phone/DOB).
- **Within-module only** — within-module FKs + relations allowed; no cross-module ref this slice; arch tests stay green unamended; `$table='parties_*'` on every model.
- **PG17 gate** — every DB-touching task verified on local PostgreSQL 17 before done (`knowledge/testing/rules.md`); recorded at task 6.2. Cross-engine recipe: `docker run -d --name pg … postgres:17`, busy-poll `pg_isready`, `DB_CONNECTION=pgsql php artisan test`, `docker rm -f pg`.
- **Verify-firsthand habit** — confirm every spec §/DEC/symbol before citing; Module K PRD entity sections are `### §4.x` (grep `§4\.`). **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words. **APPROVED = human-only.**
