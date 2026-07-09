# Tasks — parties-hero-package

> One task per loop iteration. Tests are NEVER optional. Full suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` up). Typecheck `php -d memory_limit=-1 vendor/bin/phpstan analyse`; format `vendor/bin/pint`.
>
> **Read `design.md` D1–D11 first, and the ADR it points at.** The ADR is the plan; do not re-derive it and do not re-ground against canon.
>
> **Three rules that this change dies on:**
> 1. **No new class under `app/Modules/Parties/Actions/`.** `SupplyLifecycleChainTest` asserts `toEqualCanonicalizing` over every non-`Create*` file there (D10).
> 2. **The test-env capacity default MUST stay `null` (uncapped).** All 2221 existing tests depend on today's behaviour; `null ⇒ uncapped ⇒ unchanged`.
> 3. **Only the full suite is proof** (`lessons.md` 2026-07-06). 7 of the 16 test files that execute these Actions drive them through Filament `callAction('approve')`, which `grep ApproveProfile` never sees. A `--filter` run is not proof.

## 1. Substrate — the capacity port, the seat count, the exception, the event

- [x] 1.1 Add the capacity read-port + its config-backed launch adapter + `config/parties.php`, bound in `PartiesServiceProvider::register()` (D1, D2)
  - `app/Modules/Parties/Contracts/HeroPackageCapacityReader.php` — interface, one method `forClub(int $clubId): ?int`, `null` ⇒ uncapped. Mirror the docblock discipline of `Contracts/CustomerTransactionTotalsReader.php`
  - `app/Modules/Parties/Reads/ConfigHeroPackageCapacityReader.php` — reads `config('parties.hero_package.capacity.by_club_id')[$clubId]` then falls back to `config('parties.hero_package.capacity.default')`. **Cast to `int`**: `env()` yields a string, the contract returns `?int`
  - `config/parties.php` — `'hero_package' => ['capacity' => ['default' => env('PARTIES_HERO_PACKAGE_CAPACITY'), 'by_club_id' => []]]`, with a banner comment naming Module A as the owner of `qty` and this file as the launch adapter's source
  - Bind in `PartiesServiceProvider::register()` with a plain `bind` (**not** `singleton` — match `:28` and `:35`)
  - **Do NOT set the env var in `phpunit.xml` or `.env.testing`.** Default `null` ⇒ uncapped ⇒ every existing test unchanged
  - Tests: adapter returns `int` for a configured `default`, `int` for a `by_club_id` override (override wins over default), `null` when neither is set; a **string** config value (the `env()` shape) still returns `int`; the container resolves the interface to the config adapter
  - Typecheck passes; tests pass

- [x] 1.2 Add the K-internal seat-occupancy helper `app/Modules/Parties/Support/ClubSeatOccupancy.php` (D3, D10)
  - **`Support/`, never `Actions/`** — a new `Actions/` file fails `SupplyLifecycleChainTest`. `Support/` already exists (`AnonymisedPlaceholders.php`)
  - Exposes the Club-row lock + count: acquires `Club::query()->whereKey($clubId)->lockForUpdate()->firstOrFail()` **then** counts `Profile::query()->where('club_id', …)->whereIn('state', [Active, Suspended])->count()`. **Lock strictly before count** — that ordering *is* the fix
  - Exposes a "would this transition oversell?" predicate that reads capacity via the injected `HeroPackageCapacityReader` and returns false when capacity is `null`
  - **Not** a `Contract`, **not** bound, **not** published cross-module (ADR: zero consumers ⇒ dead code)
  - Docblock states it MUST be called inside an open `DB::transaction` (the lock is meaningless otherwise) and that SQLite no-ops `lockForUpdate`
  - Tests: occupancy counts `Active` + `Suspended` and **excludes** `Applied`, `WaitingList`, `Rejected`, `Lapsed`, `Cancelled`, `Inactive` (drive all 9 states in one Club — this is the `AC-K-J-13` seat-set proof); occupancy is per-Club (a second Club's Profiles never leak in); `null` capacity ⇒ never at capacity
  - Typecheck passes; tests pass

- [ ] 1.3 Add the capacity rejection to `IllegalProfileTransition` + EN/IT copy (D8)
  - New static factory (no capacity constructor exists today). It serves **two** callers: `ApproveProfile` on a still-waitlisted Profile at parity, and `RenewProfile` at parity
  - Follow the shipped idiom exactly: `new self((string) __('parties.profile.<key>', [...]))`. Include the Club's capacity and current occupancy in the placeholders so the operator's danger toast says *why*
  - `lang/en/parties.php` → new key in the `profile` group. `lang/it/parties.php` → the IT counterpart. **IT is a documented subset**, but `PartiesApprovalCopyTest` enforces IT ⊆ EN, so an IT key without its EN counterpart fails
  - Tests: the factory renders the EN message with placeholders substituted; the IT locale renders the IT message; the existing IT ⊆ EN copy test stays green
  - Typecheck passes; tests pass

- [ ] 1.4 Add `app/Modules/Parties/Events/WaitingListJoined.php` (D7)
  - Static-holder shape, `final`, exactly like `ProfileCreated`: `public const NAME = 'WaitingListJoined'`, `public const ENTITY_TYPE = 'Profile'`, `public static function payload(Profile $profile): array`
  - Payload exactly `{profile_id, customer_id, club_id, state}` — ids and enum values only, **PII-free**
  - **Invert `ProfileState.php:20`**, whose docblock currently asserts the enum *"emits no `*Activated`/`ProfileExpired`/**`WaitingListJoined`**/etc."*
  - Tests: a unit pin in the style of `tests/Unit/Modules/Parties/Events/ActivationEventsTest.php` — pins `NAME`, `ENTITY_TYPE`, `final`, and the PII-free payload keys
  - Typecheck passes; tests pass

## 2. Domain — the gate, the divert, the widened from-states

> Each task below flips existing green tests. **Update them in the same task**, or the iteration closes red. Never "fix" the code to keep a stale pin green.

- [ ] 2.1 `CreateProfile`: birth in `WaitingList` when the target Club is at capacity (D6)
  - Inject `HeroPackageCapacityReader` + the occupancy helper. Evaluate the **Club-active gate first**, the capacity read second: a `sunset` Club rejects outright, it never waitlists
  - Born `waiting_list` ⇒ record `ProfileCreated` **and** `WaitingListJoined`. Born `applied` ⇒ `ProfileCreated` only
  - **No Club-row lock here** (D6): neither `Applied` nor `WaitingList` holds a seat, so this gate cannot oversell. Adding a lock would serialise every application in a Club for no invariant gain
  - `auto_renew` inheritance and the duplicate guard are untouched; `waiting_list` is non-terminal, so the existing partial-unique index blocks a second live Profile with **no migration**
  - Tests: at-capacity Club ⇒ born `waiting_list` + both events; free-seat Club ⇒ born `applied` + `ProfileCreated` only; uncapped Club ⇒ born `applied`; `sunset` Club at capacity ⇒ `ClubNotAcceptingMemberships`, nothing created; a `waiting_list` Profile blocks a duplicate for the pair
  - `ProfileAutoRenewTest`'s `'auto_renew'` writer-set assertion still yields exactly `['CreateProfile','SetProfileAutoRenew']`
  - Typecheck passes; tests pass

- [ ] 2.2 `ApproveProfile`: Club-row lock → seat count → gate; at parity transition to `WaitingList`; from-state widens to `{applied, waiting_list}` (D3, D4, D8)
  - **Take the `parties_clubs` row lock BEFORE counting**, inside the existing transaction. Keep the existing Profile-row lock and Customer-row lock. This is the oversell-race fix (D3)
  - From-state guard widens to `{Applied, WaitingList}`; every other state still `cannotApprove`
  - Free seat (or uncapped) ⇒ unchanged behaviour: `Approved` transient → delegate to `ActivateProfile` → `Active`; conditional `OriginatingClubLocked`; `ProfileActivated`
  - At parity **and** from `Applied` ⇒ **transition, do not throw**: write `state = waiting_list`, record exactly one `WaitingListJoined`, **no** charge, **no** OC lock, **no** `ProfileActivated`
  - At parity **and** from `WaitingList` ⇒ throw the 1.3 capacity rejection. **No** second `WaitingListJoined`, no state write
  - Replace the `HERO PACKAGE CAPACITY GATE — DEFERRED MODULE-A SEAM … ships UNCAPPED` docblock paragraph (`:58-63`) — it is now false
  - **Flips these existing tests — invert them here:** `ProfileApprovalConsoleTest.php:186` — remove `'waiting_list → hidden + rejected'` from the reject-floor dataset (approve/decline are now **legal** from `waiting_list`; under the uncapped test default the approve now *converts*, so the `toThrow` assertion goes red)
  - Tests: 51st approve against a 50-seat Club ⇒ `waiting_list` + `WaitingListJoined`, occupancy still 50, no `ProfileActivated`, no `OriginatingClubLocked`, `originating_club_id` still null (`AC-K-J-13` leg 1); conversion `waiting_list → active` once a seat frees, recording `ProfileActivated` **and** the first-ever `OriginatingClubLocked`; approve on a still-at-parity `waiting_list` Profile ⇒ capacity rejection, no second event; uncapped Club ⇒ every approve activates
  - Typecheck passes; **full suite** passes (not `--filter`)

- [ ] 2.3 `DeclineProfile`: from-state widens to `{applied, waiting_list}`
  - `WaitingList → Rejected`, audit-only, still event-silent, still no constructor
  - Takes **no** Club-row lock and reads **no** capacity: a decline neither frees nor consumes a seat
  - **Flips:** the same `ProfileApprovalConsoleTest` reject-floor dataset (if 2.2 left the decline half asserting `toThrow` on `waiting_list`)
  - Tests: decline from `applied` ⇒ `rejected`, zero events (unchanged); decline from `waiting_list` ⇒ `rejected`, zero events; decline from any other state ⇒ `cannotReject`; re-application after a waitlist decline inserts a fresh Profile (partial-unique index admits it)
  - Typecheck passes; tests pass

- [ ] 2.4 `RenewProfile`: cap-gated, grace sub-gate evaluated **first** (D8, D9)
  - ⚠️ **The naming trap (D9).** Our `RenewProfile` is `lapsed → active` — a **cap-gated re-activation** (canon §13.1:627, :629). The *grandfathered* renewal of `MVP-DEC-011`/`AC-K-J-15a` is an `Active` **period rollover we do not model**. Same word, opposite rule. Do not "grandfather" this Action
  - Order: from-state guard → **30-day grace guard** → Club-row lock → seat count → capacity gate. A past-grace renewal reports the **grace** reason regardless of capacity
  - At parity ⇒ throw the 1.3 capacity rejection. **Do NOT divert to `WaitingList`**: canon draws no `Lapsed → WaitingList` edge, and diverting would clear `lapsed_at` and burn the grace clock
  - On rejection: `state` stays `lapsed`, `lapsed_at` **unchanged**, no `ProfileRenewed`
  - Tests: renew within grace + free seat ⇒ `active`, `lapsed_at` cleared, one `ProfileRenewed`; renew within grace at parity ⇒ capacity rejection, still `lapsed`, `lapsed_at` intact, **not** `waiting_list`, zero events; then free a seat and renew again within grace ⇒ succeeds; past-grace at parity ⇒ **grace** reason, not the capacity reason; uncapped ⇒ unchanged behaviour
  - Typecheck passes; tests pass

## 3. The deliberate non-gates, and their regression proofs

- [ ] 3.1 `ReactivateProfile` and `ActivateProfile` stay ungated — prove it, and delete the false docblocks (D4)
  - **Code change is docblocks only.** Add **no** gate to either Action. `ActivateProfile.php:49-54` and `ApproveProfile`'s sibling paragraph both claim an `UNCAPPED / DEFERRED MODULE-A SEAM` that is now false
  - `ActivateProfile` docblock: `Approved` is transient, so `Approved → Active` never *newly* consumes a seat; the gate lives on the seat-consuming caller, which evaluates it under the Club-row lock before delegating; gating here would count the same seat twice. When the Module-S `MembershipFeePaid` listener lands, **it** becomes a seat-consuming entry point and carries the gate at its own boundary
  - `ReactivateProfile` docblock: a `Suspended` Profile **keeps its seat**; re-checking would let a temporary Hold evict a member
  - Tests (the load-bearing regressions — `AC-K-J-13` leg 2 / `AC-K-FSM-2a`):
    - `ReactivateProfile` on a `Suspended` Profile in a Club at **exact parity** ⇒ becomes `Active`, one `ProfileReactivated`, **no** capacity rejection
    - `SuspendProfile` does **not** free a seat: at parity, suspend a member then `ApproveProfile` an `Applied` Profile ⇒ it is **diverted to `WaitingList`**, not activated
    - `ActivateProfile` on a Profile placed directly in `Approved`, Club at parity ⇒ becomes `Active`, no capacity rejection (the seat is never counted twice)
    - Grep-style assertion: no `Parties\Actions\{ActivateProfile,ReactivateProfile}` file references the capacity reader
  - Typecheck passes; tests pass

- [ ] 3.2 PG17-only concurrency proof: two same-Club approvals serialise on the `parties_clubs` row (D3)
  - **This is the only proof that D3 works.** `AC-K-J-13` drives a *sequential* 51st approve and passes green against the racy implementation
  - Follow the house driver-gated idiom, which **asserts both halves and never skips** — `tests/Feature/Platform/ActorRoleConstraintTest.php:110` + its docblock `:17-23`. **There is no `markTestSkipped`/`->skip()` anywhere in `tests/`; do not introduce the first one**
  - `pgsql` lane: one free seat, two `Applied` Profiles in that Club, two genuinely concurrent connections/transactions each calling `ApproveProfile` ⇒ **exactly one** lands `Active`, the other lands `WaitingList`; occupancy never exceeds capacity
  - `sqlite` lane: assert the **positive** half — `lockForUpdate` is a no-op, so the concurrency claim is not provable here; assert instead that the *sequential* gate holds (second approve diverts) and document the asymmetry in the docblock
  - Run **both** lanes before closing
  - Typecheck passes; tests pass on SQLite **and** PG17

## 4. Architecture assertions — the boundary this change must not cross

- [ ] 4.1 Assert zero capacity storage, zero Module-A coupling, zero auto-promotion (`AC-K-XM-18`, `AC-K-XM-20`, D5, D10)
  - Schema inspection (the literal `AC-K-XM-20` verification method): **no** Module K table carries a capacity / seat / quota / max-members column — assert explicitly on `parties_clubs`; no `parties_*` capacity table exists
  - No Module K source file imports a `Modules\Allocation\*` symbol (`ModuleBoundariesTest` covers `Contracts`/`Events` only — this asserts *nothing at all* is imported)
  - `Parties\Contracts` exposes the capacity **read** port and **no** seat-occupancy reader contract (the count stays internal until a consumer exists)
  - **No auto-promotion exists (D5):** no listener, scheduler, job or model observer in `app/Modules/Parties/` transitions a Profile out of `waiting_list`. Assert by source scan
  - `app/Modules/Parties/Actions/` gained **no** new file — `SupplyLifecycleChainTest`'s non-`Create*` set is unchanged
  - Behavioural proof of no-backfill: at parity with a `WaitingList` Profile, drive each attrition transition (`LapseProfile`, `CancelProfile`, `DeactivateProfile`) and assert the waitlisted Profile is **still** `waiting_list`, the freed seat stays unoccupied, and no `ProfileActivated` fires
  - Typecheck passes; tests pass

## 5. Operator surface — stop the console lying about the outcome

- [ ] 5.1 Make `SurfacesDomainActions` able to derive a success notification from the action's outcome (D11)
  - `lifecycleAction()` passes a **fixed** success title into `surfaceLifecycleOutcome()` (`:81-84`). Add an outcome-aware path (a resolver receiving the Action's return value) **without** changing the ~20 existing call sites' behaviour
  - The console must still re-check **no** gate itself (design L4) and import nothing from a module's `Exceptions` namespace — it reads the returned model's state and catches `RuntimeException` by base type, as today
  - Tests: an existing form-less verb still surfaces its fixed title (regression across the Catalog + Producer + Customer console suites); an outcome-aware verb surfaces the title selected by the returned state; a `RuntimeException` still surfaces the danger toast with the domain's localized message
  - Typecheck passes; tests pass

- [ ] 5.2 `ViewProfile`: `approve`/`decline` visible from `{applied, waiting_list}`; the approve toast tells the truth (D11)
  - `approve` and `decline` visibility predicates widen to `stateIs('applied') || stateIs('waiting_list')` — the exact complement of the widened domain guards. **Waitlist conversion is unreachable through the console today**; this is what makes `AC-K-J-13` demonstrable in Paolo's walkthrough
  - `approve` uses the 5.1 outcome-aware path: reaching `Active` ⇒ the *approved* copy; landing in `WaitingList` ⇒ distinct *waitlisted* copy. Never report a capacity-diverted approval as an approval
  - New notification copy in `lang/en/operator_console.php` **and** `lang/it/operator_console.php` (the *"Operator console copy is localized in EN and IT"* requirement)
  - **Flips:** `ProfileApprovalConsoleTest.php:154` — `'waiting_list → hidden'` becomes **visible**; the test's name and its *"only from Applied … Both gate identically"* comment both go stale
  - Tests: approve visible + drives conversion from `waiting_list`; approve at parity from `applied` ⇒ Profile lands `waiting_list` and the **waitlisted** toast is surfaced (assert the notification title, not just the state — the old bug was invisible precisely because no test asserted the title); approve on a still-at-parity `waiting_list` ⇒ danger toast carrying the capacity reason; decline visible + terminal from `waiting_list`; both verbs still hidden in all 7 other states
  - Typecheck passes; tests pass

## 6. Demo — make the seeded waiting list mean something

- [ ] 6.1 Make the near-capacity Club real in the demo (tracker RM-08's *"post RM-05"* item; D2)
  - `DemoSeeder` already seeds the DRC club with `hiroshi-drc → Active`, `carlos-drc → Suspended` and `eleanor-drc → WaitingList`. Under the `Active` + `Suspended` seat set that Club sits at **exactly 2 occupied seats**, so `PARTIES_HERO_PACKAGE_CAPACITY=2` makes the pre-seeded `WaitingList` Profile coherent **for the first time**, and makes a third approve divert — live, no fixture surgery
  - Document the env var in `.env.example` and in `docs/development.md` (the demo turns the gate on; production leaves it unset ⇒ uncapped ⇒ dark-launch, per `design.md` Migration Plan). The seeder writes no config
  - **Do not** change the seeded rows, and do not add a capacity column
  - Tests: a feature test that binds capacity `2` and asserts the seeded DRC Club is at exact parity, that `ApproveProfile` on a fresh `Applied` Profile there diverts to `waiting_list` + records `WaitingListJoined`, and that `ReactivateProfile` on `carlos-drc` **succeeds** at parity; existing `DemoSeederTest` stays green with capacity unset
  - Typecheck passes; tests pass

## 7. Docs, residual-claim sweep, and the close gate

- [ ] 7.1 Invert every shipped claim that this change makes false
  - `CONTEXT.md:287` — **invert**: it asserts as a hard rule that *"No name outside the eight above is recorded — no `ProfileLapsed`, `ProfileCancelled`, `AccountSuspended`, `AccountClosed`, **`WaitingListJoined`** or `CustomerSegmentChanged`."* Add `WaitingListJoined` to the recorded set with its payload row
  - `CONTEXT.md:131`, `:166`, `:170`, `:174` — all assert the capacity invariant / `Applied → WaitingList` path is a deferred Module-A seam and that approval and activation *"ship uncapped"*. Rewrite. `:267` and `:298` — the deferred-seam bullets naming this very change; rewrite to record what shipped and what stayed carved out
  - `MembershipSuspensionChainTest.php:260-265` — the **assertion stays green** (that chain never reaches parity under the uncapped default), but its comment calls `WaitingListJoined` a *"deferred-seam name … pinned absent"*. That prose is now false. Fix the comment; keep the pin
  - `SupplyLifecycleChainTest.php:424` — the comment naming *"the deferred seams `WaitingList`/segment/Hero-cap (no Action class)"*. The **no-Action-class** half is still true and load-bearing (D10); the *deferred-seam* half is not. Fix the comment
  - Sweep for further residuals: `grep -rn "UNCAPPED\|uncapped\|deferred Module-A seam\|WaitingListJoined" app/ tests/ CONTEXT.md` and reconcile every hit against what shipped
  - Tests: existing suites stay green; no test asserts a claim the change falsified
  - Typecheck passes; tests pass

- [ ] 7.2 Close gate — full verification on both engines, and the honesty note
  - Full suite **SQLite** and **PG17** (`php -d memory_limit=-1 vendor/bin/pest`, then the PG17 prefix). The PG17 lane is the **only** place task 3.2's concurrency proof runs
  - `php -d memory_limit=-1 vendor/bin/phpstan analyse` ⇒ 0 · `vendor/bin/pint --test` clean · `openspec validate --all --strict` green
  - ⚠️ **Record the subset in `docs/validation/Remediation_Tracker.md` §3/§4:** RM-05 closes against a **documented subset**. `AC-K-J-14` / `AC-K-J-15` / `AC-K-J-15a` / `AC-K-XM-19` are **NOT met**, each blocked on Module A's capacity-adjust surface, the unmodelled period rollover, or Module 0/S. **If the tracker does not say this, a later reader will believe capacity is fully compliant — the single biggest honesty risk of this change**
  - Also record the two incidental **RM-03 residuals** this change corrected in `openspec/specs/operator-console/spec.md` (an approved Profile described as *"becomes `Approved`"*; a Profile `activate` console verb that never existed), and the two canon escalations that stay open (who evaluates the capacity-decrease seat floor; K PRD §1:77 vs §13 on the enforcer)
  - `log.md` appended via `scripts/memlog.sh`; `hot.md` overwritten from current state
  - Typecheck passes; full suite passes on both engines
