## Context

Second foundational module of Phase 2, built spine-first like the archived `catalog-product-spine`. The F1 platform substrate is complete and green on both CI engines and is reused unchanged:

- `App\Platform\Events\DomainEventRecorder::record(name, module, actorRole, actorId, entityType, entityId, payload, correlationId?, causationId?)` — **must run inside an open `DB::transaction`** (throws `NotInTransactionException` otherwise); records the event + one `pending` delivery row per consumer, inline-delivered post-commit.
- `App\Modules\Module` enum — `Module::Parties->value === 'parties'`.
- `App\Platform\Events\{ActorRole, ActorContext}` — `ActorContext::role()` / `actorId()` resolve the acting principal (System until customer/producer guards wire in).
- `App\Platform\Money\{Money, MoneyCast, Currency}` — `Money::of(int $minorUnits, Currency)`; `MoneyCast` backs a `{key}` attribute with `{key}_minor` (int) + `{key}_currency` (string) columns.
- `App\Platform\I18n\{TranslatableText, TranslatableTextCast, SupportedLocale}` — one `json` column per translatable attribute, English fallback.

The boundary law is enforced by `tests/Architecture/ModuleBoundariesTest.php` (code under `App\Modules\Parties` may import another module only via its `Contracts\*`/`Events\*` surface) and `tests/Architecture/ModulePersistenceConventionsTest.php` (every non-`Authenticatable` module model declares a `$table` with the module prefix — for Parties, `parties_*`; the auth-principal exemption does **not** apply, Parties entities are not login principals). **This slice has no cross-module reference at all** — every reference (Account→Customer, Club→Producer, Agreement→Producer/Club, Profile→Customer/Club, Customer→originating Club) is **within Module K**, so ordinary Eloquent relations and DB foreign keys are allowed among them.

The one representation choice DEC-073 delegates to the dev team — how to model the party-type marker — is recorded in `decisions/2026-06-15-party-type-marker-on-subtype.md`.

## Goals / Non-Goals

**Goals:**
- The seven core entities as `parties_*` tables + Eloquent models, with their structural creation invariants enforced (global email uniqueness; immutable party-type marker; Club↔Producer required + immutable; Producer/Supplier no-auto-cross-create; multi-profile one-per-(Customer,Club); the Originating-Club field seam).
- The party-type marker as an immutable enum on each subtype (Customer/Supplier); the unified Party Registry deferred (ADR).
- State columns stored (full domains; born in birth state); the five `*Created` events recorded transactionally, PII-free; Supplier/Account event-silent.
- The per-Club fee as `Money` (integer minor units + ISO 4217). Producer story as `TranslatableText`.
- Green on SQLite **and** verified on local PostgreSQL 17 before close.

**Non-Goals (deferred — see proposal slice-boundary table):**
- Any lifecycle **transition**, Profile approval/activation, the membership approve/decline write, the `OriginatingClubLocked` lock, the Hero Package Capacity Invariant, the Customer-segment view, and all `*Activated`/lifecycle events → `parties-membership-lifecycle`.
- The unified Hold registry + gate → `parties-holds`; KYC/sanctions → `parties-compliance`; Club Credit → `club-credit`; GDPR/marketing → `parties-gdpr-retention`; Filament console → `parties-operator-console`.
- The `parties_parties` registry table, the `third_party_owner` subtype, marker overlap → `parties-party-registry` (only if a later need surfaces).

## Decisions

**D1 — Party-type marker on the subtype; unified registry deferred** (ADR `2026-06-15-party-type-marker-on-subtype`). Customer and Supplier are distinct `parties_*` tables, each with an immutable `party_type` column (a `PartyType` backed enum: `customer`, `supplier`, `third_party_owner`) fixed at creation. BR-K-Identity-5 ("a Customer cannot become a Supplier") holds **by construction** — distinct strongly-typed entities. No shared `parties_parties` table, no `third_party_owner` entity, no marker overlap in this change: none are exercised at launch (Producer↔Supplier overlap is a Module-D `SupplierProducerLink`, DEC-067/§4.5; `third_party_owner` is a Module B inventory-ownership concept). The `PartyType` enum nonetheless defines all three markers now (the BR-K-Identity-5 domain) so a future registry slice needs no enum migration. Producer is **not** a Party (§4.4) — no marker.

**D2 — State as backed enums, transitions deferred** (mirrors catalog D3). One backed string enum per stateful entity, each defining its **full** spec state domain even though only the birth state is reachable:
- `CustomerStatus`: `pending|active|suspended|closed` (born `pending`)
- `AccountStatus`: `active|suspended|closed` (born `active`)
- `AccountType`: `personal` (sole launch case, like `ProductType::Wine`)
- `ProducerStatus`: `draft|active|retired` (born `draft`)
- `ClubStatus`: `active|sunset|closed` (born `active`)
- `ClubRegistrationFlowType`: `open_registration|application_with_approval|invitation_only|link_onboarding`
- `ProducerAgreementStatus`: `draft|active|superseded|terminated` (born `draft`)
- `ProfileState`: `applied|waiting_list|approved|rejected|active|suspended|lapsed|cancelled|inactive` (born `applied`)

Each state column is `string` + the enum cast (both engines) + a **driver-guarded PostgreSQL `CHECK`** whose accepted set derives from `Enum::cases()` (the verbatim `domain_events.actor_role` idiom; skipped on SQLite, where the cast + NOT-NULL default carry the value-set floor). No transition method, no `*Activated` emission. Supplier carries **no** state column.

**D3 — Keys & references.** Primary keys are bigint `$table->id()` (consistent with the substrate; party ids are not customer-facing). **All references are within Module K**, so within-module FKs use `->constrained('parties_*')` with **short explicit index names** (PostgreSQL's 63-char identifier limit bites on `parties_producer_agreements_*` — name the FK indexes explicitly, the catalog trap). Within-module Eloquent relations (`Account belongsTo Customer`, `Profile belongsTo Customer/Club`, `Club belongsTo Producer`, `ProducerAgreement belongsTo Producer` + optional `Club`) are permitted — the boundary law forbids only *cross-module* relations, and there are none here. `Customer.originating_club_id` is a nullable within-module FK to `parties_clubs`.

**D4 — Entity / table map.**
- `parties_producers` (standalone; `name`, `region`, `appellation?`, `country`, translatable `description`, `website?`, `status`, `version`).
- `parties_suppliers` (minimal; `legal_name`, `party_type`, timestamps — **no status column**).
- `parties_clubs` (`display_name`, `producer_id` FK + **immutable**, `status`, `fee` via `MoneyCast` → `fee_minor`/`fee_currency` nullable, `registration_flow_type`, `generates_credit` bool, `invite_only` bool, `version`).
- `parties_producer_agreements` (`producer_id` FK, `club_id` FK nullable, `status`, `term_start?`, `term_end?`, `settlement_cadence?`, `version`).
- `parties_customers` (`email` **unique**, `name`, `phone?`, `date_of_birth?`, `party_type`, `preferred_currency`, `preferred_locale`, `status`, `originating_club_id?` FK→clubs, `version`).
- `parties_accounts` (`customer_id` FK, `account_type`, `name` default "Personal", `status`, `default_currency`, `version`).
- `parties_profiles` (`customer_id` FK, `club_id` FK, `state`, `tier?`, `role?`, `invited_by_customer_id?`, `version`; partial-unique `(customer_id, club_id)` — see D8).

Build order (FK dependency): producers → suppliers → clubs → producer_agreements → customers → accounts → profiles.

**D5 — `CreateCustomer` co-provisions the Account.** A single `CreateCustomer` action, in one `DB::transaction`, runs the email-uniqueness pre-check, inserts the Customer (`pending`, marker `customer`, `originating_club_id` NULL), inserts the 1:1 Account (`active`, `personal`) through the within-module relation, and records **only** `CustomerCreated` (no `AccountCreated`). The PRD provisions the Account "on Customer activation" (§4.7); activation is deferred, so the spine binds provisioning to *creation* to keep the 1:1 invariant testable now (§7.1 step 3 already creates Customer + Account together). Email uniqueness is enforced **both** by a DB `unique` index (the true guard) **and** an in-transaction pre-check that throws a localized `DuplicateCustomerEmail` (a clean operator reason rather than a raw integrity error).

**D6 — Originating Club: field only, no setter.** `originating_club_id` is a nullable FK→`parties_clubs`, created `NULL`. This change exposes **no** operation that sets or mutates it — which is exactly the BR-K-OC-2 "immutable, no admin-override surface" floor, satisfiable in a creation-only slice. The one-shot `OriginatingClubLocked` lock fires on the **first membership approval** (§6.1) — an approval-time write that the proposal defers with membership approve/decline. Capturing the field now (it is "unreconstructable later", §6) preserves the seam.

**D7 — Creation via explicit Actions; PII-free payloads; the event asymmetry.** Each evented entity gets a `Create*` action that, inside one transaction, inserts the row(s) and calls `DomainEventRecorder::record(...)` with the verbatim event name, `Module::Parties->value`, the `ActorContext`-resolved role/id, the `entityType`, the stringified id, and a **PII-free** payload. The `CustomerCreated` payload is the strict case: it carries `customer_id`, `party_type`, `status`, `preferred_currency`, `preferred_locale`, `originating_club_id` (null) — and **deliberately omits** name/email/phone/DOB (the substrate's PII-free discipline; profile data lives in the module table where GDPR erasure operates). `ClubCreated` carries the fee as `Money::toPayload()` (`{minor_units, currency}`). **`CreateSupplier` and the Account leg of `CreateCustomer` record no event** — the PRD names none; an invented `SupplierCreated`/`AccountCreated` would breach spec fidelity. Models stay persistence-only; the Action is the seam the lifecycle change extends.

**D8 — Profile uniqueness: partial-unique on non-terminal states + app pre-check.** The "one Profile per Customer per Club" rule (BR-K-Identity-2) coexists with "rejected Profiles are not reused → a new Profile row" (§4.2.1), so the uniqueness is scoped to **non-terminal** states. Enforced by a **partial unique index** `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` written as a raw `DB::statement` — partial unique indexes are supported by **both** PostgreSQL and SQLite with the same syntax (verify on PG17 + SQLite), so the constraint is DB-enforced and portable. `CreateProfile` also runs an in-transaction pre-check throwing a localized `DuplicateProfileForClub` for a clean reason. In the spine no terminal state is reachable (all Profiles born `applied`), so the predicate is inert today but correct the moment the lifecycle change makes terminal states reachable — no later migration to the index. `tier`/`role` are nullable (single-tier/role at launch, DEC-062).

**D9 — Money & i18n discipline.** The per-Club `fee` is the **only** money field in this slice — `MoneyCast` → `fee_minor` (integer) + `fee_currency` (ISO 4217), never a float (invariant 6). Preferred/default *currency* fields (Customer, Account) are ISO-code preference strings, not amounts. The Producer `description` is `TranslatableText` (one json column, English fallback). Any operator-facing rejection reason goes through Laravel localization (invariant 12); there is no "Account Credit" / monetary-balance attribute anywhere (§4.7).

**D10 — No auto-cross-create.** `CreateProducer` creates only a Producer; `CreateSupplier` creates only a Supplier (BR-K-Producer-3). A "helpful" implicit Supplier-for-Producer would be a domain-rule violation; the Supplier↔Producer link is Module D's `SupplierProducerLink`, not modelled here.

## Risks / Trade-offs

- **SQLite-green is necessary, never sufficient** → every DB-touching task verified on a real PostgreSQL 17 before close (`knowledge/testing/rules.md`). Traps in play: enum `CHECK` constraints (driver-guard `ALTER TABLE … CHECK` with `DB::getDriverName()==='pgsql'`, values from `Enum::cases()`, mirror `domain_events`); the **partial unique index** on `parties_profiles` (D8 — assert the duplicate-rejection on both engines); `MoneyCast` two-column round-trip on `parties_clubs.fee`; `TranslatableText` json on `parties_producers.description` (assert through the cast / by key, never byte-compare jsonb); `timestamptz` `+00` if any raw timestamp is asserted; named test doubles only for persisted values.
- **PostgreSQL 63-char identifier limit** → `parties_producer_agreements` + auto-generated FK/index names overflow. Mitigation: name every FK and composite index explicitly and short (D3); the catalog migrations are the worked example.
- **PII leaking into an event payload** → `CustomerCreated` is the trap (the Customer holds email/name). Mitigation: D7 fixes the payload to ids + non-PII business fields; a test asserts the payload has **no** `email`/`name`/`phone`/`date_of_birth` key. This is a stronger guard than catalog needed (catalog's producer was already just an id).
- **Scope creep into lifecycle** → the entities *want* an `approve()`/`activate()` the moment they exist; the OC field *wants* a setter. Mitigation: D2/D6 state the non-goals as spec requirements ("no transition path exists", "no mutation surface for `originating_club_id`"); tests assert birth states hold and that no lifecycle/`OriginatingClubLocked` event is recordable.
- **Account-provisioning timing** → the PRD says "on activation", the spine binds it to creation (D5). Trade-off accepted: activation is deferred, the 1:1 invariant must be testable now, and §7.1 already co-creates them at registration; the lifecycle change tightens activation semantics without reshaping the table.
- **Inventing events for symmetry** → tempting to add `SupplierCreated`/`AccountCreated` so "every entity is evented". Rejected (D7): the PRD event catalog is authoritative; symmetry is not a spec source.

## Migration Plan

Additive only — new `parties_*` tables, no data, no change to existing schema. Build in dependency order: enums → Producer + Supplier → Club + ProducerAgreement → Customer (+ Account) → Profile → docs (`CONTEXT.md`, contract note) + cross-engine close. Rollback is dropping the `parties_*` tables (the ADR/docs revert with the branch). No production data exists; the spine carries no immutability triggers.

## Open Questions

- **Profile partial-unique portability** — D8 assumes identical partial-unique-index syntax on PG17 and SQLite; the implementer verifies on both at the Profile task and records the PG run. If a portability gap surfaces, fall back to an app-layer guard + a plain composite index and flag the DB backstop for the lifecycle change.
- **`THIRD_PARTY_OWNER` home** — defined in the `PartyType` enum now but with no entity; revisit when Module B inventory-ownership lands whether it becomes a Party-registry row (the deferred `parties-party-registry` slice) or stays an inventory-side concept.
