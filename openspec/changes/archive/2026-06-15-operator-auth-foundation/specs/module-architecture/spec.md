## MODIFIED Requirements

### Requirement: Module Persistence Conventions

Module persistence SHALL be module-private: exactly one module owns each table's rows, lifecycle and operations. Every domain table introduced by a module SHALL be named with the owning module's snake_case prefix (e.g. `catalog_product_masters`, `parties_holds`), and every **domain** Eloquent model under `App\Modules\**` SHALL declare an explicit `$table` carrying that prefix. Platform tables — owned by no module (e.g. the event-substrate tables), **or platform-foundation records that mirror the framework's flat naming** — remain unprefixed. Specifically, an **authentication principal** (a model implementing `Illuminate\Contracts\Auth\Authenticatable`, e.g. the OperatorPanel `Operator` mapped to the flat `operators` table) is **exempt** from the module-prefix convention even though it lives inside a module: it is a platform-foundation login shell reached only through its named guard, never by a cross-module Eloquent query, join or import — so invariant 10's *substance* (no cross-module DB access) is preserved by the boundary-import test and the guard-by-name access pattern, not by the cosmetic table prefix. The convention SHALL be enforced by an architecture test that binds from the first module **domain** model onward and that skips authentication principals.

_Source: CLAUDE.md invariant 10 (no cross-module DB access) · spec/02-prd/Architecture_v0.3-MVP.md § 2.2 (for every entity exactly one module owns the row, lifecycle, and operations) · table-naming choice is dev-team scope (DEC-073), founder-confirmed 2026-06-12 — recorded in design.md Decision D6 · the auth-principal carve-out is design.md Decision D7 + decisions/2026-06-15-auth-principal-table-naming.md (auth principals are platform-foundation login shells; mirrors the framework's flat `users`/`operators` naming; forward-binds the deferred customer/producer principals) · decisions/2026-06-15-identity-auth.md (auth is a platform foundation; the Operator principal is owned by the OperatorPanel module)._

#### Scenario: Module model declares its prefixed table

- **WHEN** a class under `App\Modules\**` extending the Eloquent `Model` is introduced with no explicit `$table`, or with a `$table` not starting with the owning module's prefix
- **THEN** the architecture test suite fails

#### Scenario: Empty model set is handled honestly

- **WHEN** no module models exist yet
- **THEN** the persistence-convention test passes by a proven-empty scan, not by error or skip

#### Scenario: Authentication principal is exempt from the prefix

- **WHEN** a concrete Eloquent model under `App\Modules\**` implements `Illuminate\Contracts\Auth\Authenticatable` and maps to an unprefixed auth table (e.g. the OperatorPanel `Operator` mapped to `operators`)
- **THEN** the persistence-convention test passes — the auth principal is recognised as a platform-foundation login shell and skipped — while every non-principal module model still requires its module prefix
