# Progress — parties-core

## Codebase Patterns
(consolidated reusable patterns — read first each iteration; the `catalog-product-spine` archive is the worked precedent for every idiom below)

- **Spine DB-entity creation template** (catalog task 2.1 → repeated here per entity). Each evented entity = `parties_*` migration + `Models\X` + `Database\Factories\Parties\XFactory` + `Events\XCreated` + `Actions\CreateX`.
  - **Migration:** `$table->id()`; entity cols; each state column `string` + `->default(Enum::Birth->value)`; `version` `unsignedInteger()->default(1)`; `timestampsTz()`. After `Schema::create`, a **driver-guarded** `CHECK` per enum column (`if (DB::getDriverName()==='pgsql')`, values from `Enum::cases()`, constraint `parties_X_<col>_check`) — verbatim the `domain_events.actor_role` / `catalog_*` idiom. SQLite skips the CHECK (cast + default carry the floor).
  - **Model:** `$table='parties_X'`; `$guarded=[]` (the action is the sole writer); `casts()` maps every enum/`Money`/`TranslatableText`/int column; full `@property` block; **typed `newFactory(): XFactory` override** (factories live under `Database\Factories\Parties\`, off the `App\Models` convention — the explicit return type fixes Larastan inference).
  - **Factory:** `protected $model = X::class`; bypasses the action (NO dedup, NO event) — pure fixture for standing up prerequisites cheaply.
  - **Event:** `final` class, untyped `const NAME` / `const ENTITY_TYPE`, static `payload(X): array` (PII-free). One class per `*Created` event under `Events/` (the module's public surface).
  - **Action:** `__construct(DomainEventRecorder $recorder, ActorContext $actor)`; pre-checks (uniqueness/required-ref) before/inside the tx; `DB::transaction(fn () => …)` inserts then `$this->recorder->record(name: XCreated::NAME, module: Module::Parties->value, actorRole: $this->actor->role(), actorId: $this->actor->actorId(), entityType: XCreated::ENTITY_TYPE, entityId: (string) $x->id, payload: XCreated::payload($x))`. The recorder's `NotInTransactionException` guard keeps write+emit atomic.
- **Two deliberate event asymmetries** (design D7) — `CreateSupplier` and the Account leg of `CreateCustomer` record **NO** event (the PRD §15 names none). Do not invent `SupplierCreated`/`AccountCreated`. The integration test asserts `entity_type IN ('Supplier','Account')` count is 0.
- **PII-free `CustomerCreated`** (design D7) — the Customer holds email/name/phone/DOB; the payload carries `customer_id`, `party_type`, `status`, `preferred_currency`, `preferred_locale`, `originating_club_id` (null) and **omits** all PII. Test: `expect($event->payload)->not->toHaveKey('email')` (and `name`/`phone`/`date_of_birth`).
- **All references are within Module K** — within-module FKs (`->constrained('parties_*')`, short explicit index names for the 63-char limit) and within-module Eloquent relations are allowed; there is **no** cross-module reference in this slice. `ModuleBoundariesTest` + `ModulePersistenceConventionsTest` (`$table = 'parties_*'`) stay green unamended.
- **Money & i18n** — Club `fee` is the only money field: `MoneyCast` → `fee_minor` + `fee_currency` (never a float); assert via `Money::equals()` after re-fetch and the payload key as `{minor_units, currency}`. Producer `description` is `TranslatableText` (one json column, English fallback; assert through the cast, never byte-compare jsonb).
- **Profile partial-unique** (design D8) — `(customer_id, club_id) WHERE state NOT IN ('rejected','cancelled','inactive')` via raw `DB::statement` (portable PG17 + SQLite; verify both) + an app pre-check throwing localized `DuplicateProfileForClub`. Inert in the spine (all Profiles born `applied`) but correct for the deferred lifecycle.
- **Enum-test convention** — `tests/Unit/Modules/Parties/Enums/EnumsTest.php`, no `RefreshDatabase`; per enum map `cases()` → `name=>value` and assert verbatim + order-sensitive; `->toHaveCount(n)` where "exactly N cases" is a spec rule (`AccountType` length 1; `ProfileState` length 9).
- **PG17 gate** — every DB-touching task verified on local PostgreSQL 17 before done (`knowledge/testing/rules.md`); record the run here at task 6.2.

## Iterations
(append one entry per loop iteration — narrative; promote durable patterns above)

_None yet — awaiting APPROVED + `./ralph.sh`._
