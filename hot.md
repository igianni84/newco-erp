---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-06-23
---

# Hot Cache

## Last Updated
**2026-06-23 (`operator-console-parties-membership` — group 7 GREEN, 14/16 tasks).** Shipped the demand-side membership console's **capability-close i18n guard** `tests/Feature/Modules/OperatorPanel/Parties/ProfileConsoleI18nTest.php` (the FIRST OperatorConsole i18n test to span TWO `operator_console` blocks). Two module-level dataset fns (unique names): `profileConsoleKitKeys()` → 39 **full** `operator_console.*` dot-paths = 33 `profile.*` (groups 1–5) + 6 new `customer.*` Account verb keys (`actions.{suspend,reactivate,close}_account` + `notifications.account_{suspended,reactivated,closed}`, group 6 — absent from the older `customerConsoleKitKeys()`); `profileConsoleItDiffersKeys()` = `array_diff` carving out `profile.{label,plural_label}` (EN-fallback) + `profile.{columns,fields}.club` (loanword). Six `it(...)`: EN-completeness(39) · IT-distinct(35) · EN-fallback(2) · **loanword-identity**(2, novel: authored-in-IT AND `==='Club'` AND `===EN`) · IT⊆EN scoped to `profile.` · sink-scan over `ProfileResource*` reusing `scanOperatorConsoleHardcodedSinks` behind a `function_exists` guard (NO redeclaration). No production/lang edits — every key already authored.

## Build & Quality Status
- Stack: PHP 8.5.2 · Laravel 13.15 · Filament 5.6.7 · Pest 4.7.2 · PHPStan 2.2.2 (max) · Pint 1.29.1. SQLite dev; prod PG17.
- **Last GREEN (this session, group 7):** filtered **80/80** (161 assertions, via `--filter=ProfileConsoleI18nTest`) · full suite **1725/1725** (9327 assertions, SQLite) · PHPStan max 0 · Pint clean · `openspec validate operator-console-parties-membership --strict` valid.
- **Full suite: `php -d memory_limit=-1 vendor/bin/pest` — NOT `artisan test`** (OOMs at 128 MB; lesson 2026-06-20). PG17 close-ritual run is task 8.1.

## Active Change & Next Task
- **`operator-console-parties-membership` — groups 1–7 done; 2 tasks left.** Next: **8.1** = new `tests/Feature/Modules/OperatorPanel/Parties/ProfileMembershipChainTest.php` — a single self-contained **PG17** integration test driving the FULL Profile FSM through the Filament pages: create(→Applied)→approve(→Approved, assert `OriginatingClubLocked`)→activate(→Active)→suspend(→Suspended)→reactivate(→Active)→lapse(→Lapsed)→renew(→Active)→cancel/deactivate(terminal); PLUS the Account suspend→reactivate→close chain on `ViewCustomer`. Assert event sequence + final states. **PG17 ritual:** throwaway `postgres:17` Docker, `DB_CONNECTION=pgsql … php -d memory_limit=-1 vendor/bin/pest --filter=ProfileMembershipChainTest` (GUIDE §2.7; `knowledge/testing/rules.md`). A self-contained chain test (NO shared i18n helper) IS safe via `--filter` alone. Then **8.2**: full-suite/PHPStan/Pint/openspec gate; confirm no new `Parties\Actions\*` (grep — `SupplyLifecycleChainTest` whitelist unchanged, design D8); consolidate `progress.md` Codebase Patterns → **CHANGE_COMPLETE**.

## Blockers & Decisions Needed
- **No blocker.** Boundary seams unchanged (out of scope, design Non-Goals): `Applied→WaitingList` (no writer), activation capacity cap (Module-A seam — activation ships uncapped), `MembershipFeePaid`/renewal trigger (Module-E seam), Producer-Portal TanStack UI.

## Open Patterns
- **Capability-close i18n test = the Customer/Club `*ConsoleI18nTest` shape** (Feature, no DB): `<entity>ConsoleKitKeys()` (kit's concatenated keys ∪ resource's literal `__()` keys) + `array_diff` differs set; EN-completeness · IT-distinct · EN-fallback(`label`/`plural_label`) · IT⊆EN(`Arr::dot` by block-prefix trailing dot) · sink-scan reusing the shared scanner behind `function_exists`, scoped `str_contains(pathname,'<Entity>Resource')`. **Run via `--filter`/full suite, NEVER a bare path** (shared scanner declared only in Catalog `ProductMasterConsoleI18nTest`; bare path → undeclared → false red, lesson 2026-06-20).
- **Dual-block i18n test (group 7):** copy spanning two `operator_console` blocks → kit-keys returns FULL dot-paths; EN-completeness+IT-distinct cover both; IT⊆EN scopes to the NEW block only (sibling block rides its own test). **Loanword carve-out:** `*.club` authored-in-IT but `==='Club'`-identical → carve from differs AND give a dedicated loanword-identity `it()` (≠ the omit-from-IT `label`/`plural_label` fallback).
- **Lifecycle verb (own/related record):** `lifecycleAction($verb,$key,$invoke)->visible(fn () => $this->stateIs/accountStatusIs('<from>'))`; cast `->value`, no enum import; related entity routes by nested `?->id`. Section = one atomic commit (groups 1–7); no new `Parties\Actions\*` → `SupplyLifecycleChainTest` whitelist unchanged.
