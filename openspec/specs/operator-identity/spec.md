# operator-identity Specification

## Purpose
TBD - created by archiving change operator-auth-foundation. Update Purpose after archive.
## Requirements
### Requirement: Operator Principal

The platform SHALL provide an `Operator` authenticatable as the **sole** operator login principal, owned by the OperatorPanel module. For an operator the login principal **is** the acting identity: an `Operator` SHALL NOT be a Module K party and SHALL NOT carry or reference a Customer / Producer / party row (operators have no party). The principal SHALL hold credentials only — a display name, a **globally unique** email, and a hashed password (framework-default hashing) — and SHALL replace the bootstrap `users` / `App\Models\User` shell, which SHALL be removed. The acting `actor_id` for an operator's actions SHALL be the `Operator` id.

#### Scenario: An operator authenticates as itself

- **WHEN** a seeded operator submits valid credentials at the operator panel
- **THEN** it is authenticated as an `Operator`, and the `actor_id` recorded for the actions it drives is that `Operator`'s id (no party row is involved)

#### Scenario: The operator principal is not a party

- **WHEN** the operator schema and model are inspected
- **THEN** they carry credentials only (name, unique email, hashed password, optional 2FA secret/recovery columns) and **no** Customer / Producer / party reference column or relation

#### Scenario: Operator email is globally unique

- **WHEN** an operator is created with an email already held by another operator
- **THEN** the creation is rejected (the email is unique)

#### Scenario: The bootstrap user shell is gone

- **WHEN** the codebase is inspected after this change
- **THEN** there is no `App\Models\User` and no generic `users` table — the operator principal (`Operator` / `operators`) is the only authenticatable

_Source: decisions/2026-06-15-identity-auth.md (Auth is a platform foundation; for an Operator the principal IS the identity, operators have no party row, owned by the OperatorPanel module; separate principals per actor class) · CONTEXT.md → Identity & Access (Operator; Authentication principal) · spec/02-prd/Module_K_PRD_v0.3-MVP.md § 7 (a Customer is identity+eligibility, never a login) · § 4.1 (email globally unique) · CLAUDE.md invariant 10._

### Requirement: Operator Authentication Guard

The platform SHALL configure an `operator` authentication guard using the **session** driver, backed by an Eloquent provider for the `Operator` model, and the Filament `/admin` panel SHALL authenticate against this `operator` guard. Operators SHALL **not** be able to self-register — there SHALL be no public operator registration surface (operators are provisioned). Operator **password reset** SHALL be available through an operator password-reset broker (the email transport itself is a hosting-gate concern and does not block the capability). The customer and producer guards are out of scope for this change and SHALL remain unwired (deferred to the Module S storefront / TanStack frontend gate); the `producer` and `customer` `actor_role` values nonetheless remain valid in the envelope contract.

#### Scenario: The operator guard is session-backed and backs the panel

- **WHEN** the authentication configuration is inspected
- **THEN** an `operator` guard exists with the `session` driver and a provider resolving the `Operator` model, and the Filament `/admin` panel's auth guard is `operator`

#### Scenario: No operator self-registration

- **WHEN** the operator panel authentication surface is inspected
- **THEN** it exposes login and password reset but **no** public registration route or self-signup affordance

#### Scenario: Operator password reset is available

- **WHEN** a password reset is requested for a known operator email
- **THEN** a reset is issued through the operator password-reset broker (transport per the hosting gate); an unknown email yields no account disclosure

#### Scenario: Customer and producer guards stay unwired

- **WHEN** the authentication configuration is inspected
- **THEN** no `customer` or `producer` guard is wired by this change, and only the `operator` guard authenticates a principal

_Source: decisions/2026-06-15-identity-auth.md (operator → session guard + Filament; multi-guard separated by actor class & surface technology; customer/producer guards deferred to the Module S gate; Socialite SSO a deferred seam) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md § 1.3 (operator actions on the panel) · § 2.1 (operators are staff; the one producer write is operator-on-behalf at launch) · decisions/2026-06-11-stack-versions-and-filament-ai-tooling.md (Filament 5.x ships the session guard + login)._

### Requirement: Operator Two-Factor Authentication (opt-in)

The operator panel SHALL offer **optional** TOTP-based two-factor authentication, with recovery codes, that an operator MAY enable for its own account. When an operator has enabled 2FA, authentication for that operator SHALL require a valid second factor (a TOTP code or a recovery code) in addition to the password. The platform SHALL NOT require 2FA enrolment at launch — enrolment is per-operator and optional, and the MFA-**enforcement** policy is deferred to the Architectural Security Review gate. The 2FA secret and recovery codes SHALL be stored encrypted at rest.

#### Scenario: An operator enables 2FA

- **WHEN** an operator enrols TOTP 2FA from its profile
- **THEN** an encrypted shared secret and encrypted recovery codes are stored for that operator, and its subsequent logins require a valid TOTP code (or a recovery code) after the password

#### Scenario: 2FA is opt-in, not enforced

- **WHEN** an operator that has not enrolled 2FA authenticates
- **THEN** it signs in with password alone — no platform-wide enrolment requirement is imposed at launch

_Source: decisions/2026-06-15-identity-auth.md (first-party TOTP 2FA capability; operators on Filament's session guard; "MFA-enforcement policy … fall under the Architectural security review gate") · spec/02-prd/Architecture_v0.3-MVP.md § 7.1 (DEC-073 — MFA/security mechanisms are dev-team scope) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (authentication wired) · founder decision 2026-06-15 (opt-in 2FA available at launch, enforcement deferred)._

### Requirement: Operator Role Mechanism (RBAC)

The platform SHALL provide a DB-backed, **runtime-configurable** operator role/permission mechanism (`spatie/laravel-permission`), **guard-aware** and **operator-scoped**, with the `Operator` model carrying roles. The three spec-named roles **Creator**, **Reviewer**, and **Approver** SHALL be seeded on the `operator` guard as **mechanism (data) only** — each carrying **no** permission grants — and the change SHALL define **no** authority-tier / "which-role-may-run-which-capability" policy (that policy is admin-configurable and downstream, `feedback_prd_rr_approval`). Customers and producers SHALL NOT appear in the operator permission matrix. The separation-of-duties floor (the 3-step Creator→Reviewer→Approver progression with **no self-approval**) SHALL NOT be modelled as a static permission here — it is per-instance transition logic deferred to `catalog-lifecycle-approval`; this change only makes the actor identity and the role rows available for that slice to consume.

#### Scenario: The three roles are seeded as bare mechanism

- **WHEN** the role seeder runs
- **THEN** roles `Creator`, `Reviewer`, and `Approver` exist on guard `operator`, each with **zero** permissions attached, and an `Operator` can be assigned or unassigned any of them at runtime

#### Scenario: RBAC is operator-scoped

- **WHEN** the role mechanism is inspected
- **THEN** every seeded role's guard is `operator`, and no customer or producer is assignable an operator role

#### Scenario: No authority-tier policy is encoded

- **WHEN** this change's role mechanism is inspected
- **THEN** there is **no** role→capability mapping (no permission defines which operation a role may run) and **no** separation-of-duties transition is enforced — the surface stays role-agnostic at launch (authority-tier policy deferred to `feedback_prd_rr_approval`; the SoD transition deferred to `catalog-lifecycle-approval`)

#### Scenario: Seeding is idempotent

- **WHEN** the role seeder runs a second time
- **THEN** the three roles still exist exactly once (no duplicates created)

_Source: decisions/2026-06-15-identity-auth.md (Operator RBAC = spatie/laravel-permission, runtime-configurable = the spec's "admin-configurable", guard-aware/operator-scoped; RBAC operator-scoped — customers/producers not in the matrix; the SoD floor is module logic not a permission; role catalogue / authority-tier deferred to feedback_prd_rr_approval; Filament Shield deferred) · spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md § 1.4 + § 9.2 (RBAC admin-configurable + downstream; role-agnostic at the PRD layer) · § 3.0 + § 5.2 (the spec-named Creator → Reviewer → Approver; self-approval never allowed — the floor) · CLAUDE.md invariant 10._

