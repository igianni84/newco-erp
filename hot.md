---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-09
---

# Hot Cache

## Last Updated
**2026-07-09 — RM-05 task 2.2 green: the seat gate ships. `ApproveProfile` locks the Club row, counts, diverts at parity.** Branch `ralph/parties-hero-package`, **6/16**. Local commits, not pushed.

## Build & Quality Status
- PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- SQLite **2292/2292** (2280 + 13 new − 1 removed dataset row) · PHPStan **0** · Pint clean · `validate parties-hero-package --strict` green. **PG17 not re-run** — first required at 3.2.
- Suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 env prefix: see the `tasks.md` header.

## Active Change & Next Task
- **`parties-hero-package` — 6/16.** Next: **2.3 — `DeclineProfile`: from-state widens to `{applied, waiting_list}`.** `WaitingList → Rejected`, audit-only, still event-silent, still **no constructor** (no Club lock, no capacity read — a decline neither frees nor consumes a seat).
- **2.3's dataset flip is already done.** 2.2 removed `'waiting_list → hidden + rejected'` from `ProfileApprovalConsoleTest`'s reject-floor dataset entirely (it asserted `toThrow` for *both* verbs). So 2.3 adds decline-from-`waiting_list` coverage in its own file; it does not touch that dataset again.
- **2.4 (`RenewProfile`) inherits 2.2's ordering**: from-state guard → **30-day grace guard** → Club-row lock → count → capacity gate. At parity it **throws** (`clubAtCapacity`), never diverts — canon draws no `Lapsed → WaitingList` edge.
- **ADR `2026-07-09-hero-package-capacity-seat-set-and-waitinglist` is the plan; `design.md` D1–D11 restates it.** Don't re-derive it; don't re-ground against canon.
- **⚠️ Closes against a documented SUBSET:** `J-14`/`J-15`/`J-15a`/`XM-19` NOT met. Task 7.2 forces this onto the tracker.

## Blockers & Decisions Needed
- **None blocking.** Landmines (fuller in `design.md` / `tasks.md` / `progress.md` § Codebase Patterns):
  1. **The delta spec `:212` orders the from-state guard AFTER the capacity gate. It is wrong.** Guard first, or an `active` Profile in a full Club gets diverted onto the waitlist. D8's table is authoritative. Pinned by a 7-state dataset in `ProfileApprovalCapacityGateTest`.
  2. **No new class in `Actions/`** — `SupplyLifecycleChainTest` set-pins it. `Contracts/`/`Reads/`/`Support/`/`Exceptions/`/`Events/` unpinned.
  3. **Test-env capacity default stays `null`** — *why* the 2280 prior tests are unmoved. Per-test: `config()->set('parties.hero_package.capacity.by_club_id', [$club->id => N])`.
  4. **PHPStan max audits test fakes** (`return.unusedType`), **production ctors** (`property.onlyWritten` on an unread port), and **`?int` array offsets** (`offsetAccess.notFound` on a `foreach`-found index — filter the `DB::listen` capture and use literal offsets, as `ClubSeatOccupancyTest` does).
  5. **A Pest global helper is per-PROCESS.** A second `function clubAtCapacity()` is a fatal redeclare; and never call another file's helper (a `--filter` run may not load it).
  6. **Pint promotes a docblock `{@see \FQCN}` into a real `use` import** — name non-dependencies as backticked prose, or 4.1's *no `Modules\Allocation\*` import* assertion breaks on prose.
  7. **No `markTestSkipped` in `tests/`**; driver-gated lanes assert both halves (`ActorRoleConstraintTest:110`).
  8. **`MembershipSuspensionChainTest:265`** (`WaitingListJoined` pinned at count 0) survived **both** writers — 2.1's birth and 2.2's divert. Trustworthy for the rest of the change; if it reddens, a fixture leaked a capacity.
  9. Blast radius is behavioural — **only the full suite is proof.**
- **Logged, out of scope:** an `operator-console` Profile `activate` verb that never existed; `DemoSeeder` rests `jeanluc-krugd` durably in `Approved`. Both contradict RM-03.
- **Tracker §7:** F8 (MVP-DEC-030 → RM-26/27) · F9 (canon #18 OPEN: auth is **OTP**) · F10 (`spec/` 29 commits behind — needs an ADR, Giovanni's call).

## Open Patterns
- **A green acceptance test can pass against a racy implementation.** `AC-K-J-13`'s 51st approve is *sequential*; the oversell race survives it. A criterion tests a scenario, not an invariant. **3.2 is the only proof of D3.**
- **Lock-before-read is invisible on SQLite** (no lock clause compiled). Pin it as SQL **statement order** (`DB::listen`) + driver-gate `for update`. Order ≠ serialisation.
- **PHPStan enforces the design where a comment would only describe it.** `clubAtCapacity(int $capacity, …)` is non-nullable *on purpose*: a caller cannot build the rejection without first establishing the capped branch. Narrow with `$capacity !== null &&`; never `(int)`-cast, never `??`.
- **An event payload snapshots the row; it never asserts its own trigger.** A writer bug must reach the audit store.
- **`0` is a capacity, not an absence.** `is_numeric` / `array_key_exists`, never `??`. Capacity `0` is also the cheapest at-parity fixture — but `AC-K-J-13`'s cardinal (51st of 50) earns the real one.
- **`RenewProfile` is `lapsed→active` and cap-gated**; the *grandfathered* renewal is an unmodelled period rollover. Same word, opposite rule.
- **Always `MVP-DEC-NNN`**, never bare `DEC-NNN`. A verifier's finding is a **candidate** — check every quote against the file.
- At build time re-read `knowledge/filament/hypotheses.md` + `lessons.md` 2026-07-06.
