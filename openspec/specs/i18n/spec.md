# i18n Specification

## Purpose
TBD - created by archiving change foundations-money-i18n-flags. Update Purpose after archive.
## Requirements
### Requirement: Supported Locales

The platform SHALL define a single source of truth for the supported locales: exactly the six launch locales — `en`, `it`, `fr`, `de`, `ja`, `zh_Hans` (English, Italian, French, German, Japanese, Simplified Chinese) — with `en` as the final fallback locale. Adding a locale post-launch SHALL be a configuration change (extending the registry and adding translation files), never a schema migration. Locale validation across the platform SHALL reference this registry; a locale outside it SHALL be rejected.

_Source: spec/02-prd/Architecture_v0.3-MVP.md § 1.2 + § 5.1 (i18n-ready by design; launch locales Bottle Page + Consumer Portal six — EN+IT+FR+DE+JP+ZH; "adding a locale post-launch is configuration, not migration"; D2 KEEP) · spec/04-decisions/decisions.md DEC-031 (six launch locales) + DEC-127 (locale fallback chain — "English (final fallback)") · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md AC-0-XM-4 (six launch locales — EN, IT, FR, DE, JA, zh-Hans) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (the i18n schema pattern — D2, six-locale-ready) · CLAUDE.md invariant 12._

#### Scenario: The supported set is exactly the six launch locales

- **WHEN** the supported-locale registry is read
- **THEN** it contains exactly `en`, `it`, `fr`, `de`, `ja`, `zh_Hans` — no more, no fewer

#### Scenario: English is the fallback locale

- **WHEN** the final fallback locale is requested
- **THEN** it is `en`

#### Scenario: An unsupported locale is rejected

- **WHEN** a locale outside the registry (e.g. `es`, `zh_Hant`, `xx`) is validated
- **THEN** it is rejected as unsupported

### Requirement: No Hardcoded User-Facing Strings

Every user-facing string SHALL be resolved through Laravel localization (a translation key), never hardcoded in code or templates. The `lang/` directory SHALL provide translation resources for all six supported locales — English authored at launch; the other five MAY be partially populated (content may stagger; partial coverage is allowed) and fall back to English per key. The legacy `resources/views/welcome.blade.php` SHALL contain no hardcoded user-facing copy (debt S3): it is remediated to a minimal placeholder whose visible strings all resolve through translation keys, and it decides no frontend stack (the consumer/producer SPA is the TanStack ADR's, a later gate).

_Source: CLAUDE.md invariant 12 (no hardcoded user-facing strings; six locales EN/IT/FR/DE/JP/ZH) · spec/02-prd/Architecture_v0.3-MVP.md § 5.1 (i18n-keyed; adding a locale is configuration) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md AC-0-XM-4 (PIM does not enforce locale-completeness; partial coverage allowed) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (i18n schema pattern — D2) · hot.md (debt S3 — welcome.blade.php carries hardcoded copy)._

#### Scenario: A user-facing string resolves through a translation key

- **WHEN** a user-facing string is rendered for the active locale
- **THEN** it is produced by a translation key lookup, not a literal in code or template

#### Scenario: A key missing in a non-English locale falls back to English

- **WHEN** a translation key is requested in a locale that does not define it
- **THEN** the English value is returned (the final fallback)

#### Scenario: The welcome view renders only keyed strings

- **WHEN** `resources/views/welcome.blade.php` is rendered
- **THEN** its visible user-facing strings resolve through translation keys (no hardcoded copy remains)

### Requirement: Translatable Entity Attributes (i18n-keyed JSON)

The platform SHALL provide a reusable translatable-text primitive that holds a translatable attribute as i18n-keyed JSON — an object of `{ "<locale>": "<text>" }` with at most one entry per supported locale. Resolving it to a requested locale SHALL return that locale's text when present and SHALL fall back to English **for that attribute only** when absent (per-attribute fallback, not whole-page). Locale keys SHALL be validated against the supported-locale registry at the application layer (the storage column stays schema-less JSON). The primitive SHALL round-trip to and from its JSON representation without loss, so a module (Module 0 PIM, F2) can attach it to a translatable column with no further machinery.

_Source: spec/04-decisions/decisions.md DEC-064 (translatable strings: i18n-keyed JSON per attribute; shape `{"<locale>": "<text>"}`; "Locale validation: application-layer (the database column is schema-less JSON)"; no separate translation registry at launch) + DEC-127 item 4 (per-attribute fallback to English within a locale resolution) · spec/02-prd/Architecture_v0.3-MVP.md § 5.1 (i18n-keyed JSON per attribute on existing entities) · spec/03-acceptance/Module_0_Acceptance_v0.3-MVP.md AC-0-XM-4 (per-locale strings for all six launch locales) · spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (per-locale content + fallback)._

#### Scenario: Resolving returns the requested locale's text

- **WHEN** an i18n-keyed value with `it` and `en` entries is resolved for `it`
- **THEN** it returns the `it` text

#### Scenario: A missing locale falls back to English for that attribute only

- **WHEN** an i18n-keyed value lacking a `fr` entry is resolved for `fr`
- **THEN** it returns the `en` text for that attribute (no exception; whole-object fallback is not required)

#### Scenario: An unsupported locale key is rejected

- **WHEN** an i18n-keyed value is built with a key outside the supported-locale registry (e.g. `es`)
- **THEN** validation rejects it at the application layer

#### Scenario: The i18n-keyed JSON round-trips without loss

- **WHEN** a translatable value is serialised to JSON and rehydrated
- **THEN** every locale entry is preserved exactly

