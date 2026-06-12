# Architecture — Rules (apply by default)

> Promoted from `hypotheses.md` (3 dated confirmations) or derived directly from a canonical decision/arch-test. A contradiction demotes a rule back to `hypotheses.md`.

## The platform substrate speaks storage primitives across the module boundary, never `App\Modules\*` symbols

**Rule.** Code under `App\Platform\**` MUST NOT import any `App\Modules\**` symbol — *including* the `App\Modules\Module` registry enum. The boundary-law arch test `it_forbids_platform_code_from_depending_on_any_module` enforces it (platform is the foundation modules build on; a reverse dependency inverts the layering). Consequence for any platform API that carries a *module identity*: the parameter/column type is **`string`** — the module's registry value (`Module::X->value`) or a platform pseudo-module like `'platform'` — NOT the `Module` enum. The module-side caller owns the typed `Module` and serialises to `->value` at the boundary; the substrate stores/returns the string. Same shape as `event_deliveries.consumer` holding a string FQCN, and `domain_events.module` / `audit_records.module` being plain `string` columns (no `Module` cast).

**Origin (dated).** 2026-06-12, `foundations-domain-events-audit` task 3.3: `AuditRecorder` was first written `Module|string $module` (per design D3's sketch) and the full suite turned the arch test RED — `Expecting 'App\Platform' not to use 'App\Modules'.`. Refining the signature to `string $module` (drop the `Module` import; callers pass `Module::X->value`) returned it green. Design D3 explicitly labels its signature *"realization, loop may refine"*; design D1 ("platform must never depend on modules") + the arch test are canonical and outrank a realization detail they conflict with.

**Applies to.** `DomainEventRecorder` / `AuditRecorder` (`string $module`); every F2+ module emitter (`$recorder->record(..., module: Module::Commerce->value, ...)`); the `*.module` columns. Tests MAY import `Module` (the arch suite scans only `App\Platform` source, not `Tests\`). Document the call convention in `docs/event-substrate.md` (task 6.1).
