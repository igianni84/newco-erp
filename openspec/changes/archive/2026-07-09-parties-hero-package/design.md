## Context

RM-05 — the last open P0/P1. The authority for this change is **ADR [`decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md`](../../../decisions/2026-07-09-hero-package-capacity-seat-set-and-waitinglist.md)**, written with `grill-with-docs` against **LIVE canon** (`c-mless/documentation @ 360df0b`, +29 commits past our frozen `spec/` @ `4f48277`) and amended after a read-only recon of the canon's 18 GitHub issues. **Read the ADR before the first task. It is the plan; do not re-derive it and do not re-ground against canon.**

Three facts about the source material an implementer must hold:

1. **Our frozen `spec/` is wrong here, and canon corrects it.** Frozen §13.1:616 — *"only `Active` Profiles do"* consume capacity — is the exact phrasing `MVP-DEC-017` was written to fix. Frozen `AC-K-J-15a` **does not exist** (the table jumps J-15 → J-16). Where this change's requirements cite behaviour, provenance is the **ADR**, with the frozen section named only for context.
2. **⚠️ TRIPLE number collision.** This change is about **`MVP-DEC-011` / `MVP-DEC-017` / `MVP-DEC-020`**. In our frozen `spec/04-decisions/decisions.md` those bare numbers mean *no active consignment* (`:81`), *No B2B* (`:121`) and *Crurated as Discovery supplier* (`:136`). Canon's own K PRD §16:866 cites the **greenfield** pair. **Always write the full `MVP-DEC-NNN` token.**
3. **RM-03 is the direct dependency.** It created the single atomic instant (`approve = charge = activate`, `Approved` transient). RM-05 puts the seat gate *on* that instant. RM-03's charge-fail contract — *stays `Applied`, no seat, no OC lock* — is already shipped; this change is what finally makes its "no seat" clause mean something.

## Goals / Non-Goals

**Goals**

- Enforce membership no-oversell at the transitions that newly consume a seat, **race-free**.
- Activate the dormant `WaitingList` state along both canon entry points, with `WaitingListJoined`.
- Keep **zero capacity storage** in Module K (`AC-K-XM-20`), while making the gate genuinely demonstrable (not a vacuous shape).
- Make the operator surface tell the truth about what just happened.

**Non-Goals**

- Module A's capacity-adjust surface (`AC-K-J-14`, `AC-K-J-15`) — `app/Modules/Allocation/` is a two-file stub.
- The period rollover / grandfathered renewal (`AC-K-J-15a` legs b, d) — **unmodelled**, and modelling it means inventing columns canon never names in Module K.
- Publishing the seat count cross-module (`Parties\Contracts\ClubSeatOccupancyReader`) — zero consumers exist. Dead code.
- Any auto-promotion mechanism, on any trigger. See D5.
- RM-26 / RM-27 (`MVP-DEC-024`, Producer offboarding dual-control). Not folded in.

## Decisions

### D1 — Capacity is read through a K-owned port; the launch adapter is config-backed

```
app/Modules/Parties/Contracts/HeroPackageCapacityReader.php    interface: forClub(int $clubId): ?int   // null ⇒ uncapped
app/Modules/Parties/Reads/ConfigHeroPackageCapacityReader.php  launch adapter
app/Modules/Parties/Providers/PartiesServiceProvider.php       $this->app->bind(...) in register()
```

Exactly the shape of the RM-02 seam (`Contracts/CustomerTransactionTotalsReader` + `Reads/NullCustomerTransactionTotalsReader`, bound at `PartiesServiceProvider.php:35`) and of `PartyComplianceStatusReader` → `DatabaseComplianceStatusReader` (`:28`). Both existing binds are plain `bind`, not `singleton` — match that.

**Alternatives rejected** (ADR §(a)): a K-owned read-model table — `AC-K-XM-18` blesses a *"derived, reconciling read-model"* but **there is no signal to reconcile from** (Module A emits nothing), so it would be authoritative-by-default, i.e. the *"drift-prone mirror"* `MVP-DEC-020` declines, and it reads as capacity storage to `AC-K-XM-20`'s schema inspection. A minimal Module A slice — commits A's schema and event contract ahead of A's own gate. A `Null` adapter returning uncapped — **a vacuous gate is worse than no gate**: it ships the *shape* of RM-05 while leaving its stated defect fully intact.

When Module A lands, **only the adapter is replaced** (live read, or the `AllocationCapacity*`-fed read-model canon blesses). That choice belongs to Module A's gate, on Module A's evidence. This change commits to nothing about A's schema or payloads.

### D2 — `config/parties.php`: a global `default` plus an optional per-id override

```php
'hero_package' => [
    'capacity' => [
        'default'    => env('PARTIES_HERO_PACKAGE_CAPACITY'),  // null ⇒ uncapped
        'by_club_id' => [],                                     // optional pinning
    ],
],
```

`parties_clubs` has **no slug column**, and ids are not stable outside `DemoSeeder`. A `default` makes the demo coherent with one env var and no ids: `DemoSeeder` already seeds the DRC club with `hiroshi-drc → Active`, `carlos-drc → Suspended` and `eleanor-drc → WaitingList`. Under the `Active`+`Suspended` seat set that Club sits at **exactly 2 occupied seats**, so `default = 2` makes the pre-seeded `WaitingList` Profile *coherent for the first time* and makes a third approve divert — live, in the demo, with no fixture surgery.

**Honest note on convention** (ADR §9): Module K uses **no `config()` calls today** — its configurable constants are class constants (`CreateCustomer::MINIMUM_REGISTRATION_AGE`). A class constant cannot express a per-Club value. `config/parties.php` is **new for Parties**, though consistent with `config/catalog.php` / `config/operator.php`. Record it; do not mistake it for existing precedent.

Cast defensively: `env()` yields a string. The reader must return `?int`, never `?string`.

### D3 — The seat count is serialised on the `parties_clubs` row. This is the actual bug.

Today `ApproveProfile` takes `lockForUpdate` on the **Profile** row, and its docblock asserts *"the from-state assert below is the correctness guarantee."* True of the FSM, **false of the seat count**: two concurrent approvals of *different* Profiles in the same Club lock *different* rows, both read `49/50`, both pass → `qty = 50` yields 51 occupied seats. A direct breach of the invariant RM-05 exists to close.

Every seat-consuming transaction therefore takes `lockForUpdate` on the **`parties_clubs` row** *before* counting occupancy. Same-Club approvals serialise; different Clubs stay parallel. Precedent: `ApproveProfile` already row-locks the **Customer** for the Originating-Club one-shot lock.

**Nothing in the tracker, the PRD, or the acceptance criteria mentions this.** `AC-K-J-13` drives a *sequential* 51st approve and **passes green against the racy implementation**. A criterion tests a scenario, not an invariant.

**SQLite makes `lockForUpdate` a no-op**, so the serialisation proof lives in the **PG17 lane only** — the same split the P3 sweep's PG-only CHECK lane already lives with. Follow the house idiom, which **asserts both halves and never skips**: `if (DB::getDriverName() === 'pgsql') { … } else { … }` (`tests/Feature/Platform/ActorRoleConstraintTest.php:110`, docblock `:17-23`). **No `markTestSkipped` / `->skip()` exists anywhere in `tests/`** — do not introduce the first one.

Rejected: a DB constraint over materialised seat rows (makes seats *entities*; canon derives occupancy from Profile state; trips `AC-K-XM-20`). `SERIALIZABLE` isolation (demands retry-on-`40001` no other Action implements; inexpressible on SQLite).

### D4 — Where the gate fires, and where it deliberately does not

| Transition | Writer | Gated? | Why |
|---|---|---|---|
| `applied → active` (approve) | `ApproveProfile` | **yes** | newly consumes a seat — the atomic instant, `AC-K-J-13` |
| `waiting_list → active` (conversion) | `ApproveProfile` | **yes** | newly consumes a seat — §13.5 |
| `lapsed → active` (renew) | `RenewProfile` | **yes** | *"re-consumes a seat"* — canon §13.1:629, :627 |
| `suspended → active` (restore) | `ReactivateProfile` | **NEVER** | the seat was never freed. Re-checking would let a temporary Hold **evict** a member — `AC-K-FSM-2a`, canon §13.1:625, §10.1:532 |
| `approved → active` | `ActivateProfile` | **no** | `Approved` is transient and never durably rested-in (RM-03). Its only caller is `ApproveProfile`, which gates *before* delegating. Gating here would count the same seat twice. |
| birth at application | `CreateProfile` | **reads, does not lock** | see D6 |

`ActivateProfile` and `ReactivateProfile` are **docblock-only** changes: both currently carry an `UNCAPPED / DEFERRED MODULE-A SEAM` paragraph (`ActivateProfile.php:49-54`) that becomes **false** the moment this change lands. Replace the prose; do not add a gate. `ReactivateProfile`'s non-gate is a **load-bearing behaviour** and earns an explicit regression test at parity.

Canon also gates *"re-activation from `Cancelled`"*. We have **no `Cancelled → Active` edge** (terminal soft-delete). Nothing to gate; noted so a reader does not think it was missed.

### D5 — No auto-promotion. Ever. On any trigger.

Canon §13.5:655: *"Priority order is producer-discretionary at launch… **there is no automatic FIFO conversion at launch**."* **`MVP-DEC-022` sub-ruling (1)** is emphatic: the tech team proposed exactly `auto-convert waiting_list → approved FIFO`, and Paolo ruled the task text *"loose"* — *"PRD wins."*

§13.5 is titled *"Waitlist conversion on **capacity increase**"* and its prose covers only the increase path, which reads as a silence on the **attrition** path (a seat freed by `Lapsed`/`Cancelled`/`Inactive`). **It is not a silence.** Canon issue **#1** (→ `MVP-DEC-011`), paoloalfieri: *"**Shrink by attrition + no-backfill.** To reach a smaller club, set a lower target and don't admit/convert new members until natural attrition brings Active down to it."*

So: a freed seat is **never** auto-filled. The only exit from the waitlist is the Producer's manual approve, at any time capacity allows. **No listener, no scheduler, no job, no model observer** may promote a Profile. If a task seems to want one, it is wrong.

### D6 — Two entry points into `WaitingList`; only one is the invariant

- **Birth at application** — canon §7.1 step 6 (`:399`): *"each application creates a Profile in `Applied` state (**or `WaitingList` if the target Club is at capacity** — §13)"*. `CreateProfile` reads capacity and chooses the birth state.
- **Divert at approval** — `AC-K-J-13`: the 51st approve against `qty = 50` lands in `WaitingList`, `WaitingListJoined` fires, **no charge is taken**.

**These are not equivalent.** Because neither `Applied` nor `WaitingList` holds a seat, the application-time gate **cannot oversell** — it is a routing/UX decision about birth state. The approve-time gate is the **sole enforcement point**. Consequence: **`CreateProfile` needs no Club-row lock.** A Profile born `applied` on a Club that reaches parity a microsecond later is harmless — the approve gate intercepts it. Do not add a lock there; it would serialise every application in a Club for no invariant gain.

### D7 — `WaitingListJoined` fires at **both** entry points (a recorded resolution, not a silent one)

`AC-K-EVT-11` (`:259`) and canon §15.6:822 both say it fires *"when a Profile **transitions** to `WaitingList`"*, and a birth is not a transition. **The canon GitHub recon confirmed this was never asked** — zero occurrences of `WaitingListJoined` across all 18 issues, bodies and comments.

We fire it in both cases: the event's declared consumer is HubSpot's waitlist-confirmation, and an applicant *born* on the waitlist needs the same confirmation as one diverted at approval. We **extend an existing canon event's trigger; we invent no name.** Flagged for Paolo (ADR open question 1) — it does not block.

Payload, PII-free, mirroring the `ProfileCreated` id-only discipline: `{profile_id, customer_id, club_id, state}`. Root event (no causation), like every other Parties event.

### D8 — At parity, `ApproveProfile` **transitions**; it throws only where no transition exists

A capacity-exceeded approve of an `applied` Profile is **not** an `IllegalProfileTransition` — canon has the Profile *land* in `WaitingList`. So:

| From-state | Capacity | Outcome |
|---|---|---|
| `applied` | free | → `Active` (atomic; `OriginatingClubLocked`? + `ProfileActivated`) |
| `applied` | **at parity** | → `WaitingList`; `WaitingListJoined`; **no charge, no OC lock, no `ProfileActivated`** |
| `waiting_list` | free | → `Active` (conversion; same atomic instant, same OC-lock rule) |
| `waiting_list` | **at parity** | **throws** — no transition exists to make |
| anything else | — | throws `IllegalProfileTransition::cannotApprove` (unchanged) |

The `waiting_list`-at-parity throw and the `RenewProfile`-at-parity throw share **one new factory** on `IllegalProfileTransition` (which has no capacity constructor today). The alternative — a silent idempotent no-op — is indistinguishable from a bug to the operator who clicked the button.

`RenewProfile` at parity **throws rather than diverting to `WaitingList`**: canon draws only `Applied → WaitingList` (§4.2.1:186). There is no `Lapsed → WaitingList` edge; inventing one would violate *never invent names* and would burn the 30-day grace clock (`lapsed_at` is cleared on renewal). The Profile stays `Lapsed`, the grace keeps running, the operator reads why.

### D9 — ⚠️ `RenewProfile` is the load-bearing naming trap of this change

- **Our `RenewProfile`** is `lapsed → active` — the 30-day grace re-activation (`ProfileRenewed`, DEC-034). Canon §13.1:629: *"a re-activation within the 30-day grace **re-consumes a seat** (subject to the cap at re-activation time)."* **It is cap-gated.**
- The **grandfathered renewal** of `MVP-DEC-011` / `AC-K-J-15a` is the **period rollover of an `Active` Profile into a new club year** — *"not cap-gated"* (§13.1:627), because the seat was never freed. **That transition does not exist in our model**: `parties_profiles` has no `valid_to`, no period column, no rollover Action.

**Same word, opposite rule.** Anyone who reads *"renewal is grandfathered"* and applies it to `RenewProfile` breaks the invariant. The Remediation Tracker's RM-05 row says only *"renewal grandfathering + attrition (J-15a)"* — which is exactly how this trap gets stepped on.

### D10 — No new Action class, and the seat count lives in `Support/`

`tests/Feature/Modules/Parties/SupplyLifecycleChainTest.php` asserts `toEqualCanonicalizing` over **every non-`Create*` file** in `Actions/`, and its own comment names *"the deferred seams `WaitingList`/segment/Hero-cap (**no Action class**)"*. Adding one fails that assertion.

None is needed: conversion is `ApproveProfile` from `waiting_list`; decline-from-waitlist is `DeclineProfile`; the renew gate is inside `RenewProfile`. The occupancy helper goes in **`app/Modules/Parties/Support/ClubSeatOccupancy.php`** (the `Support/` namespace exists — `AnonymisedPlaceholders.php`). It is **K-internal**: not a `Contract`, not published, not bound. It takes the Club row lock and counts — and must therefore only ever be called inside a `DB::transaction`.

### D11 — The console must not lie about the outcome

`SurfacesDomainActions::lifecycleAction()` passes a **fixed** success title into `surfaceLifecycleOutcome()` (`:81-84`). A capacity-diverted approve would print a green *"Profile approved"*. And `approve` is `visible(stateIs('applied'))`, so **waitlist conversion is unreachable through the console** — the demo could not show `AC-K-J-13` at all.

Widen visibility to `{applied, waiting_list}` and derive the notification from the **resulting Profile state**. The console still re-checks **no** gate itself (design L4 — it catches `RuntimeException` by base type and imports nothing from a module's `Exceptions` namespace); it only reads the state of the record the Action returned.

## Risks / Trade-offs

- **A green acceptance test can pass against a racy implementation.** → `AC-K-J-13` drives a *sequential* 51st approve. The concurrency proof is a **separate, PG17-only** test that opens two real transactions. Without it, D3 is unverified prose.
- **The concurrency guarantee is provable on PG17 only.** → Accepted (the P3-sweep precedent). The SQLite lane asserts the *positive* half (the sequential gate holds), never a skip.
- **The blast radius is behavioural, not structural.** → No call site passes constructor args, so a dependency add compiles everywhere. **That is the trap**: the suite goes green on compile and red on semantics, and 7 of the 16 executing test files drive the Action through `callAction('approve')`, which `grep ApproveProfile` never sees. **Only the full suite is proof.** `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs), then the PG17 lane.
- **Three suites pin the pre-change truth.** → `ProfileApprovalConsoleTest:154` (`waiting_list → hidden`), `:186` (`waiting_list → hidden + rejected`), `MembershipSuspensionChainTest:265` (`WaitingListJoined` absent-pin). **Invert them; do not delete them, and do not "fix" the code to keep them green.** The `MembershipSuspensionChainTest` count assertion stays valid (that chain never hits parity under an uncapped default) — it is the *comment* calling `WaitingListJoined` a deferred seam that goes stale.
- **RM-05 closes against a documented subset.** → `AC-K-J-14` / `J-15` / `J-15a` / `XM-19` remain unmet. **This is the single biggest honesty risk of the change.** It is stated in `proposal.md`, in the delta requirements, and must land in the Remediation Tracker at close.
- **`config/parties.php` is new for a module that had none**, and a per-id map is awkward for fixtures. → Accepted over a schema change that would trip `AC-K-XM-20`. The `default` key is what the demo actually uses.
- **`{@see FQCN}` on another module's type reds `ModuleBoundariesTest`** (Pint auto-imports the reference). → Reference other modules' types by bare name in docblocks.
- **`CONTEXT.md:287` must be inverted**, not appended to: it states as a hard rule that `WaitingListJoined` is never recorded. `:267` / `:298` already name this change `parties-hero-package` — the slug is canon-of-record.

## Migration Plan

**No migration.** `AC-K-XM-20` forbids the column, and D1 stores nothing. Deployment is a config default (`PARTIES_HERO_PACKAGE_CAPACITY` unset ⇒ `null` ⇒ uncapped ⇒ **exactly today's behaviour**), so the change is dark-launchable: the gate activates only where a capacity is configured. Rollback is unsetting the env var.

`DemoSeeder` is the one place capacity is switched on, making the already-seeded `eleanor-drc → WaitingList` Profile coherent for the first time.

## Open Questions

Two survive from the ADR. **Neither blocks this change**; both are Giovanni's to file against canon, not ours to resolve in code.

1. **Who evaluates the capacity-decrease seat floor?** `AC-K-J-15` says *"**Module K** rejects"* and canon's `AC-K-J-15` floors on the **seat-occupying set**. But the decrease *executes* on Module A's surface, whose `BR-A-Mutability-1` (A PRD `:378`) floors on *"the count of **vouchers already issued**"* — a different set. In issue #1 paoloalfieri writes both together. **No issue disentangles it.** Must be settled before Module A's capacity-adjust lands. Carved out of this change.
2. **K PRD §1:77 still attributes Hero-Package capacity-invariant *enforcement* to Module S**, while §13 says three times that **K** enforces. A wording residual over a settled three-way split (**A owns the number · K owns the invariant · S enforces at Hero gate 3**). Our position, recorded not assumed: **Module K owns and evaluates the invariant at `ApproveProfile` today; Module S's gate 3, when built, consumes K's capacity view rather than duplicating the arithmetic** — exactly what paoloalfieri offers in issue #11.

A third — **does `WaitingListJoined` fire when a Profile is *born* `waiting_list`?** — is **confirmed never asked** (zero hits across all 18 canon issues) and is resolved here by D7. Worth filing as a new canon issue; not a blocker.
