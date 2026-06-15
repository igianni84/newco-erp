---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-15
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-15 16:54 (ralph iteration 2/20 — `parties-core` task 1.2 DONE).** Created the four identity/account backed string enums under `App\Modules\Parties\Enums\` (house style, mirroring `Catalog\Enums`): `PartyType` (`customer`/`supplier`/`third_party_owner` — full BR-K-Identity-5 domain, all 3 declared though only customer/supplier produced this slice), `CustomerStatus` (`pending`/`active`/`suspended`/`closed`), `AccountStatus` (`active`/`suspended`/`closed`), `AccountType` (sole `personal`). Test `tests/Unit/Modules/Parties/Enums/EnumsTest.php` mirrors catalog EnumsTest (verbatim+order-sensitive `cases()`→`name=>value`, count rules `AccountType`=1/`PartyType`=3, three `from()`→`ValueError` guards). No DB.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pennant 1.23 · spatie/laravel-permission 8.0.0 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev (`:memory:`); prod PostgreSQL 17.
- **Branch `ralph/parties-core`**, 2 of 11 tasks done. This iteration: 5 new files (4 enums + 1 test), no DB. Pint format ✅ · filtered test ✅ (7/11) · full suite ✅ (366 tests/1358 assertions) · phpstan max ✅ (0 errors) · pint --test ✅ · `openspec validate parties-core --strict` ✅ · composer diff vs main empty ✅. PG17 not needed (enum-only; gate applies to DB tasks, first is 2.1).

## Active Change & Next Task
- **`parties-core`** (capability `party-registry`; 11 tasks; 5 `*Created` events; 7 entities). APPROVED present.
- **Next: task 1.3 — Registry & membership enums** (no DB): `App\Modules\Parties\Enums\{ProducerStatus, ClubStatus, ClubRegistrationFlowType, ProducerAgreementStatus, ProfileState}`. Values (design D2): ProducerStatus `draft|active|retired`; ClubStatus `active|sunset|closed`; ClubRegistrationFlowType `open_registration|application_with_approval|invitation_only|link_onboarding`; ProducerAgreementStatus `draft|active|superseded|terminated`; ProfileState `applied|waiting_list|approved|rejected|active|suspended|lapsed|cancelled|inactive`. **EXTEND** the existing `EnumsTest.php` (keep imports alphabetised, same verbatim+count shape); assert `ProfileState::cases()` length 9 and the terminal set `{rejected,cancelled,inactive}` (D8 partial-index predicate).

## Blockers & Decisions Needed
- **None.** Marker representation decided (ADR `2026-06-15-party-type-marker-on-subtype`). This slice steps through no open ADR gate.
- Spec-faithful asymmetries to keep: Supplier & Account emit **no** `*Created` (PRD §15); Originating Club = **field only** (`originating_club_id` born NULL, no setter — lock deferred to membership lifecycle).
- Open ADR gates (future): queue driver (F4–F6) · object storage (INV1) · hosting EU (staging) · frontend TanStack/Sanctum (Module S) · authority-tier RBAC · lifecycle FSM.

## Open Patterns
- **Enum-test convention** — `tests/Unit/Modules/Parties/Enums/EnumsTest.php`, no `RefreshDatabase`; per enum map `cases()`→`name=>value` and assert verbatim + order-sensitive `->toBe([...])`; `->toHaveCount(n)` where "exactly N cases" is a spec rule; one `from()`→`ValueError` guard per hard-domain enum. House-style enum = one docblock (design ref + spec § + persisted-token note) then bare `case`s; values are persisted snake_case tokens (not user copy → no i18n).
- **Spine DB-entity template** (progress.md `## Codebase Patterns`, top — read first): per evented entity = `parties_*` migration (+ driver-guarded enum `CHECK`) + `Models\X` (`$table='parties_X'`, `$guarded=[]`, typed `newFactory()`) + `Database\Factories\Parties\XFactory` + `Events\XCreated` (final, static `payload()` PII-free) + `Actions\CreateX` (`DB::transaction` insert → `DomainEventRecorder::record(...)`). `catalog-product-spine` archive is the worked precedent.
- **Two event silences** (D7): `CreateSupplier` + Account leg of `CreateCustomer` record NO event. **PII-free `CustomerCreated`** (omit email/name/phone/DOB).
- **Within-module only** — within-module FKs + relations allowed; no cross-module ref this slice; arch tests stay green unamended; `$table='parties_*'` on every model.
- **PG17 gate** — every DB-touching task verified on local PostgreSQL 17 before done (`knowledge/testing/rules.md`); recorded at task 6.2.
- **Verify-firsthand habit** — confirm every spec §/DEC/symbol before citing. **log.md:** append ONLY via `scripts/memlog.sh`; hot.md ≤550 words. **APPROVED = human-only.**
