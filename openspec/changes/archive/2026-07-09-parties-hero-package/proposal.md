## Why

Membership no-oversell — the eligibility analogue of the inventory floor, and the invariant Module K §13 calls *"the membership no-oversell guard"* — **is unenforced today**. `ApproveProfile`'s own docblock says so verbatim (`ApproveProfile.php:58-63`): *"Approval therefore ships UNCAPPED (the seat gate is MVP-DEC-017 / RM-05, after Module A)."* A Club with a 50-seat Hero Package will admit a 51st member, silently. The `WaitingList` state exists in the enum (`ProfileState.php:29`) but is **inert**: no Action enters it, none leaves it, and no `WaitingListJoined` event class exists.

RM-05 is the **last open P0/P1** in the Remediation Tracker. Giovanni's 2026-07-08 decision unblocked it: build the **K-side seam now** rather than wait for Module A. ADR [`2026-07-09-hero-package-capacity-seat-set-and-waitinglist`](../../../decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md) — authored via `grill-with-docs` against **LIVE canon** (`c-mless/documentation @ 360df0b`) — fixes the scope, the seam, the concurrency contract and the carve-outs. **This change implements that ADR and re-derives none of it.**

## What Changes

**The invariant and its gate**

- The **seat-occupying set** becomes `Active` + `Suspended` (canon MVP-DEC-017). Seats are freed only by `Lapsed` / `Cancelled` / `Inactive`, and are **never** held by `Applied` / `WaitingList` / `Rejected`. This **corrects our frozen spec**, whose §13.1:616 says *"only `Active` Profiles do"* consume capacity.
- The cap is enforced at the **atomic `approve = charge = activate` instant** RM-03 created, and at `RenewProfile` — exactly the transitions that *newly consume* a seat.
- **BREAKING (concurrency):** every seat-consuming transaction takes `lockForUpdate` on the **`parties_clubs` row** before counting. `ApproveProfile` today locks the *Profile* row, so two concurrent approvals of different Profiles in one Club never serialise — `qty = 50` yields 51 seats. Provable on the **PG17 lane only** (SQLite no-ops the lock).
- **`Suspended → Active` (`ReactivateProfile`) is never capacity-re-checked and never blocked**, even at parity — a temporary Hold must never evict a member. Ships with an explicit regression test.

**`WaitingList`, activated**

- **BREAKING (contract):** `ApproveProfile`'s from-state widens to `{applied, waiting_list}` and, at parity on an `applied` Profile, it **transitions rather than throws**: the Profile lands in `WaitingList`, `WaitingListJoined` fires, no charge, no Originating-Club lock. Its return value is therefore no longer always an `Active` Profile.
- `DeclineProfile`'s from-state widens to `{applied, waiting_list}`.
- **Two entry points** into `WaitingList`, both shipping: **birth at application** on an at-capacity Club (canon §7.1 step 6) and **divert at approval** (`AC-K-J-13`). `WaitingListJoined` fires at both.
- **Waitlist conversion** is `ApproveProfile` from `waiting_list` — **manual, producer-discretionary. There is no auto-promotion, ever, on any trigger** (canon §13.5:655; `MVP-DEC-022`(1) explicitly rejected the dev team's auto-FIFO; canon issue #1 rules attrition-freed seats *"no-backfill"*).
- **BREAKING (event catalog):** a new `WaitingListJoined` event class. This **inverts** `CONTEXT.md:287`, which asserts as a hard rule that it is *never recorded*, and the absent-pin at `MembershipSuspensionChainTest.php:265`.

**`RenewProfile` — the naming trap**

- Our `RenewProfile` is `lapsed → active`, a **cap-gated re-activation** (canon §13.1:627, :629). The *grandfathered* renewal of `MVP-DEC-011` / `AC-K-J-15a` is the **period rollover of an `Active` Profile**, which **we do not model at all** (no `valid_to`, no rollover Action). Same word, opposite rule. At parity `RenewProfile` **throws** — canon draws no `Lapsed → WaitingList` edge, and inventing one would burn the 30-day grace clock.

**The capacity read — zero storage in Module K**

- `qty` stays on **Module A**. Module K gains a K-owned read-port `Contracts/HeroPackageCapacityReader` + launch adapter `Reads/ConfigHeroPackageCapacityReader`, bound in `PartiesServiceProvider::register()` — the RM-02 `CustomerTransactionTotalsReader` seam. **No column, no table, no read-model.** New file `config/parties.php`.
- `MVP-DEC-020` **declines** the "K-owned capacity column" the Remediation Tracker itself once proposed (*"a drift-prone mirror with no independent meaning"*). `AC-K-XM-20` — *"NO … capacity storage"*, tested by schema inspection — is the **binding** constraint, stricter than the permissive `AC-K-XM-18`.

**Operator surface**

- `approve` becomes visible from `{applied, waiting_list}` (waitlist conversion is **unreachable** through the console today), and its notification is **derived from the resulting state** — today `surfaceLifecycleOutcome` prints a fixed success title, so a capacity-diverted approve would print a green *"Profile approved"*.

## Capabilities

### New Capabilities

None. The Hero-Package capacity invariant is a Module K (`party-registry`) behaviour and its operator affordance is an `operator-console` behaviour; both capabilities already exist.

### Modified Capabilities

- `party-registry`: the seat invariant, the `WaitingList` placement/conversion/decline paths, `WaitingListJoined`, the cap gate on `ApproveProfile` and `RenewProfile`, the explicit *non*-gate on `ReactivateProfile` and `ActivateProfile`, and the read-port boundary. **3 ADDED + 7 MODIFIED requirements.**
- `operator-console`: the approve/decline visibility set, the outcome-derived notification, and the removal of the *"activation ships uncapped"* clause. **3 MODIFIED requirements.**

## Impact

**Code (all Module K + its console surface)**

- New: `Contracts/HeroPackageCapacityReader.php`, `Reads/ConfigHeroPackageCapacityReader.php`, `Support/ClubSeatOccupancy.php`, `Events/WaitingListJoined.php`, `config/parties.php`.
- Modified: `Actions/{CreateProfile,ApproveProfile,DeclineProfile,RenewProfile}.php`, `Actions/{ActivateProfile,ReactivateProfile}.php` (docblocks only — the `UNCAPPED` seam prose is now false), `Exceptions/IllegalProfileTransition.php` (new capacity factory), `Providers/PartiesServiceProvider.php`, `Enums/ProfileState.php` (docblock), `lang/{en,it}/parties.php`, `lang/{en,it}/operator_console.php`, `ProfileResource/Pages/ViewProfile.php`, `Console/Concerns/SurfacesDomainActions.php`, `DemoSeeder.php`.
- **No new Action class**, deliberately: `SupplyLifecycleChainTest` asserts `toEqualCanonicalizing` over every non-`Create*` file in `Actions/` and would fail on one. Waitlist conversion is `ApproveProfile` from `waiting_list`; the seat count lives in `Support/`, not `Actions/`.
- **No migration.** `AC-K-XM-20` forbids the column.

**Blast radius** — `ApproveProfile` / `CreateProfile` / `DeclineProfile` / `RenewProfile` are **shipped Actions**. Every call site resolves through the container (`app(X::class)->handle(...)`), and **no** call site anywhere passes constructor arguments, so adding a dependency breaks none of them. The real radius is **behavioural**: 16 test files execute these Actions, 7 of them through Filament `callAction('approve')` — invisible to `grep ApproveProfile`. Per `lessons.md` (2026-07-06), **only the full suite is proof; a `--filter` run is not.**

**Three suites pin the pre-change truth and must be inverted, not appeased:**

| File | Pins today | Why it must change |
|---|---|---|
| `ProfileApprovalConsoleTest.php:154` | `'waiting_list → hidden'` | approve becomes **visible** from `waiting_list` |
| `ProfileApprovalConsoleTest.php:186` | `'waiting_list → hidden + rejected'` | approve **and** decline become **legal** from `waiting_list` |
| `MembershipSuspensionChainTest.php:265` | `WaitingListJoined` pinned absent | the event now exists (count stays 0 in that chain — the *comment* is what goes stale) |

**Docs** — `CONTEXT.md` lines `:131`, `:166`, `:170`, `:174`, `:267`, `:287`, `:298` all assert the uncapped/deferred state. `:267` and `:298` already name this change `parties-hero-package`; the slug is canon-of-record, not an invention.

**Deliberate gaps (⚠️ RM-05 closes against a documented SUBSET — say so, or a later reader will believe capacity is fully compliant):**

| Not met | Blocker |
|---|---|
| `AC-K-J-14` (mid-year increase) | needs Module A's `AllocationCapacityIncreased`; `app/Modules/Allocation/` is a two-file stub with an empty `register()`. *(The waitlist-**conversion** half does ship — only the increase signal is absent.)* |
| `AC-K-J-15` (decrease floor) | the decrease executes on Module A's §5.3.4 surface. Canon itself conflates the floor: `BR-A-Mutability-1` floors on **vouchers issued**, `AC-K-J-15` on **seats**. Open question for Paolo. |
| `AC-K-J-15a` (renewal boundary) | legs (a)/(c) need A's decrease surface; legs (b)/(d) need the **period rollover we do not model**. Adding `valid_to` would invent schema canon never names in Module K. |
| `AC-K-XM-19` (SKU-shape irrelevance) | Hero-Package Offers are Module 0 / Module S surfaces. Vacuous today. |
| `Parties\Contracts\ClubSeatOccupancyReader` | the seat count stays **internal** to K until Module A (J-15 floor) or Module S (`AC-S-XM-6` gate 3) exists to consume it — a contract with zero consumers is dead code. Same call RM-03 made on the charge seam. |

**Two incidental RM-03 residuals in `openspec/specs/operator-console/spec.md`, corrected in passing** (both inside requirements this change rewrites anyway; neither is introduced by RM-05): `:783` says an approved Profile *"becomes `Approved`"* (RM-03 made `Approved` transient — it becomes `Active`); `:802`/`:806-810` describe a Profile **`activate` console verb that has never existed** — `ViewProfile::getHeaderActions()` has no such verb and `OperatorPanel` never references `ActivateProfile`.

**Out of scope by construction:** RM-26 / RM-27 (Producer offboarding dual-control, `MVP-DEC-024`) are **not** folded in.
