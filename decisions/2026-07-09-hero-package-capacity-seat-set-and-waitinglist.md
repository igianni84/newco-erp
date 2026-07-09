---
type: decision
status: active
date: 2026-07-09
supersedes:
superseded-by:
---

## Decision: Adopt canon **MVP-DEC-011 / MVP-DEC-017 / MVP-DEC-020** locally — the Hero-Package seat-occupying set is **`Active` + `Suspended`**, enforced **at RM-03's atomic approve = charge = activation instant**; `WaitingList` is a **Profile FSM state** with **no auto-promotion, ever**; and Module K **stores no capacity number at all** — it reads Module A's `qty` through a K-owned read-port whose launch adapter is config-backed

> ⚠️ **TRIPLE number collision — read first.** This ADR is about **`MVP-DEC-011`** (`MVP_Decisions_Register_v0.1.md:136`, *Hero Package capacity × renewal: grandfathering + attrition*), **`MVP-DEC-017`** (`:142`, *seat-occupying set = `Active`+`Suspended`*) and **`MVP-DEC-020`** (`:145`, *capacity ownership stays with Module A*).
>
> They are **unrelated** to the greenfield decisions of the same number, which our frozen `spec/04-decisions/decisions.md` carries and which Module K's own §16 cites:
> - greenfield **`DEC-011`** (`:81`) = *Both V1 and V2 passive consignment in scope; no active consignment*
> - greenfield **`DEC-017`** (`:121`) = *No B2B; consumer-only NewCo*
> - greenfield **`DEC-020`** (`:136`) = *Crurated as Discovery supplier — commercial-only*
>
> Canon K PRD §16 line 866 and §17.2 item 9 both write "`DEC-011 + DEC-017`" meaning the **greenfield** pair (AgencyAgreement / B2B dormancy). The Remediation Tracker's RM-05 row writes "`DEC-011/017`" **bare** — read naively against the PRD it resolves to *active consignment + no B2B*, which is nonsense for capacity. **Always use the full `MVP-DEC-NNN` token.** Worse than the single collisions RM-03 (`MVP-DEC-016`), RM-06 (`MVP-DEC-019`) and RM-23 (`MVP-DEC-009/010/022`) each flagged: here three of the four decision numbers collide at once.
>
> The bridged greenfield **`DEC-069`** (`:545`, *Hero Package Allocation `qty` is mutable mid-year*) does **not** collide — it is present in our frozen spec and is cited by frozen §13.4:628. That asymmetry (bridged greenfield DEC present, MVP-DEC absent) is the same escalation-asymmetry tell RM-23's ADR recorded.

### What we adopt

**1. The seat-occupying set is `Active` + `Suspended`.** A `Suspended` Profile **keeps** its Hero-Package seat. Seats are **freed only by `Lapsed` / `Cancelled` / `Inactive`**, and are **never held by `Applied` / `WaitingList` / `Rejected`** (canon §13.1:625). This **corrects our frozen spec**, whose §13.1 says *"only `Active` Profiles do"* consume capacity (frozen §13.1:616) — the exact phrasing MVP-DEC-017 was written to fix.

**2. `Suspended → Active` is never capacity-re-checked and never blocked**, even at `seat-occupying = qty` (canon §13.1:625, §10.1:532, `AC-K-FSM-2a`). Freeing a seat on suspension would let a returning member exceed the cap, or let a *temporary* Hold evict a member. `ReactivateProfile` therefore stays untouched, and earns an explicit regression test.

**3. Enforcement point = the atomic `approve = charge = activation` instant that RM-03 created.** Canon §13.1:627: *"Enforcement is at the approval moment — which, with the corrected membership flow (§4.2.1 / MVP-DEC-016), is the atomic approve = charge = activation instant."* Because there is no "approved-but-unpaid" gap, *reserve-at-approval* and *count-only-seat-occupying* are the **same instant**. **A charge that fails at approval consumes no seat** — the Profile stays `Applied` (canon §4.2.1:187), which is precisely the contract RM-03 already shipped.

**4. The cap gates exactly the transitions that *newly consume* a seat** (canon §13.1:627): membership approval, waitlist conversion, and re-activation from `Lapsed` / `Cancelled`. It gates nothing else.

**5. `WaitingList` is a Profile FSM state, not an entity.** Canon §4.2.1:186 draws `Applied → WaitingList`, with exits to `Approved` (capacity opens) or `Rejected` (Producer declines). The case **already exists** in our enum — `ProfileState.php:29`, `case WaitingList = 'waiting_list'` — inert: no Action enters or leaves it. Nothing is designed here; a dormant state is activated.

**6. `WaitingList` has two entry points, and `WaitingListJoined` fires at both.**
- **At application** — canon §7.1 step 6 (`:399`): *"each application creates a Profile in `Applied` state (**or `WaitingList` if the target Club is at capacity** — §13)"*. `CreateProfile` reads capacity and chooses the birth state.
- **At approval** — `AC-K-J-13` (`:92`): the 51st approve against `qty = 50` is *"rejected at the atomic approve = charge = activation moment — the Profile lands in `WaitingList`, `WaitingListJoined` fires, and **no charge is taken**"*.

**These two gates are not equivalent, and only the second is the invariant.** Because neither `Applied` nor `WaitingList` holds a seat (§13.1:625), the application-time gate **cannot cause oversell** — it is a routing/UX decision about birth state. The approve-time gate is the sole enforcement point. Consequence: `CreateProfile` needs **no** Club-row lock (a Profile born `applied` on a Club that reaches parity a microsecond later is harmless — the approve gate intercepts it).

**7. `ApproveProfile` changes contract: at parity it does not throw, it transitions.** A capacity-exceeded approve is **not** an `IllegalProfileTransition` — canon has the Profile *land* in `WaitingList`. `ApproveProfile`'s from-state set widens to `{applied, waiting_list}` (a waitlist conversion is the same atomic approve-and-charge — canon §4.2.1:186, §7.5:429); `DeclineProfile`'s widens to `{applied, waiting_list}` (canon §4.2.1:186).

**8. No auto-promotion. Ever.** Canon §13.5:655: *"Priority order is producer-discretionary at launch. The Producer reviews the waitlist and approves whichever applicants they choose — **there is no automatic FIFO conversion at launch**."* **MVP-DEC-022 sub-ruling (1)** is emphatic: the tech team proposed exactly `auto-convert waiting_list → approved FIFO`, and Paolo ruled the task text *"loose"* — *"PRD wins (handoff read-order rule)"*. FIFO / priority-by-date / producer ranking are deferred (§17.2 item 5).

> **On the attrition path — not a silence after all.** §13.5 is titled *"Waitlist conversion on **capacity increase**"* and its prose covers only the increase path, so the PRD alone leaves an attrition-freed seat (`Cancelled` / `Lapsed` / `Inactive`) with no stated promotion trigger. **The GitHub recon (2026-07-09) closed this**: canon issue **#1** (→ MVP-DEC-011), paoloalfieri — *"**Shrink by attrition + no-backfill.** To reach a smaller club, set a lower target and don't admit/convert new members until natural attrition (declined renewals, lapse, cancellation) brings Active down to it."* So a freed seat is **never** auto-filled; the only path off the waitlist is the Producer's manual approve, at any time capacity allows. Our behaviour is unchanged — the justification is now a ruling, not an inference.

**9. Module K stores no capacity number — anywhere.** **MVP-DEC-020** (`:145`) **declines** the tech team's proposal to move capacity ownership to Module K: *"club capacity **cannot diverge** from the Hero Package allocation `qty` — capacity *is* the allocation `qty`… a K-owned capacity number would be a **drift-prone mirror with no independent meaning**."* Module A owns the **number**; Module K owns and enforces the **invariant** (§13.2:633).

Canon leaves the *read mechanism* to DEC-073 — `AC-K-XM-18` (`:335`): *"a live read of Module A, or a derived, reconciling read-model fed by A's `AllocationCapacity*` signal — is an implementation choice."* But `AC-K-XM-20` (`:342`) is blunt: Module K holds *"NO Allocation, **capacity storage**, sub-pool, sourcing-model attribute"*, tested by *"inspect Module K entity schemas… assert absence."*

**Launch mechanism (chosen): a K-owned read-port with a config-backed adapter.**

```
app/Modules/Parties/Contracts/HeroPackageCapacityReader.php   ← interface; null ⇒ uncapped
app/Modules/Parties/Reads/ConfigHeroPackageCapacityReader.php ← launch adapter
PartiesServiceProvider::register()                            ← bind()
```

No column on `parties_clubs`, no capacity table, no read-model. `AC-K-XM-18` **and** `AC-K-XM-20` both pass **literally**. This is the exact shape of the RM-02 seam — `Contracts/CustomerTransactionTotalsReader` + `Reads/NullCustomerTransactionTotalsReader`, bound at `PartiesServiceProvider.php:35` — and of `PartyComplianceStatusReader` → `DatabaseComplianceStatusReader` (`:28`). Tests bind a fake reader; when Module A lands, **only the adapter is replaced** (with a live read, or with the `AllocationCapacity*`-fed read-model canon blesses — that choice is deferred to Module A's own gate, not pre-empted here).

> **Honest note on convention.** Module K uses **no `config()` calls today** — its configurable constants are class constants (`CreateCustomer::MINIMUM_REGISTRATION_AGE = 18`; `EvaluateEnhancedKycThreshold::SINGLE_TRANSACTION_THRESHOLD_MINOR`). A class constant cannot express a **per-Club** value, so the adapter introduces **`config/parties.php`** — new for Parties, though consistent with the repo (`config/catalog.php`, `config/operator.php` already exist). This is a new file, not an existing convention; recording it so no reviewer mistakes it for precedent.

**10. Concurrency: the seat count is serialised on the Club row.** Today `ApproveProfile` takes `lockForUpdate` on the **Profile** row, and its docblock asserts *"the from-state assert below is the correctness guarantee."* That is true of the FSM and **false of the seat count**: two concurrent approvals of *different* Profiles in the same Club lock *different* rows, both read `49/50`, and both pass — `qty = 50` yields 51 occupied seats. That is a direct breach of the no-oversell invariant RM-05 exists to close.

Every seat-consuming transaction therefore takes `lockForUpdate` on the **`parties_clubs` row** *before* counting occupancy. Approvals of the same Club serialise; different Clubs stay parallel. Precedent: `ApproveProfile` already row-locks the **Customer** for the Originating-Club one-shot lock. **SQLite makes `lockForUpdate` a no-op**, so the serialisation proof lives in the **PG17 lane only** — the concurrency test is PG-only, exactly like the PG-only CHECK lane the P3 sweep established.

**11. `RenewProfile` is cap-gated. The "renewal" canon grandfathers is a different, unmodelled thing.** ⚠️ **The load-bearing naming trap of this ADR.**

- Our **`RenewProfile`** is `lapsed → active` — the 30-day grace re-activation (`ProfileRenewed`, DEC-034). Canon §13.1:629: *"a re-activation within the 30-day grace **re-consumes a seat** (subject to the cap at re-activation time)"*, and §13.1:627 lists *"re-activation from `Lapsed` / `Cancelled`"* among the gated transitions. **It is cap-gated.**
- The **grandfathered renewal** of `MVP-DEC-011` / `AC-K-J-15a` is the **period rollover of an `Active` Profile into a new club year** — *"a renewal that continues an in-good-standing `Active` membership into a new period does NOT newly consume a seat (it was never freed), so it is **not cap-gated**"* (§13.1:627). **That transition does not exist in our model at all**: `parties_profiles` has no `valid_to`, no period column, and no rollover Action. `lapsed_at` is the only period-ish field.

Same word, opposite rule. Anyone who reads *"renewal → grandfathered"* and applies it to `RenewProfile` breaks the invariant. Recorded here in full because the tracker's RM-05 row says only *"renewal grandfathering + attrition (J-15a)"*.

### Scope — what the `parties-hero-package` change delivers, and what it does not

**In scope (K-side, buildable today):**

| Delivered | Canon criterion |
|---|---|
| Internal seat-occupancy count (`Active` + `Suspended`) per Club | §13.1:625 |
| Capacity gate on `ApproveProfile`, under a Club-row lock | `AC-K-J-13` (leg 1) |
| `Applied → WaitingList` at approval; `WaitingListJoined` recorded; no charge, no OC lock | `AC-K-J-13`, `AC-K-FSM-2`, `AC-K-EVT-11` |
| Birth-in-`WaitingList` at application on a Club at parity | canon §7.1 step 6 (`:399`) |
| Waitlist conversion `WaitingList → Approved → Active`, cap-gated, producer-discretionary, no FIFO | §13.5:655, `MVP-DEC-022`(1) |
| `WaitingList → Rejected` via `DeclineProfile` | §4.2.1:186 |
| `RenewProfile` (`lapsed → active`) cap-gated | §13.1:627, :629 |
| Regression: `ReactivateProfile` (`suspended → active`) **never** cap-gated, admitted at parity | `AC-K-J-13` (leg 2), `AC-K-FSM-2a` |
| Architecture assertion: no capacity attribute in any Module K schema | `AC-K-XM-18`, `AC-K-XM-20` |

**Out of scope, each with its blocker named:**

- **`AC-K-J-14`** (mid-year increase) — the increase happens on **Module A's** capacity-adjust surface (A §5.3.4) and emits `AllocationCapacityIncreased`. `app/Modules/Allocation/` is a two-file stub with an empty `register()`/`boot()`. *(The waitlist-**conversion** half of J-14 does ship — only the increase signal is absent.)*
- **`AC-K-J-15`** (decrease-floor rejection) — the decrease is a **Module A** operation. See the open question below: A's own anti-orphan rule floors on **vouchers issued**, not on seats.
- **`AC-K-J-15a`** (renewal boundary) — legs (a) and (c) need A's decrease surface; legs (b) and (d) need the **period rollover we do not model**. Adding `valid_to` / a rollover Action would mean inventing columns canon never names in Module K — refused (invariant: never invent names).
- **`AC-K-XM-19`** (SKU shape irrelevance) — Hero-Package Offers are Module S / Module 0 surfaces. Vacuous today.
- **The seat-occupancy count stays *internal* to Module K.** Publishing `Parties\Contracts\ClubSeatOccupancyReader` for Module A (J-15 floor) and Module S (`AC-S-XM-6` gate 3) is deferred until one of them exists to consume it — a contract with zero consumers is dead code, the same call RM-03 made on the charge seam.

## Context: why this came up

- **RM-05**, the **last open P0/P1** in the Remediation Tracker, and the third of the three headline canon divergences Paolo aimed his walkthrough at. We ship **UNCAPPED**: membership no-oversell — a core invariant — is unenforced. `ApproveProfile`'s own docblock (`:58-63`) says so: *"Approval therefore ships UNCAPPED (the seat gate is MVP-DEC-017 / RM-05, after Module A)."*
- **Giovanni's 2026-07-08 decision:** build the **K-side seam** now; stop waiting for Module A. The seam consumes the real Module-A signal when it exists.
- **RM-03 is the direct precedent and the direct dependency.** It *created* the single atomic instant (`approve = charge = activate`, transient `Approved`); RM-05 puts the seat gate **on** it. Its charge-fail contract (*stays `Applied`, no seat, no OC lock*) is already shipped — RM-05 makes the "no seat" clause finally mean something.
- **Grounded on LIVE canon, per the `lessons.md` 2026-07-03 rule.** Read-only `git -C ../documentation fetch cmless main`; canon `main` is at **`360df0b`**, **+29 commits** past our pinned `spec/` (`spec.lock` → `4f48277`, 2026-06-16). The clone's worktree was left untouched (verified `git status --short` clean before and after); canon files were materialised to a scratchpad via `git show`.
- **That grounding paid off twice.**
  1. The tracker's baseline says *"canon @ DEC-023"*. Canon is at **MVP-DEC-030**. The tracker's RM-05 row names only `DEC-011/017` — but **`MVP-DEC-020` (2026-06-22) is the decision that actually settles open question (a)**, and it **declines** the very option the tracker floated ("a K-owned column"): *"a K-owned capacity number would be a drift-prone mirror with no independent meaning."*
  2. Two of the tracker's four open questions — (b) *state vs entity* and (c) *auto-promote vs operator pull* — are **closed verbatim by canon text**, not open at all. Deciding them from our frozen spec would have been re-deriving what Paolo already ruled.
- **Escalation asymmetry.** MVP-DEC-008..030 are absent from our frozen `spec/` (pinned pre-MVP-DEC-008). The bridged greenfield `DEC-069` **is** present. Same tell as RM-23.

## Alternatives considered

### (a) Where the capacity `qty` lives while Module A is a stub

- **(A1) K-owned read-port + config-backed adapter. ✅ CHOSEN.** No storage of any kind in Module K → `AC-K-XM-18` *and* the blunter `AC-K-XM-20` both pass literally. Zero migration. Mirrors the RM-02 read-port seam Giovanni already approved. Swap-point is one `bind()` line.
- **(A2) K-owned derived read-model table (`parties_hero_package_capacities`). ❌ REJECTED.** `AC-K-XM-18` does bless a *"derived, reconciling read-model fed by A's `AllocationCapacity*` signal"* — but **there is no signal to reconcile from**: Module A emits nothing. The table would be authoritative-by-default, i.e. exactly the *"drift-prone mirror with no independent meaning"* MVP-DEC-020 declines. It also reads as "capacity storage" to `AC-K-XM-20`'s schema inspection. Canon-shaped in the abstract, canon-violating today.
- **(A3) Build a minimal Module A slice that owns `qty`. ❌ REJECTED (scope).** Canon-exact — A owns the number, K consumes `AllocationCapacity{Increased,Decreased}` (Architecture §8.1:355) — and it would unblock `AC-K-J-14` and `AC-K-J-15` too. But it abandons the "K-side seam" Giovanni scoped, commits Module A's schema and event contract **ahead of Module A's own `/spec-to-change` and ADR gate**, and risks rework. Revisit when Module A opens.
- **(A4) Read-port + Null adapter returning uncapped. ❌ REJECTED.** Canon-pure and risk-free, but the gate never fires: the invariant stays unenforced, `WaitingList` stays unreachable, and Paolo's demo is identical to today's. It ships the *shape* of RM-05 while leaving RM-05's stated defect (*"ships UNCAPPED"*) fully intact.

### (b) Entry points into `WaitingList`

- **(B1) Both gates — application **and** approval. ✅ CHOSEN.** Full fidelity to canon §7.1 step 6 *and* `AC-K-J-13`. Cheap and safe once you see that the application-time gate cannot oversell (neither `Applied` nor `WaitingList` holds a seat), so it needs no lock and carries no invariant weight.
- **(B2) Approval-time only. ❌ REJECTED.** Passes `AC-K-J-13` (the only AUTO criterion) and leaves §7.1 step 6 unimplemented. Rejected because the omission is invisible in tests and would silently ship a Club at parity accepting applications as `Applied` — a state canon says should be `WaitingList` from birth.

### (c) Serialising the seat count

- **(C1) `lockForUpdate` on the `parties_clubs` row inside the seat-consuming transaction. ✅ CHOSEN.** Serialises same-Club approvals, keeps different Clubs parallel, adds no schema, reuses the Customer-row-lock pattern `ApproveProfile` already carries.
- **(C2) A DB constraint over materialised seat rows. ❌ REJECTED.** Strongest guarantee, but it makes seats **entities** — canon derives occupancy from Profile state — and it introduces precisely the "capacity storage" `AC-K-XM-20` forbids.
- **(C3) `SERIALIZABLE` isolation on the approve transaction. ❌ REJECTED.** Correct without explicit locks, but demands retry-on-`40001` that no other Action in the repo implements, is opaque to readers, and is inexpressible on SQLite — splitting the two test lanes. Disproportionate to a row lock.

### (d) Scope

- **(D1) Narrow slice + carve-outs, each blocker named. ✅ CHOSEN.**
- **(D2) Also model the period rollover** (`valid_to`, a rollover Action) to satisfy `AC-K-J-15a`(b)(d). **❌ REJECTED** — canon's Module K PRD names no such column; we would be inventing schema, violating *never invent names*. And J-15a(a)(c) would **still** be blocked on Module A.

## Reasoning

- **Spec fidelity is the point of the RM series, and canon here is unusually explicit.** Where the text rules, we follow it; where it is silent (attrition-triggered promotion), we implement the silence and escalate rather than invent a mechanism.
- **`AC-K-XM-20` is the binding constraint, not `AC-K-XM-18`.** XM-18 is permissive about mechanism; XM-20 is a flat prohibition tested by schema inspection. Only option (A1) satisfies the stricter of the two. Reading XM-18 alone — as the tracker's "K-owned column" suggestion did — picks an option XM-20 rejects.
- **A vacuous gate is worse than no gate**, because it *looks* enforced. (A4) would let a reader of the code, an acceptance test, or Paolo's walkthrough conclude the invariant holds when nothing constrains it. The config adapter costs one file and makes `AC-K-J-13` genuinely demonstrable.
- **The concurrency hole is the actual bug**, and nothing in the tracker, the PRD, or the acceptance criteria mentions it. `AC-K-J-13` drives a sequential 51st approval and would pass green against a racy implementation. Naming it in the ADR is the only way it survives into `tasks.md`.
- **The `RenewProfile` trap is the second silent failure.** `lessons.md` (2026-07-03, 2026-07-07) already records that a plan's one-line description of a symbol routinely under-states what that symbol does. "Renewal is grandfathered" is true of a transition we don't have, and false of the one we do.
- **The read-port keeps Module A's design free.** We commit to *nothing* about A's schema or event payloads — only that Module K asks a question and someone answers it. Whether A answers by live read or by feeding a K read-model is decided at A's gate, on A's evidence.

## Trade-offs accepted

- **Capacity is not operator-adjustable at launch.** A config value cannot be changed from the console, so `AC-K-J-14`'s mid-year increase is not drivable through the UI. Accepted: the increase surface is Module A's (§5.3.4) and does not belong in K anyway. The DemoSeeder seeds a near-capacity Club through config.
- **`config/parties.php` is a new file for a module that had none.** Small, and consistent with `config/catalog.php` / `config/operator.php` — but it *is* new, and a per-Club map keyed by id is mildly awkward for seeded fixtures. Accepted over a schema change that would trip `AC-K-XM-20`.
- **Three acceptance criteria (`J-14`, `J-15`, `J-15a`) remain unmet after this change**, plus `XM-19`. RM-05 will close as ✅ against a **documented subset**. The tracker must say so, or a later reader will believe capacity is fully compliant. This is the single biggest honesty risk of the change.
- **The concurrency guarantee is provable on PG17 only.** The SQLite lane cannot demonstrate it (`lockForUpdate` is a no-op). Accepted — the same split the P3 sweep's PG-only CHECK lane already lives with.
- **`ApproveProfile` gains a dependency and a widened from-state set**, and `CreateProfile` gains the reader. Both are shipped Actions with existing callers; per `lessons.md` (2026-07-06), the blast radius is **every** call site — including console `callAction('approve')` sites — not the subset a tasks list enumerates. To be grepped exhaustively before implementing, and proven on the **full** suite, never a `--filter` run.
- **`CONTEXT.md:287` must be inverted.** It currently states, as a hard rule, that *"No name outside the eight above is recorded — no `ProfileLapsed`, `ProfileCancelled`, `AccountSuspended`, `AccountClosed`, **`WaitingListJoined`** or `CustomerSegmentChanged`."* This change introduces `WaitingListJoined`. `CONTEXT.md:267` / `:298` already name the future change **`parties-hero-package`** — the slug is canon-of-record here, not an invention.
- **We resolve one canon ambiguity ourselves** (`WaitingListJoined` on birth — below). Recorded as a decision, flagged as a question, not smuggled in.

## Open questions for Paolo (canon gaps found while grounding — none block the change)

> **Amended 2026-07-09 (same day) after a read-only recon of the canon GitHub repo** (`c-mless/documentation`, 18 issues: 17 closed, each resolving into one `MVP-DEC`; the decision register's *"tech-team GitHub question"* citations resolve to issue **#1** → MVP-DEC-011, **#9** → MVP-DEC-016/017, **#11** → MVP-DEC-020). Two of the five below were **already answered there** and are struck; one is confirmed **never asked**. No PRs, no branches ahead of `main`, Discussions disabled — `360df0b` is genuinely the tip, and Issues is the only question channel.

1. **⚠️ THE ONE THAT IS GENUINELY UNASKED — file it. Does `WaitingListJoined` fire when a Profile is *born* `waiting_list`?** `AC-K-EVT-11` (`:259`) and §15.6:822 both say it fires *"when a Profile **transitions** to `WaitingList`"*, and a birth (canon §7.1 step 6) is not a transition. **Recon result: zero occurrences of `WaitingListJoined` across all 18 issues, bodies and comments — the tech team never raised it.** Our decision stands: **we fire it in both cases**, because the event's declared consumer is HubSpot's waitlist-confirmation and an applicant born on the waitlist needs the same confirmation as one diverted at approval. We extend an existing canon event's trigger; we invent no name.

2. **STILL OPEN — and canon reproduces the conflation at the source. Who evaluates the capacity-decrease seat floor?** `AC-K-J-15` (`:94`) says *"**Module K** rejects"* — and canon's `AC-K-J-15` **does** floor on the seat-occupying set (verified verbatim against `cmless/main`; our **frozen** `AC-K-J-15` still says `Active`-only, which is the pre-MVP-DEC-017 text). But the decrease *executes* on Module A's surface (A §5.3.4), whose canonical rule **`BR-A-Mutability-1`** (A PRD `:378`) floors `qty` on *"the count of **vouchers already issued**"* — a different set. In **issue #1** paoloalfieri writes the two together: *"the invariant is defined in Module K §13, but **qty + the anti-orphan floor are owned by Module A (§5.3.4)**"*, while the count he describes is K's seat semantics. **No issue disentangles which module evaluates the seat floor.** Not blocking (the decrease surface is Module A's, carved out of this change), but it must be settled before Module A's capacity-adjust lands.

3. **~~Is Module K a consumer of `AllocationCapacityDecreased`?~~ ANSWERED — yes, both events.** Not a contradiction: **MVP-DEC-020's own body records that the omission was *"fixed in a Paolo-approved follow-up (added Module K to the consumer row)"*** — canon commit `c0a40e1`, which is why Architecture §8.1:355 now reads *"K (`AllocationCapacity*` → Hero Package capacity invariant + waitlist eligibility, §13)"*. Module A PRD `:505` (*"Consumer: Module S"*) is an **un-swept residual of that same follow-up**, not a competing claim. Nothing to ask; noted so a future reader of A's PRD is not misled.

4. **OPEN, minor — a wording residual, not a design gap.** The three-way split is settled and consistent across issues #1/#9/#11: **Module A owns the number · Module K owns and defines the invariant (§13) and consumes `AllocationCapacity*` · Module S enforces it at Hero gate 3** at the same approve = charge instant, with the read path (S→A direct vs S→K capacity view) a DEC-073 choice (`AC-S-XM-6`, Module S Acceptance `:493`). What remains is that **K PRD §1:77 still attributes *"Hero Package capacity-invariant enforcement"* to Module S** while §13 says three times that K enforces — a sentence never swept, and never surfaced as a question. Our position, recorded not assumed: **Module K owns and evaluates the invariant at `ApproveProfile` today; Module S's gate 3, when built, consumes K's capacity view rather than duplicating the arithmetic** — which is exactly the pattern paoloalfieri offers in issue #11 (*"have Module S validate Hero gate 3 against that Module K capacity view instead of reading Module A directly"*).

5. **~~Attrition never promotes — intended?~~ ANSWERED, in issue #1.** paoloalfieri: *"**Shrink by attrition + no-backfill.** To reach a smaller club, set a lower target and don't admit/convert new members until natural attrition (declined renewals, lapse, cancellation) brings Active down to it."* Combined with §13.5's manual-only rule and **MVP-DEC-022**(1)'s rejection of auto-FIFO: **a freed seat is never auto-filled, and the only exit from the waitlist is the Producer's manual approve.** §13.5's increase-only title is a scoping artefact, not a gap. Our implementation was already correct.

## References

**Canon (authoritative — `c-mless/documentation` @ `360df0b`, fetched read-only 2026-07-09; our `spec/` is frozen @ `4f48277`, `spec.lock`):**
- `04-decisions/MVP_Decisions_Register_v0.1.md:136` — **MVP-DEC-011** (grandfathering; attrition drawdown; never evict; no auto-cancel; FIFO deferred).
- `…:142` — **MVP-DEC-017** (seat set = `Active`+`Suspended`; `Suspended → Active` never re-checked; enforcement at the atomic instant; charge-fail consumes no seat; decrease-floor counts the seat set).
- `…:145` — **MVP-DEC-020** (A owns the number; K owns the invariant; a K-owned number is a *drift-prone mirror*; mechanism freed to DEC-073).
- `…:141` — **MVP-DEC-016** (the atomic instant RM-03 built). `…:147` — **MVP-DEC-022** sub-ruling (1): waitlist conversion is **manual, producer-discretionary, no FIFO**.
- Module K PRD (canon): §4.2.1:186 (`Applied → WaitingList`), :187 (charge-fail, no seat), :191 (`Suspended → Active` never re-checked); §7.1 step 6 (`:399`, birth-in-WaitingList); §7.5:429 (mandate persists through waitlisting); §10.1:532; §13.1:625/:627/:629; §13.2:633/:635; §13.4:646/:647; §13.5:655/:656/:658; §15.6:822 (`WaitingListJoined`); §16:858 (no independent capacity value); §17.2 item 5 (FIFO deferred).
- Module K Acceptance (canon): **AC-K-J-13** `:92` · **AC-K-J-14** `:93` · **AC-K-J-15** `:94` · **AC-K-J-15a** `:95` · **AC-K-FSM-2** `:113` · **AC-K-FSM-2a** `:114` · **AC-K-EVT-11** `:259` · **AC-K-XM-18** `:335` · **AC-K-XM-19** `:336` · **AC-K-XM-20** `:342`.
- Module A PRD (canon): §5.3.4 (`qty` adjust; `AllocationCapacityIncreased`/`Decreased`); `:233-234`, `:504-505` (event consumers); **BR-A-Mutability-1** `:378` (anti-orphan floors on **vouchers issued**).
- Module S Acceptance (canon): **AC-S-XM-6** `:493` (S evaluates at Hero gate 3, same instant; read path is DEC-073).
- Architecture (canon) §8.1 `:355` (K consumes `AllocationCapacity{Increased,Decreased}`).

**Frozen spec (what this ADR corrects):**
- `spec/02-prd/Module_K_PRD_v0.3-MVP.md` §13.1:616 — *"only `Active` Profiles do"* consume capacity (superseded by MVP-DEC-017); §13.4:633 — decrease-floor counts `Active` (now the seat set); §16:832 — *"it does not duplicate the value"* (softened by MVP-DEC-020).
- `spec/03-acceptance/Module_K_Acceptance_v0.3-MVP.md` — **`AC-K-J-15a` is ABSENT** from the frozen text (the table jumps J-15 → J-16); frozen `AC-K-J-13`/`J-15`/`XM-18` all predate the seat-set correction.
- `spec/04-decisions/decisions.md:81` / `:121` / `:136` — the three **colliding greenfield** DEC numbers; `:545` — greenfield **DEC-069** (bridged, present, non-colliding).

**Local code (what the change touches):**
- `app/Modules/Parties/Enums/ProfileState.php:29` — `case WaitingList = 'waiting_list'` (inert today).
- `app/Modules/Parties/Actions/ApproveProfile.php` — `:58-63` the "ships UNCAPPED / RM-05" seam docblock; `:84-130` the transaction (Profile lock, Customer lock, OC one-shot, delegate to `ActivateProfile`).
- `app/Modules/Parties/Actions/{CreateProfile,ActivateProfile,DeclineProfile,RenewProfile,ReactivateProfile,LapseProfile,CancelProfile,DeactivateProfile}.php`.
- `app/Modules/Parties/Exceptions/IllegalProfileTransition.php` — no capacity constructor exists yet.
- `app/Modules/Parties/Contracts/CustomerTransactionTotalsReader.php` + `Reads/NullCustomerTransactionTotalsReader.php` + `Providers/PartiesServiceProvider.php:28,:35` — **the read-port precedent this ADR reuses**.
- `app/Modules/Allocation/` — two files; `AllocationServiceProvider` has an empty `register()`/`boot()`. No events, no models, no `qty`.
- `tests/Architecture/ModuleBoundariesTest.php` — a module may import another module's `\Contracts` and `\Events` **only**.
- `openspec/specs/party-registry/spec.md` — **no** capacity / seat / waitlist requirement exists; the change introduces it.
- `CONTEXT.md:287` (asserts `WaitingListJoined` is never recorded — to be inverted); `:267` / `:298` (name the change **`parties-hero-package`**).

**Related ADRs:**
- [[2026-07-03-adopt-mvp-dec-016-membership-charge-on-approval]] — **RM-03**, the direct precedent: it *created* the atomic approve = charge = activation instant and shipped the charge-fail contract; this ADR puts the seat gate on that instant. It explicitly deferred *"the Hero-Package capacity seat gate… MVP-DEC-017"* here.
- [[2026-06-19-hold-status-coupling]] — the `Suspended` / `Reactivate` coupling whose seat semantics MVP-DEC-017 Q1 pins (a Hold must never evict a member).
- [[2026-06-11-modular-monolith-architecture]] — events + contracts only; why the capacity read is a port, never a cross-module query.
- [[2026-06-17-spec-synced-from-documentation-repo]] — why `spec/` is frozen, and the read-only `git fetch cmless/main` grounding path this ADR used.

**Same canon-adoption class as** [[2026-07-01-adopt-dec-008-hold-types-8]] / [[2026-07-02-adopt-dec-015-anonymisation-hold-block-set]] / [[2026-07-02-adopt-dec-018-clubcredit-accrued]] / [[2026-07-02-adopt-dec-019-review-freshness-resubmit]] / [[2026-07-02-adopt-dec-023-product-type-immutable]] / [[2026-07-07-adopt-mvp-dec-009-agreement-club-active-scope]] — but a **full ADR** (tracker §3 "ADR? **yes**"), like RM-03: a behavioural design change carrying a real seam decision, a concurrency contract and a scope carve-out, not a naming or guard tweak.

**Authored for RM-05 → next step `/spec-to-change` (`parties-hero-package`), in its own window. No code, no OpenSpec change, and no `APPROVED` marker were produced with this ADR.**
