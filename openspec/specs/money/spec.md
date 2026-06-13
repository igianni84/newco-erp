# money Specification

## Purpose
TBD - created by archiving change foundations-money-i18n-flags. Update Purpose after archive.
## Requirements
### Requirement: Money Value Object

The platform SHALL represent every monetary amount as an immutable `Money` value object composed of an integer count of minor units and a `Currency`. `Money` SHALL NOT be constructible from a floating-point value — there SHALL be no float-accepting constructor or factory — so a fractional-minor-unit or binary-rounding amount is unrepresentable. Arithmetic (addition, subtraction, negation) SHALL operate on integer minor units and SHALL reject operands whose currencies differ. `Money` SHALL be persistable to and rehydratable from its two components (minor-unit integer + currency code) without precision loss, and SHALL serialise to the envelope payload shape consumed by the domain-event and audit recorders (an integer minor-units field + an ISO 4217 currency code). `Money` MAY be negative (credits, refunds, reversals).

_Source: CLAUDE.md invariant 6 (money discipline — integer minor units + currency code, everywhere; never floats) · spec/02-prd/Architecture_v0.3-MVP.md § 5.2 Multi-Currency (D18 — FLOOR) · spec/04-decisions/decisions.md DEC-169 (every Module E financial-event payload carries `amount` + `currency`) · decisions/2026-06-12-event-substrate-and-audit-store.md (payload discipline — "money = integer minor units + ISO 4217 code") · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (the multi-currency dual-record schema pattern — D18 FLOOR, full pattern) · CONTEXT.md (Club Credit — a monetary credit entity attached to a membership)._

#### Scenario: Money cannot be constructed from a float

- **WHEN** code attempts to create a `Money` from a floating-point amount (e.g. `12.34`)
- **THEN** no such construction path exists — `Money` is built only from an integer minor-units count plus a `Currency`

#### Scenario: Minor units round-trip without precision loss

- **WHEN** a `Money` of a given minor-units count and currency is serialised and rehydrated
- **THEN** the rehydrated value equals the original exactly (same minor units, same currency)

#### Scenario: Adding different currencies is rejected

- **WHEN** two `Money` values of different currencies are added
- **THEN** the operation throws; same-currency addition returns the integer-minor-units sum

#### Scenario: Money serialises to the minor-units payload shape

- **WHEN** a `Money` is serialised for an event or audit payload
- **THEN** it yields an integer minor-units value and an ISO 4217 currency code (never a float, never a formatted string)

#### Scenario: Negative money is representable

- **WHEN** a `Money` is created with a negative minor-units count (a credit or refund)
- **THEN** it is valid and arithmetic preserves the sign

### Requirement: Currency

A `Currency` SHALL carry its ISO 4217 alphabetic code and its ISO 4217 minor-unit exponent (the number of decimal places), so that `Money` knows how many minor units make one major unit. The launch-supported set SHALL be exactly EUR (the base currency), USD, GBP, CHF and JPY, with exponent 2 for EUR/USD/GBP/CHF and exponent 0 for JPY. Constructing a `Currency` from a code outside the supported set SHALL be rejected (fail-closed — a wrong or assumed exponent is a money bug). Adding a currency post-launch SHALL be a single registry addition, not a schema migration (currencies are added "on demand").

_Source: spec/04-decisions/decisions.md DEC-037 (customer-facing launch set: EUR base + USD + GBP + CHF + JPY; "Add others post-launch on demand") · spec/02-prd/Architecture_v0.3-MVP.md § 5.2 (the launch currency set) · ISO 4217 (minor-unit exponents — JPY = 0; EUR/USD/GBP/CHF = 2) · CLAUDE.md invariant 6._

#### Scenario: JPY has zero minor-unit digits

- **WHEN** a `Currency` for JPY is created
- **THEN** its minor-unit exponent is 0 (one minor unit equals one yen)

#### Scenario: EUR, USD, GBP and CHF have two minor-unit digits

- **WHEN** a `Currency` for EUR, USD, GBP or CHF is created
- **THEN** its minor-unit exponent is 2 (one hundred minor units per major unit)

#### Scenario: EUR is the base currency

- **WHEN** the base currency is requested
- **THEN** it is EUR

#### Scenario: An unsupported currency code is rejected

- **WHEN** a `Currency` is constructed from a code outside the launch set (e.g. `XAU`, or a malformed code)
- **THEN** construction throws (fail-closed) rather than assuming a default exponent

### Requirement: FX Rate

An `FxRate` SHALL hold an exchange rate as an exact decimal string (e.g. `"1.0842"`) and SHALL NOT accept or expose a floating-point value, preserving the rate bit-for-bit so a locked rate survives storage and read-back unchanged. This value object is the typed enforcement of the substrate's payload contract that FX rates are decimal strings, never floats. Construction SHALL reject any value that is not a well-formed decimal string.

_Source: decisions/2026-06-12-event-substrate-and-audit-store.md (payload discipline — "FX rates = decimal strings, never floats"; "D18: refunds settle at the exact locked rate") · openspec/specs/event-substrate/spec.md Requirement: Domain Event Envelope ("FX rates as decimal strings (never floats)") + Scenario "FX rates survive as exact decimal strings" · spec/02-prd/Architecture_v0.3-MVP.md § 5.2 (per-leg rate-lock) · CLAUDE.md invariant 5 (locked FX rate)._

#### Scenario: A decimal-string rate is preserved exactly

- **WHEN** an `FxRate` is created from `"1.0842"` and read back
- **THEN** it returns exactly `"1.0842"` (a string, not a float; no precision drift)

#### Scenario: An FX rate cannot be constructed from a float

- **WHEN** code attempts to create an `FxRate` from a floating-point value
- **THEN** no such construction path exists — the rate is built only from a decimal string

#### Scenario: A malformed rate string is rejected

- **WHEN** an `FxRate` is constructed from a non-decimal string (e.g. `"1.08.42"`, `"abc"`, empty)
- **THEN** construction throws

### Requirement: Dual-Currency Amount

A `DualCurrencyAmount` SHALL represent the D18 dual-record amount as the customer-currency `Money`, the EUR-equivalent `Money`, the locked `FxRate`, and the rate's timestamp — the immutable bundle every customer-facing financial event records. The EUR leg's currency MUST be EUR. It SHALL serialise to the recorded payload shape `amount` + `currency` + `eur_equivalent_amount` + `fx_rate` + `fx_rate_date` (DEC-169). It SHALL preserve the locked rate verbatim (refunds settle at the original captured rate — the value object never re-derives a rate). It SHALL be pure representation: it SHALL carry no FX policy (which leg locks when, snapshot/buffer/refresh mechanics) — that is Module E's responsibility (DEC-038/DEC-169) and is out of this change's scope.

_Source: CLAUDE.md invariant 5 (dual currency — every customer-facing financial event records customer currency AND EUR with a locked FX rate; refunds settle at the original captured rate) · spec/04-decisions/decisions.md DEC-169 (dual-currency recording: `amount` + `currency` + `eur_equivalent_amount` + `fx_rate` + `fx_rate_date`) + DEC-038 (FX principle: EUR base, daily snapshot) · spec/03-acceptance/Module_E_Acceptance_v0.3-MVP.md AC-E-J-43 + AC-E-BR-FX-1 (dual-currency on every event — FLOOR D18) · spec/02-prd/Architecture_v0.3-MVP.md § 5.2 (dual-recording machinery; per-leg rate-lock; "Refunds use the original captured rate") · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (the dual-record schema pattern — D18 FLOOR, full pattern) · CONTEXT.md (Dual-currency recording)._

#### Scenario: Dual-currency amount serialises to the DEC-169 payload shape

- **WHEN** a `DualCurrencyAmount` is serialised for a financial-event payload
- **THEN** it yields exactly `amount` (minor units), `currency`, `eur_equivalent_amount` (minor units), `fx_rate` (decimal string) and `fx_rate_date`

#### Scenario: The EUR leg must be in EUR

- **WHEN** a `DualCurrencyAmount` is constructed with a non-EUR currency for the EUR-equivalent leg
- **THEN** construction throws

#### Scenario: The locked rate is preserved for refunds

- **WHEN** a `DualCurrencyAmount` is created with a locked `FxRate` and later read for a refund
- **THEN** it returns the original locked rate unchanged (no fresh rate is derived)

