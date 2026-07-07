---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-07
---

# Hot Cache

## Last Updated
**2026-07-07 — `parties-module-k-br-guards` ralph loop RUNNING. Task 2.4 (five localized BR-guard exceptions) DONE, committed — 7/23.** Authored the five `RuntimeException` subclasses the §3–5 guards will throw, as PURE definitions (factories + EN/IT copy + tests; NO Action wiring): `ProducerAgreementScopeConflict` (2 dir factories), `ProducerAgreementClubNotActive`, `ClubNotAcceptingMemberships`, `BelowMinimumRegistrationAge` (2 factories), `ProducerReviewGovernedContentLocked`. Each mirrors the `SeparationOfDutiesViolation` shape.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint. Task 2.4 full loop **green**: SQLite full suite **2004/2004** (1984 baseline + 20 new) · PHPStan max **0** · Pint clean · `openspec validate --strict` valid. Copy-parity `PartiesApprovalCopyTest` + all Parties exception tests 117/117.
- **PG17 recipe (for 7.1 gate + CHECK reject-lanes):** `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco php -d memory_limit=-1 vendor/bin/pest` (`pg` container UP on :55432; NOT :5432). Full suite via `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs at 128 MB). 2.4 touched no DB → no PG run needed.

## Active Change & Next Task
- **`parties-module-k-br-guards` — 7/23 done. NEXT = task 3.1 (RM-22):** enforce the closed cadence set server-side in `CreateProducerAgreement` — reject an out-of-set/typo cadence with a clean localized reject BEFORE the `SettlementCadence::from()` raw `ValueError`. _Acceptance:_ `ProducerAgreementTest` — `quarterly`/`monthly`/`semi_annual` admitted, `annual`/`weekly` rejected (no row, no event). **ℹ 2.1 ALREADY did the DemoSeeder `annual→semi_annual` + the cast round-trip** — 3.1 is ONLY the reject guard. Enum token is `semi_annual` (underscore), not `semi-annual`.
- **Scope after 3.1:** §3 rest (3.2 Agreement-4 → `ProducerAgreementClubNotActive::forClub`; 3.3 RM-20 → `ProducerAgreementScopeConflict::producerWideBlockedByClubScope|clubScopeBlockedByProducerWide`, inverts shipped `ProducerAgreementLifecycleTest` L157+L206) → §4 (4.1 RM-21 → `ClubNotAcceptingMemberships::forClub`; 4.2 CreateProfile auto_renew inheritance; **4.3/6.4 invite_only PRE-SATISFIED by 2.3 EXCEPT lang EN/IT `fields.invite_only` + `ClubConsoleI18nTest` L48/L62**) → §5 (5.1 Identity-6 → `BelowMinimumRegistrationAge::belowMinimum|missingDateOfBirth`; 5.2 Producer-5 → `ProducerReviewGovernedContentLocked::whileActive`) → §6 console+i18n → §7 close (human-gated).
- **The five exception factory NAMES/signatures are now fixed API** — wiring tasks call them verbatim (tasks.md 2.4 ℹ note).

## Blockers & Decisions Needed
- None. Change APPROVED; branch `ralph/parties-module-k-br-guards`.
- **Append memory files via the Edit tool, not `cat >>` heredocs** (git-guardrails hook false-positives on spec-path strings in the body).

## Open Patterns
(full form in progress.md `## Codebase Patterns`)
- **Localized guard exception = the SoD shape (2.4):** `RuntimeException` subclass, static named factories → `(string) __("parties.{group}.{key}", […])`; 2+ factories share a private `build()`, one inlines. Name the RULE, interpolate only non-PII (int id / state / `:min_age` — never DOB/derived age). IT genuinely authored when a task says "EN + IT" (IT⊆EN parity via `PartiesApprovalCopyTest`).
- **PHPStan-max landmine on data-driven Pest tests (2.4):** a loose `array $replace` param + `(string) $mixed` element BOTH red. Fix = group by placeholder shape, pass the key string via `->with()`, build the replacement array INLINE as a literal. Bare `RuntimeException::class` is CORRECT in a global-namespace Pest file; `new X('')` explicitly, never `new $class`.
- **i18n subset-run trap:** the shared `scanOperatorConsoleHardcodedSinks` helper lives in `ProductMasterConsoleI18nTest` — a Console i18n test run WITHOUT it fails on `function_exists`. Confirm against the FULL suite.
