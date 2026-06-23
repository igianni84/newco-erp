## Why

Club Credit is the core value proposition of a producer Club: the membership fee a member pays converts into spendable credit redeemable against that Club's Offers through the year (the BMD chain *fee → Club Credit → redeem*; DEC-007, PRD §11). It is a **greenfield** Module K entity — no `ClubCredit` code exists today — and is the named `club-credit` seam that the shipped Parties slices pointed forward to (`parties-membership-activation` design **L5**: "Club Credit (also `MembershipFeePaid`-coupled) is an independent consumer in `club-credit`"; `parties-membership-suspension`: "the Club Credit frozen-while-suspended guarantee is a deferred `club-credit` seam"). This slice builds the entity and its full within-module lifecycle so that when Module E (Phase 6 / F6) and the Module S storefront land, their listeners and checkout simply invoke the writers this change ships.

## What Changes

- **New `parties_club_credits` entity** — a per-Profile prepayment instrument: `amount` + `remaining` (both `Money`), a `valid_from`/`valid_to` window, and a `state` FSM `active → redeemed | forfeited`. Structural **one-active-Club-Credit-per-Profile** invariant via a partial unique index.
- **Four within-module writer Actions**, each from-state guarded, transaction-locked, and the sole writer of the credit `state`:
  - `IssueClubCredit` — creates an `active` credit when the Profile's Club has `generates_credit = true`; `amount` = `Club.fee` verbatim (full-fee → full-credit; K.18 scaling deferred), `remaining` = `amount`, `valid_to` = 31 Dec of the issuance year.
  - `ApplyClubCredit` — redemption decrement; partial leaves `remaining` and stays `active` (**K.17 carry-forward**), full → `redeemed`; currency-match + Profile-not-suspended (freeze) guards.
  - `ForfeitClubCredit` — `active → forfeited` (terminal, at most one forfeiture per lifetime).
  - `RestoreClubCredit` — `redeemed → active` on order cancellation (downstream effect), one-active-respecting.
- **Audit-only — NO domain event emitted by Module K** (the headline §11.4 decision): `ClubCreditIssued` / `ClubCreditApplied` / `ClubCreditRestored` / `ClubCreditForfeited` **and** `MembershipFeePaid` are **Module E's** events. The writers record state on the entity only; no Module-E event class is fabricated. When Module E lands, its consumers invoke these same writers. Mirrors the audit-only `RecordKycVerified` precedent (activation design **L2**).
- **i18n** exception copy; **CONTEXT.md** glossary + seam refresh.

### Slice boundary — deliberately NOT in this change

- **Module-E `MembershipFeePaid` listener** (the production issuance/renewal trigger, payment-provider-confirmed) + the **`ClubCreditIssued/Applied/Restored/Forfeited` consumers** → Module E (Phase 6). No Module-E contract fabricated (mirrors L5).
- **Redemption mechanics at checkout** — price-resolution, coupon **mutual-exclusion** (DEC-110), auto-apply (DEC-111), the Hold-gated price resolution → **Module S**. This change ships the within-module `ApplyClubCredit` writer + the model's queryable eligibility state (`active` + `remaining` + issuing Club via `profile.club_id` + currency); the checkout orchestration and the Module-S eligibility read contract are the Module-S seam.
- **Forfeiture triggers**: the year-end-lapse **scheduler**, the renewal **forfeit-before-issue** orchestration (Module-E renewal listener), the **Profile-cancellation cascade** (a within-module follow-on), and **Club-closure → Discovery store-credit conversion** (DEC-043, **owned by Module S** — AC-K-XM-23). The `ForfeitClubCredit` writer ships; the triggers are documented seams. The forfeit-before-issue *ordering* is provable now via the one-active invariant (issue-when-active rejects; forfeit-then-issue succeeds).
- **K.18** welcome-window proportional scaling + **K.19** operator manual issuance → deferred with retained seams (PRD §11.1; AC-K-MVP-3 / AC-K-MVP-4 / AC-K-J-17 / AC-K-J-16a).
- The **Filament operator console** over these Actions → a future `operator-console-parties-club-credit` slice.

## Capabilities

### New Capabilities

_None._ This change extends the existing `party-registry` capability — the **Module-K-is-one-capability** pattern the prior Parties slices established (Club Credit is a Module K domain entity, not a cross-cutting surface like `operator-console`).

### Modified Capabilities

- `party-registry`: gains the Club Credit entity + its full within-module lifecycle.
  - **ADDED** — *Club Credit Entity and One-Active-Per-Profile Invariant*; *Club Credit Issuance*; *Club Credit Redemption and Carry-Forward*; *Club Credit Forfeiture and Restoration*; *Club Credit State Recording Is Module-E-Owned*.
  - **MODIFIED** — *Birth States Recorded, Lifecycle Transitions Deferred* (the Club Credit entity + its within-module FSM are now implemented; the cross-module triggers — Module-E events/listener, Module-S redemption/conversion, the year-end scheduler, the Profile-cancellation cascade — remain deferred seams).

## Impact

- **New code**: `app/Modules/Parties/Models/ClubCredit.php`, `Enums/ClubCreditState.php`, `Actions/{Issue,Apply,Forfeit,Restore}ClubCredit.php`, `Exceptions/IllegalClubCreditTransition.php` (+ an issuance-precondition exception), `database/migrations/<ts>_create_parties_club_credits_table.php`, `database/factories/Parties/ClubCreditFactory.php`, a within-module `Profile::activeClubCredit()` relation, `lang/en/parties.php` additions.
- **No** new dependency; **no** open ADR gate is stepped through — the writers are **audit-only (no domain event)**, so the queue/consumer gate is not touched; no object-storage / payment-provider / frontend. Money via the existing `MoneyCast` (its docblock already names "Club Credit balance" as its first real consumer).
- **Tests**: new Pest feature tests per Action + a §11.4 audit-only guard test (Parties records no `ClubCredit*`/`MembershipFeePaid` event; no such class fabricated); full Parties suite green on SQLite + PostgreSQL 17.
- **Docs**: `CONTEXT.md` (Club Credit glossary entry + the residual Module-E/S/scheduler/cascade seams), `knowledge/` as warranted.
