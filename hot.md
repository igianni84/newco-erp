---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-18
---

# Hot Cache

> Auto-injected at session start and post-compaction via hooks. OVERWRITTEN completely, never appended.
> Updated by: every ralph iteration (mandatory), and any interactive session that materially changes the repo.

## Last Updated
**2026-06-18 (ralph iter 2 — `parties-membership-activation` T1.2 DONE).** The two demand-side transition-guard exceptions — the localized reasons the activation Actions (T2.x) will throw — plus their `lang/en/parties.php` copy. Pure PHP/i18n: no DB, no migration, no PG run (acceptance says so). The Actions/events themselves still come in T1.3 + T2.x.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 · Pint 1.29.1. SQLite dev; prod PG17.
- **Green 797/797** (was 786; +11 from `MembershipTransitionExceptionsTest`) on SQLite; phpstan 0; pint clean. `openspec validate parties-membership-activation --strict` green. `git diff main -- composer.json composer.lock` empty (no new dep).
- Branch `ralph/parties-membership-activation`; T1.2 committed locally (not pushed — human's call). No PG run this task (no DB touched).

## Active Change & Next Task
- **`parties-membership-activation` — 2 / 7 tasks done.** Shipped: T1.1 migration (3 acceptance timestamps); T1.2 `IllegalProfileTransition` (`::cannotApprove/cannotReject/cannotActivate`) + `IllegalCustomerTransition` (`::cannotActivate/gateNotMet`) + 5 lang keys in existing `profile`/`customer` groups (`gate_not_met` PII-free, no placeholder).
- **Next: T1.3** — the three `final` event classes `{CustomerActivated, ProfileActivated, OriginatingClubLocked}` (untyped `const NAME` = verbatim §15 name, `const ENTITY_TYPE`, static `payload()` — PII-free, mirror `ProducerActivated`), **then narrow `SupplyLifecycleChainTest`'s event-non-existence loop** (remove only those 3; KEEP `AccountActivated`/`ProfileApproved`/`CustomerSegmentChanged`). **First guard-test edit of this change** — `grep -rn 'ProfileActivated\|CustomerActivated\|OriginatingClubLocked' tests/` to confirm it's the only existence-guard touched. Still NO DB → no PG run. Then T2.1–2.3 (Actions + guard narrowing, PG17), T3.1 (chain + docs + full PG17 close).

## Blockers & Decisions Needed
- None. Documented deferred seams stay deferred (NOT reads): §13 Hero Package capacity → Module A (Approve/Activate ship uncapped, L7); `MembershipFeePaid` listener → Module E (`ActivateProfile` invoked directly, L5); Hold→`suspended`, segments, WaitingList, producer/Filament UI → later slices. The three acceptance cols (T1.1) have no production setter yet (deferred registration surface).

## Open Patterns
- **Verb mapping for T2.x:** `cannotReject` is thrown by `DeclineProfile` (factory named for the `Rejected` target state; copy uses operator verb "decline"). `ActivateCustomer` throws `cannotActivate(CustomerStatus)` for wrong from-state AND `gateNotMet()` for the composite onboarding gate.
- **PII-free argless-exception test idiom** (progress.md): prove no value leaked with `expect(preg_match('/\d/', $msg))->toBe(0)` + `toContain('<rule-word>')`; complements the sibling "absent-token interpolation proof" for `:state` keys.
- **Guard tests pre-name the seams** (lessons.md): archived forbidden-Action / event-non-existence lists hand the next slice its zero-invention names; narrow each in the SAME task that ships the name (grep-derive the blast radius first). T1.3 is the first such edit here.
- **Backend-green ≠ phase-complete**; the arch-OOM needs `php -d memory_limit=512M vendor/bin/pest` for the full suite (SQLite & PG).
