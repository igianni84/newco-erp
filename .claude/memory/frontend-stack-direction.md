# Frontend stack direction (founder, 2026-06-11)

**Direction:** consumer storefront AND producer portal will NOT be PHP-rendered. Giovanni wants **TanStack** (https://tanstack.com/ — TypeScript SPA: Start/Router/Query family). Livewire/Inertia are ruled out for those surfaces. Filament/Livewire stays operator-panel-only.

**Status:** registered direction, NOT yet the formal decision. The ADR gate is unchanged: before the first Module S storefront slice, run a `grill-with-docs` session and write the ADR (see decisions/2026-06-11-stack-versions-and-filament-ai-tooling.md, open sub-decisions table).

**Implications the gate ADR must resolve** (why this can't be rubber-stamped now):
- API layer for the SPA: the spec keeps wire-level API shape dev-team scope (DEC-073); the modular monolith currently assumes server-rendered consumers.
- Customer auth across SPA + Laravel backend (interacts with the open identity/auth ADR, Module K gate).
- i18n ×6 locales outside Laravel localization (CLAUDE.md invariant 12 says all user-facing strings via Laravel localization — the ADR must reconcile this for the SPA: shared translation source vs duplicated catalogs).
- Money rendering in the SPA (integer minor units + currency must survive the API boundary; no float drift client-side).
- SEO/SSR for storefront browsing (TanStack Start vs pure SPA).
- EU data residency + hosting of a second deployable artifact.

**Spec scope facts (verified 2026-06-11):** storefront frontend is IN scope (Build Workplan Phase 4 "Frontend (parallel)" — Consumer Portal self-serve KEPT); Producer Portal is IN scope read-only + one write (membership approve/decline, Phase 2; D23 7-section reporting, Phase 3); producer write UIs deferred post-launch (Admin Panel PRD §6); NO supplier portal. Frontend stack itself is explicitly dev-team scope (DEC-073, Architecture §0.5).
