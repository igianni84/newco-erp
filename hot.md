---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod`, task 4.1 ✅ (8/10): the demo seeder now stands up a walkable Producer SoD fixture with GENUINE creator lineage.** Added `seedProducerApprovalScenario()` to `DemoSeeder` (mirrors RM-07's Catalog `seedSodReviewScenario`): ONE Producer built through the **real `CreateProducer`** as `creator@newco.test` (via `ActorContext::runAs(NewcoOps, $creatorId, …)`), left `draft` with a real `ProducerCreated` the SoD floor's `creatorOf()` recovers, then `kyc_status = Verified` (audit-only). Only a DISTINCT seeded operator (`approver@newco.test`) can activate it — the creator is blocked on the SoD floor. Named `'Domaine Leroy'` via new public const `SOD_FIXTURE_PRODUCER_NAME`; wired in `run()` after the Catalog fixture (both post-`reset()` → deterministic lineage). Production guard KEPT (method runs inside `run()`). +3 DemoSeederTest cases (draft+KYC-cleared+real creator lineage / creator blocked via direct `ActivateProducer` throw / distinct approver activates end-to-end via `ViewProducer` console). Full suite **1975/1975**. Committed.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1975/1975** (10497 assn) · PHPStan max **0** · Pint clean · `openspec validate parties-producer-approval-sod --strict` valid. SQLite only (cross-engine `===` + loose-`toEqual` bigint reads ride PG17 at close, task 6.1).
- ⚠ **Full/arch suite = `php -d memory_limit=-1 vendor/bin/pest`** — `php artisan test` (128 MB subprocess) OOMs on the full suite AND any single arch file. Per-`--filter`/per-file runs fine under `artisan test`.

## Active Change & Next Task
- **ACTIVE = `parties-producer-approval-sod` (RM-08)**, APPROVED, building. Branch `ralph/parties-producer-approval-sod`. **8/10 done.**
- **NEXT = task 5.1 (doc honesty, NO code):** add a dated **in-place** correction to `decisions/2026-06-17-approval-separation-of-duties-role-gated.md` distinguishing the SoD floor **built in Catalog** (`catalog-lifecycle-approval`) from **Producer activation** (shipped KYC-gated single-operator) — RM-08 is what makes the "already correct for Parties" claim true. Mirror the **RM-09 in-place-correction precedent**: NO supersede, Decision/Alternatives/Trade-offs untouched, `decisions/INDEX.md` unchanged. `git diff` must show ONLY the added correction marker + reworded overclaim clause. Bullets: tasks.md 5.1. Read the ADR + find the RM-09 precedent correction for the marker format first.
- Then 6.1 close (PG17 full-suite + semantic-verify + tracker RM-08 🟡→✅).

## Blockers & Decisions Needed
- None blocking. Scope (Giovanni 2026-07-06): Producer-only SoD, 2-step Creator→Approver.
- **5.1 has no test** (doc-only) — but it's still ONE task/iteration; the "assert" is a `git diff` shape check (only the added marker + reworded clause; no decision-text change).
- **Repo sync:** `origin/main` @ `067f459`; ralph branch ahead. Assistant `git push` classifier-gated → Giovanni pushes at close.

## Open Patterns
- **Seeders are exempt from module boundaries, but the `{@see}` FQCN Pint trap still bites via UNUSED imports.** `DemoSeeder` imports Catalog+Parties freely (not `ModuleBoundariesTest`-governed), yet an FQCN `{@see \App\…\ProducerApprovalGovernance}` makes Pint auto-add an *unused* `use` (the guard is referenced, not called). Prose-name any class you only reference in a docblock; grep post-Pint to confirm no auto-add.
- **`CreateProducer::handle()` takes no KYC param + leaves `kyc_status` NULL.** For a KYC-cleared fixture/test, `$producer->update(['kyc_status' => KycStatus::Verified])` after create (`$guarded=[]`, cast, inert to activation). Omit optional `website` rather than invent a URL.
- **Genuine-lineage seeder/fixture idiom:** `app(ActorContext::class)->runAs(NewcoOps, $creatorId, fn () => app(CreateProducer::class)->handle(...))` records `ProducerCreated` with the creator's actor_id; a `factory()->create()`/plain `create()` row (e.g. the demo `leflaive` draft) is a null-creator vacuum and proves nothing. `callAction('activate')` auto-confirms the 3.1 `affordance.second_actor` modal, so the console happy path is unchanged.
- **Migrated tests grow the event set — scope activation assertions by NAME (`->where('name','ProducerActivated')->sole()`), never total-count.** Genuine lineage records Created + Activated.
- **Isolated-run false failure:** `ProducerConsoleI18nTest` alone fails its last test (`scanOperatorConsoleHardcodedSinks` declared in `ProductMasterConsoleI18nTest`, loaded suite-wide only) — run both i18n files together / full suite. Not a regression.
