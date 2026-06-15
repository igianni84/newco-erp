## MODIFIED Requirements

### Requirement: Operator Panel Shell

The platform SHALL provide an operator panel at `/admin` (Filament) that requires authentication on the **`operator` guard**. Unauthenticated visitors SHALL be redirected to the panel login. The authenticated principal SHALL be an **`Operator`** — the operator login principal owned by the OperatorPanel module, **not** a generic application user; the bootstrap "every account in the local `users` table is an operator" shell SHALL be removed. A seeded operator account — an `Operator`, credentials supplied via environment, never committed — SHALL be able to authenticate and reach the panel dashboard.

_Source: spec/02-prd/Admin_Panel_PRD_v0.3-MVP.md — the Admin Panel is a first-class launch surface (manual-first operations, D24); this requirement establishes its authenticated shell · decisions/2026-06-15-identity-auth.md (the panel authenticates on the operator session guard against the `Operator` principal) · openspec/changes/operator-auth-foundation/specs/operator-identity/spec.md (the Operator principal + `operator` guard this shell now binds to)._

#### Scenario: Unauthenticated visitor is redirected to login

- **WHEN** an unauthenticated client requests `/admin`
- **THEN** the response redirects to the operator panel login page

#### Scenario: Seeded operator can authenticate

- **WHEN** the seeded operator submits valid credentials at the panel login
- **THEN** the operator reaches the panel dashboard

#### Scenario: The panel authenticates the operator principal

- **WHEN** the panel authenticates a visitor
- **THEN** the authenticated principal is an `Operator` resolved through the `operator` guard, and no generic `users` table or `App\Models\User` shell remains in the application
