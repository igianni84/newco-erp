---
type: meta
description: Hot cache — repo-state digest (~500 words), overwritten on every significant operation. Not a journal (chronology lives in log.md).
updated: 2026-07-06
---

# Hot Cache

## Last Updated
**2026-07-06 — RM-08 `parties-producer-approval-sod`, task 3.2 ✅ (7/10): the console activation happy path now runs under GENUINE distinct operators.** Migrated the parameterized activate happy-path test from a vacuous `Producer::factory()` fixture (null creator → SoD vacuously cleared) to real lineage: CREATOR op A stands the draft up via the real `CreateProducer` action → DISTINCT approver op B activates through the console (`actingAs($approver)` before `Livewire::test`). Asserts `active` + one `ProducerActivated` whose `actor_id` = approver `not->toEqual` creator (the distinctness proof), and `ProducerCreated` carries op A. Kept the 3-way KYC parameterization (NULL/not_required/verified — non-null set via `$producer->update(['kyc_status'])`, audit-only). Test-only (no prod code). Fixed the now-stale "only events are the console's" header clause. Full suite **1972/1972**. Committed.

## Build & Quality Status
- Stack: PHP 8.5 · Laravel 13 · Filament 5.6.7 · Pest · PHPStan max · Pint.
- Full suite **1972/1972** (10485 assn) · PHPStan max **0** · Pint clean · `openspec validate parties-producer-approval-sod --strict` valid. SQLite only (cross-engine `===` + loose-`toEqual` bigint reads + split chain assertions ride PG17 at close, task 6.1).
- ⚠ **Full/arch suite = `php -d memory_limit=-1 vendor/bin/pest`** — `php artisan test` (128 MB subprocess) OOMs on the full suite AND any single arch file. Per-`--filter`/per-file runs fine under `artisan test`.

## Active Change & Next Task
- **ACTIVE = `parties-producer-approval-sod` (RM-08)**, APPROVED, building. Branch `ralph/parties-producer-approval-sod`. **7/10 done.**
- **NEXT = task 4.1 (P2 demo enablement):** extend the demo seeder (`OperatorDemoSeeder`/`DemoSeeder`, mirror RM-07's `seedSodReviewScenario`) with a `draft`, KYC-cleared Producer built through the **real `CreateProducer`** as one seeded operator, activatable only by a DISTINCT seeded operator (a `factory()->create()` row proves nothing — null creator). Keep the production guard. Extend the DemoSeeder test: fixture Producer is `draft` w/ a real creator `actor_id`; a distinct seeded operator activates it end-to-end while the creator is blocked on the SoD floor. Read RM-07's seeder scenario + how the demo seeds/holds operator ids before writing. Bullets: tasks.md 4.1.
- Then 5.1 ADR honesty, 6.1 close.

## Blockers & Decisions Needed
- None blocking. Scope (Giovanni 2026-07-06): Producer-only SoD, 2-step Creator→Approver.
- **4.1 seeder pattern:** set the acting operator before `CreateProducer` so `ProducerCreated.actor_id` = the seeded creator (`ActorContext::runAs(NewcoOps, $creatorOpId, fn () => app(CreateProducer::class)->handle(...))`, or `actingAs` if the seeder path resolves the operator guard). Leave the Producer `draft` + KYC-cleared; only a DISTINCT seeded operator id can activate. Mirror RM-07's `seedSodReviewScenario` shape.
- **ADR honesty (5.1):** `decisions/2026-06-17-approval-separation-of-duties-role-gated` "already built in `parties-producer-lifecycle`" overclaim → in-place dated correction (RM-09-style, no supersede, INDEX unchanged).
- **Repo sync:** `origin/main` @ `067f459`; ralph branch ahead. Assistant `git push` classifier-gated → Giovanni pushes at close.

## Open Patterns
- **Genuine-lineage activation records TWO events (Created + Activated) — scope activation assertions by NAME, never total-count.** A migrated distinct-operator test grows the event set; `->where('name','ProducerActivated')->sole()` isolates it. `count()===1` on real lineage breaks.
- **`CreateProducer::handle()` takes no KYC param + leaves `kyc_status` NULL.** Parameterize KYC on real lineage via a direct `$producer->update(['kyc_status'])` (`$guarded=[]`, `ActivateProducer` neither bumps nor checks `version`, KYC FSM audit-only § 4.4). Console KYC path proven separately in `ProducerConsoleChainTest`.
- **Distinct-operator idiom (console):** `actingAs($opA)` → `CreateProducer` → `actingAs($opB)` → activate `Livewire::test` (fresh component reads the `operator` guard at record time). Non-vacuous proof = `actor_id not->toEqual($creator->id)` + `ProducerCreated toEqual($creator->id)`, else self-approval passes vacuously.
- **Affordance blast radius is TINY:** a console `confirmationKey` only breaks tests ASSERTING its absence; `callAction` auto-confirms. Sibling FSMs (ProducerAgreement/Club/Customer) untouched (Producer-only scope).
- **Isolated-run false failure:** `ProducerConsoleI18nTest` alone fails its last test (`scanOperatorConsoleHardcodedSinks` declared in `ProductMasterConsoleI18nTest`, loaded suite-wide only) — run both i18n files together / full suite. Not a regression.
