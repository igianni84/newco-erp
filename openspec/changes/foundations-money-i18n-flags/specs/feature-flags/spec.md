## ADDED Requirements

### Requirement: Feature-Flag Infrastructure

The platform SHALL provide feature-flag infrastructure (Laravel Pennant) so that build-incomplete or commercially-gated surfaces can be toggled without code changes. Feature flags SHALL have a single source of truth (defined feature names) and a reusable accessor for resolving a flag's state. The infrastructure SHALL be installed with its backing store (the `features` table migration, Postgres-truthful / SQLite-compatible) so a flag's value can be persisted and changed operationally; the operator surface that flips flags is a later change. Pennant is a first-party Laravel package adopted at its latest stable version, consistent with the stack ADR's framework-conventional posture — it requires no standalone dependency ADR.

_Source: spec/05-release/Build_Workplan_v0.3-MVP.md § 2 Phase 1 (MVP carries (i): "the feature-flag infrastructure that will gate the NFT/on-chain surfaces through the build (D12) is a Phase-1 platform concern") · decisions/2026-06-11-stack-versions-and-filament-ai-tooling.md (prefer framework-conventional first-party Laravel packages; pin exact installed versions in docs/development.md) · decisions/2026-06-12-production-db-engine.md (Postgres-truthful, SQLite-compatible migrations) · CLAUDE.md (NFT/on-chain decoupled behind a feature flag — D12)._

#### Scenario: The feature-flag infrastructure is installed

- **WHEN** the migrations run
- **THEN** the Pennant `features` backing table exists on both engines and the feature-flag service resolves through the container

#### Scenario: A defined feature resolves through the accessor

- **WHEN** a defined feature flag is queried through the reusable accessor
- **THEN** it returns a deterministic boolean state (its launch default unless an operational value has been stored)

### Requirement: NFT/On-Chain Decoupling Flag (EXT-1)

A feature flag (the re-scoped EXT-1 gate) SHALL gate the NFT/on-chain surfaces — NFT mint/burn, the custodial wallet, on-chain recovery and the Bottle-Page chain-link — and SHALL be **OFF by default at launch**. The flag SHALL NOT gate the per-bottle serialization workflow (NFC tag application + serial capture + the SerializedBottle ledger record + Logilize integration), which ships launch-ready. When the flag is off, the non-serialized (NS) path SHALL be the universal fallback — every downstream surface degrades gracefully to NS. Turning the flag on is gated on an external blockchain-expert review and is out of this change's scope.

_Source: spec/02-prd/Architecture_v0.3-MVP.md § 4 (Avalanche / EXT-1: "DECOUPLED off the launch critical path (D12 — DECOUPLE ≠ DEFER)"; "the per-bottle serialization workflow stays launch-ready"; "the NFT mint/burn + custodial wallet + on-chain recovery + Bottle-Page chain-link decouple"; "Gated by the re-scoped EXT-1 feature-flag") + § 6.1 (NFT on-chain D12 — "the NS path is the universal fallback (every downstream degrades gracefully)") + § 8.2 (the floor does not depend on the decoupled NFT workstream; the NS sub-pool ATP is the load-bearing floor) · CONTEXT.md (SerializedBottle / NS — "NFT mint/burn is decoupled behind a feature flag") · CLAUDE.md (D12: serialization ships launch-ready; mint/burn stays flagged off; the NS path is the universal fallback)._

#### Scenario: The NFT/on-chain flag is OFF by default

- **WHEN** the EXT-1 NFT/on-chain feature is resolved with no operational override
- **THEN** it is off

#### Scenario: The flag is the single named gate for on-chain surfaces

- **WHEN** code must decide whether an on-chain surface is active
- **THEN** it consults the EXT-1 flag through the accessor (and at launch always receives off), while the serialization workflow has no dependency on this flag

#### Scenario: The NS path is the documented fallback when the flag is off

- **WHEN** the EXT-1 flag is off (the launch state)
- **THEN** the documented platform convention is that downstream surfaces use the non-serialized (NS) path as the universal fallback
