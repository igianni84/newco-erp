## Why

The `parties-hero-package` §2.7 semantic-verify (4 agents over all 13 delta requirements, 2026-07-09) returned **0 CRITICAL** and the change archived. Four of its five WARNINGs are residuals that the archive has now baked into the living documentation or left unpinned. None is a defect in shipped behaviour; each is a place where the **spec, the tests and the code disagree about what is guaranteed**.

1. **The truth spec now misstates the approval sequence.** *Profile Membership Approval* says `ApproveProfile` SHALL *"acquire a row-level lock on the Club row; count the Club's seat occupancy; read the capacity; and then, **only if a seat is free**: assert the from-state; …"*. Read literally, an `Active` Profile approved into a full Club falls into the at-capacity branch. The shipped code asserts the from-state **first** (`ApproveProfile.php:131`, before `:139`), which is what the ADR's D8 table requires — its capacity column reads `—` for every non-approvable state — and which also stops a doomed call from serialising an unrelated healthy one on the Club row. The implementer recorded the discrepancy in `progress.md` § Codebase Patterns and shipped the correct order; the prose was never corrected. **A future implementer reading only the requirement would reintroduce the defect.**

2. **`WaitingListJoined`'s root-ness is unpinned.** Its own scenario names the facet verbatim — *"it is a root event (no `causation_id`; `correlation_id` defaults to its own `event_id`)"* — and no test asserts it. Every sibling does (`ProfileActivationTest.php:74-75`). The behaviour is correct today by inheritance from `DomainEventRecorder`; nothing would turn red if a future edit threaded a parent into either `record()` call, silently breaking the correlation chain the HubSpot waitlist-confirmation consumer rides.

3. **Two `operator-console` scenarios are proven only at the domain layer.** *"A Club at capacity creates the Profile on the waiting list"* and *"A renew into a Club at capacity is rejected and surfaced"* are written as console requirements — including the `actor_role: newco_ops` envelope and the danger-notification surfacing — but no test drives either through the Filament page. The domain outcome is covered; the console contract around it is not.

## What Changes

- **`Profile Membership Approval` is corrected** to state the sequence the code implements and the ADR mandates: **from-state guard → Club-row lock → occupancy count → capacity read → gate**. The correction adds the two guarantees the old prose silently dropped: a call from a non-approvable state is rejected **before** any Club row is locked, and it is **never** diverted onto the waitlist merely because its Club happens to be full.
- A new scenario pins that ordering **negatively** — by the trace the skipped step did not leave — because a positive assertion ("an out-of-state approve reports `cannotApprove`") stays green under both orderings whenever the capacity gate would also have rejected.
- Three test pins are added: `WaitingListJoined` root-ness at **both** entry points, console create-at-capacity, console renew-at-capacity.

**No production code changes.** The code is already correct; this change makes the documentation and the suite say so.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `party-registry`: the *Profile Membership Approval* requirement's operation sequence, plus one added scenario pinning the guard-before-lock order. **1 MODIFIED requirement.**

## Impact

**Specs** — `openspec/specs/party-registry/spec.md`, requirement *Profile Membership Approval* (prose paragraph 2, and the from-state-guard paragraph).

**Tests only, no `app/` changes**
- `tests/Feature/Modules/Parties/ProfileApprovalCapacityGateTest.php` — the negative ordering pin (an out-of-state approve emits **no** `parties_clubs` statement). The file already owns the `DB::listen` idiom and the capacity-0 fixture.
- `tests/Feature/Modules/Parties/ProfileBirthStateRoutingTest.php` + `ProfileApprovalCapacityGateTest.php` — `WaitingListJoined` root-ness at birth and at divert.
- `tests/Feature/Modules/OperatorPanel/Parties/ProfileCreateConsoleTest.php` — create-at-capacity through the page.
- `tests/Feature/Modules/OperatorPanel/Parties/ProfileLifecycleConsoleTest.php` — renew-at-capacity through the page.

**Out of scope** — the four RM-05 carve-outs (`AC-K-J-14` / `J-15` / `J-15a` / `XM-19`) stay carved out; this change closes none of them and RM-05 remains **a documented subset**. The `Profile ↔ Customer` lock-order inversion (tracker §7 **F12**) is **pre-existing** and needs a decision, not a test — not folded in.

**Method note** — an ordering claim and a non-gate are both invisible to a diff. Per `progress.md` § Codebase Patterns, each new pin must be **mutation-tested**: reorder the guard (or thread a causation id) and watch the new assertion, and only it, turn red. A pin that survives its own mutant is decoration.
