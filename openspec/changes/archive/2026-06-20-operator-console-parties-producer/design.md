## Context

The operator-console kit (`app/Modules/OperatorPanel/Filament/Console/`) was extracted and proved against the **catalog** lifecycle by `operator-console-catalog-spine`. Two of its four pieces encode the catalog shape: `OperatorConsoleViewRecord` hard-codes the five governance verbs (`submit·reject·activate[SoD]·retire·reopen`), and `OperatorConsoleResource::lifecycleStateColumn()` reads the attribute `lifecycle_state`. The Parties **Producer** does not share that shape — `draft → active → retired` (two status verbs), KYC-gated (not SoD) activation, state attribute `status` (a `ProducerStatus`), plus a separate four-state provenance-KYC FSM. The domain backend is fully shipped (`app/Modules/Parties/Actions/*`); this change is a **pure operator surface** over it.

The kit-fit seam is resolved by ADR `decisions/2026-06-20-operator-console-non-catalog-lifecycle-trait-reuse.md`: reuse the kit **at the trait level** (`SurfacesDomainActions`) + `OperatorConsoleCreateRecord` verbatim + `OperatorConsoleResource`'s label/version helpers; the View page extends Filament `ViewRecord`, `use`s the trait, and assembles its own verbs. Read the proposal for motivation and `specs/operator-console/spec.md` for the three ADDED requirements. The verified Filament-5 / kit recipes are in the predecessor's `## Codebase Patterns` (`openspec/changes/archive/2026-06-20-operator-console-catalog-spine/progress.md`) — read them first.

## Goals / Non-Goals

**Goals:**
- Make the Producer fully operable through the panel: create (`draft` + `ProducerCreated`), the status FSM (activate [KYC-gated], retire [cascades Club sunset]), and the four-action KYC FSM (require/waive/verify/reject, audit-only).
- Prove the non-catalog trait-reuse pattern (the ADR) so `operator-console-parties-supply-side` (Club + ProducerAgreement) and the later Parties consoles reuse it.
- Keep every catalog console and the kit's catalog-shaped base classes untouched and green.

**Non-Goals:**
- No new Parties domain code (Actions/events/migrations) — surface only.
- No Club / ProducerAgreement / Customer / Account / Profile / Supplier / Hold surfaces (named follow-ons).
- No field-editing (no Parties update Action exists), no confirmation-modal UX polish, no authority-tier RBAC, no SPA frontend.
- No wiring of Catalog's `catalog_producer_states` projection — it is Catalog-owned and already fed by the `ProducerActivated`/`ProducerRetired` events this console now emits for real (a downstream effect, not a task here).

## Decisions

- **D1 — View page reuses the trait, not the catalog View base (the ADR).** `ViewProducer extends \Filament\Resources\Pages\ViewRecord` and `use SurfacesDomainActions`; it defines `i18nKey(): string => 'producer'` and `getHeaderActions(): array` returning the six actions built with `$this->lifecycleAction(...)`. It does **NOT** extend `OperatorConsoleViewRecord` (that would force the five catalog verbs). The catalog base classes are not edited.
- **D2 — Resource reuses labels + version; supplies its own status/KYC columns.** `ProducerResource extends OperatorConsoleResource` for `getModelLabel()/getPluralModelLabel()` (off `static i18nKey() => 'producer'`) and `versionColumn()` (Producer carries `version`). It does **NOT** call `lifecycleStateColumn()` (that reads `lifecycle_state`); instead it defines a `status` badge `TextColumn` rendering `ProducerStatus->value` and a `kyc_status` badge rendering `KycStatus|null` (blank when NULL) — both via `getStateUsing` + `instanceof BackedEnum` (the kit's type-clean pattern, no `Parties\Enums` import needed in the column closure). List columns: name · region · country · status · kyc_status · version. Infolist: identity attributes + the translatable description + the operated-Clubs read (`Producer::clubs()`).
- **D3 — Six form-less lifecycle actions; no SoD affordance; no confirmation modals.** All six are built with `lifecycleAction(verb, successKey, invoke)` — **no `$form`, no `$confirmationKey`** (Producer has no reject-notes form, and activation is not SoD-gated so it carries no `affordance.second_actor`). Verb → successKey map (label key = `actions.<Str::snake(verb)>`, success title = `notifications.<successKey>`):
  | verb (action id) | label key | successKey |
  |---|---|---|
  | `activate` | `actions.activate` | `activated` |
  | `retire` | `actions.retire` | `retired` |
  | `requireKyc` | `actions.require_kyc` | `kyc_required` |
  | `waiveKyc` | `actions.waive_kyc` | `kyc_waived` |
  | `verifyKyc` | `actions.verify_kyc` | `kyc_verified` |
  | `rejectKyc` | `actions.reject_kyc` | `kyc_rejected` |
- **D4 — invoke-closure shape (Parties Actions take `int $id`, not the model).** Each closure has the uniform `fn (Model $record, string $notes)` signature the trait expects (`$notes` is unused — no action has a form). It resolves the action and passes the **id**: e.g. `fn (Model $record) => app(ActivateProducer::class)->handle($this->recordOf(Producer::class, $record)->id)`. `recordOf()` narrows `Model → Producer` (phpstan-clean); `->id` is a typed `int` via the model's `@property int $id`. (Catalog's actions took the model; Parties' take the id — the one shape difference.)
- **D5 — Rejections surface by `RuntimeException` base type; no `Parties\Exceptions` import.** `IllegalProducerTransition` (incl. `::kycNotCleared`) and `IllegalKycTransition` both extend `RuntimeException`, so the trait's `surfaceLifecycleOutcome` catch renders them as the `notifications.action_failed` danger title with the exception's already-localized message as the body. The console imports only `Parties\Models\Producer` + the seven `Parties\Actions\*` (the `{Models, Actions}` carve-out). Name any domain exception in **prose backticks** in docblocks, never `{@see \App\Modules\Parties\Exceptions\X}` (Pint's `fully_qualified_strict_types` would re-add the forbidden import — lessons.md, 2026-06-20).
- **D6 — Create form + write-through.** `CreateProducer extends OperatorConsoleCreateRecord`. Form: `name`/`region`/`country` required `TextInput`; `appellation`/`website` optional `TextInput`; `description` optional `Textarea`. `createViaAction(array $data): Model` narrows the payload and calls `app(CreateProducer::class)->handle(name, region, country, appellation, description, website)` — wrap `description` as `TranslatableText::of($data['description'])` when present, else `null` (mirror catalog `tasting_notes`). `createRejectionField(): string => 'name'` is the inert safety net (Producer ships no create guard — **no** PR-style `UniqueConstraintViolationException` catch). Page/action class-name collision: `CreateProducer` (page) vs `CreateProducer` (action) → alias `use App\Modules\Parties\Actions\CreateProducer as CreateProducerAction;` (mirrors catalog `CreateSellableSku as CreateSellableSkuAction`).
- **D7 — File layout.** New sub-namespace `Filament/Resources/Parties/`: `ProducerResource.php` + `ProducerResource/Pages/{ListProducers,ViewProducer,CreateProducer}.php`. (Catalog lives under `Filament/Resources/Catalog/`.) `ListProducers` is the header create-link page (copy the shape from `ListFormats`).
- **D8 — i18n.** New `operator_console.producer.*` block in `lang/en/operator_console.php` (full) + `lang/it/operator_console.php` (per-key IT; `label`/`plural_label` may fall back to EN). The danger-notification **body** copy is the domain's (`parties.*`, shipped with the actions) — the console owns only `notifications.action_failed` + the six success titles + labels/columns/create-fields. The completeness test enumerates the **kit-contract keys** (resource: `label`, `plural_label`, `columns.{status,kyc_status,version}`; trait per the six verbs: `actions.{activate,retire,require_kyc,waive_kyc,verify_kyc,reject_kyc}` + `notifications.{activated,retired,kyc_required,kyc_waived,kyc_verified,kyc_rejected,action_failed}`) — these never appear as literals in the source, so only enumeration + `Lang::has(..., 'en', false)` catches a dropped key (the spine 5.1 pattern; reuse `scanOperatorConsoleHardcodedSinks` with a `function_exists` guard).

## Risks / Trade-offs

- **An implementer extends `OperatorConsoleViewRecord` out of catalog habit** → it would force submit/reject/reopen onto Producer. Mitigation: D1 is explicit; the lifecycle test asserts `assertActionExists('activate'/'retire')` AND `assertActionDoesNotExist('submit'/'reject'/'reopen')`, and asserts the activate action has no second-actor confirmation.
- **`lifecycleStateColumn()` reused → renders blank** (Producer has no `lifecycle_state`). Mitigation: D2 — own `status`/`kyc_status` columns reading the real attributes; the resource test asserts the `status` column renders the `ProducerStatus` value.
- **`Parties\Exceptions` import / FQ `@see` breaks the module boundary.** Mitigation: D5 — catch by `RuntimeException`; prose backticks; `ModuleBoundariesTest` + `NoEloquentWriteInOperatorPanelRule` stay green (run phpstan).
- **Passing the model (not the id) to a Parties Action** → type error / wrong call. Mitigation: D4 — `->id` after `recordOf`.
- **KYC actions wrongly expected to emit events or place Holds.** Mitigation: the KYC tests assert the `domain_events` count is **unchanged** across require/waive/verify/reject (audit-only) and that `status` is unmoved; no Hold assertions (none placed).
- **A dropped kit i18n key passes every behaviour test** (because `__()` renders the raw key). Mitigation: D8 — the kit-contract enumeration test.
- **Retirement cascade not proven.** Mitigation: a test seeds the Producer operating two `active` Clubs (+ one `closed`), retires via the console, and asserts both `active` Clubs → `sunset` with a `ClubSunset` each carrying the `ProducerRetired` causation, the `closed` Club unchanged.

## Migration Plan

No schema change, no data migration, no new dependency. Deploy = ship the Filament classes + the `operator_console.producer.*` lang block. Rollback = remove them (the Producer domain is unaffected). The change is additive to the running app.

## Open Questions

None blocking. The `parties.*` localized messages that back the danger-notification bodies ship with the domain actions — confirm the relevant keys resolve when wiring `surfaceLifecycleOutcome` (they are domain-owned, not console-owned). The closing-chain test is the PG17 task.
