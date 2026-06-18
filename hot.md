---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph iter 1 — `parties-membership-activation` T1.1 DONE).** First production code of the demand-side activation spine: the additive migration adding the three onboarding-acceptance timestamps (`email_verified_at`, `tc_accepted_at`, `privacy_accepted_at`) to `parties_customers` — the gate inputs the later `ActivateCustomer` reads. Schema-only task; no Action/event yet.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green 786/786** (was 783; +3 from `CustomerOnboardingAcceptanceTest`) on **SQLite AND PG17**; phpstan 0; pint clean. `openspec validate parties-membership-activation --strict` green. `git diff main -- composer.json composer.lock` empty (no new dep).
- Branch `ralph/parties-membership-activation`; T1.1 committed locally (not pushed — human's call).

## Active Change & Next Task
- **`parties-membership-activation` — 1 / 7 tasks done.** Migration `2026_06_18_000002` + `Customer` casts/`@property`/docblock shipped. PG17-verified (cols = nullable `timestamptz`, no default; isolated `down()`/`up()` reversible).
- **Next: T1.2** — `IllegalProfileTransition` (`::cannotApprove/cannotReject/cannotActivate`) + `IllegalCustomerTransition` (`::cannotActivate/gateNotMet`) exceptions + localized copy in the `profile`/`customer` groups of `lang/en/parties.php`; mirror `IllegalProducerTransition`. **Pure PHP/i18n — NO DB, no PG run** (its acceptance says so). Then T1.3 (3 event classes + narrow `SupplyLifecycleChainTest`), T2.1–2.3 (the four Actions + guard-test narrowing), T3.1 (chain + docs + full PG17 close).

## Blockers & Decisions Needed
- None. Documented deferred seams stay deferred (NOT reads): **§13 Hero Package capacity** → Module A (`ApproveProfile`/`ActivateProfile` ship uncapped); **`MembershipFeePaid` listener** → Module E (`ActivateProfile` invoked directly); Hold→`suspended`, segments, producer/Filament UI → later slices. The three acceptance cols have no production setter yet (deferred registration surface — known additive-seam pattern).

## Open Patterns
- **Demand-side backend sequence:** activation spine (this change) → Club Credit → suspension + Hero Package → Admin Panel UI Phase 2 (Filament Resources — immediate next step after the demand-side backend, NOT deferred — `lessons.md` 2026-06-18).
- **Backend-green ≠ phase-complete** (judge across all workplan columns + supply/demand split).
- **New reusable patterns** (progress.md Codebase Patterns): additive-nullable-timestamp migration needs no CHECK; isolated PG17 `down()`/`up()` via `migrate:rollback --path=<mig>`; null-safe `?->format()` round-trip for nullable datetime casts (PHPStan-max clean); the arch-OOM 512M flag hits the full suite on SQLite too.
- **Guard tests pre-name the seams:** archived forbidden-Action / event-non-existence lists hand the next slice its zero-invention names; narrow each in the SAME task that ships the name (grep-derive the blast radius — `lessons.md`).
