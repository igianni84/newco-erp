# Operations Log

> Append-only ledger. ONE line per significant operation, appended via `scripts/memlog.sh`:
> `## [YYYY-MM-DD HH:MM] {op} | {target} | {outcome}` — real-clock timestamp; outcome ≤280 chars (narrative → the change's `progress.md`).
> Rotate to `log-archive-YYYY-H{1,2}.md` past ~200KB (the `.claude/hooks/memory-health.sh` Stop hook warns). Earlier history: `log-archive-2026-H1.md`.

---

## [2026-06-13 08:17] audit | 360-degree read-only audit (5 agents) + gates | main 151/151 green; findings triaged into substrate-hardening change + second-brain source fix; F1 3/3 gap surfaced

## [2026-06-13 08:17] fix | second brain: memlog.sh + memory-health Stop hook + rules (.claude/CLAUDE.md, RALPH.md) | log timestamps now real-clock, outcome cap 280, rotation by size; log.md rotated to log-archive-2026-H1.md; PHP text->8.5, /opsx:verify removed, grill ADR override

## [2026-06-13 08:55] spec-to-change authored+approved | foundations-money-i18n-flags (F1 3/3) | 4 capability deltas (money/i18n/feature-flags new + event-substrate actor-context seam); 10 reqs/33 scenarios/14 tasks; validate --strict green; founder approved 3 default calls (DualCurrency in, welcome=placeholder, actor=resolver-only); APPROVED set; next=ralph

## [2026-06-13 09:02] ralph | foundations-money-i18n-flags 1.1 | green | Currency enum (5 ISO codes, fail-closed of(), match exponent) + 7 tests; suite 158/158, phpstan 0, no dep churn | 2 files

## [2026-06-13 09:14] ralph-recover | foundations-money-i18n-flags | Iter-1 halted exit-5: task-1.1 commit 866e92a swept untracked APPROVED into baseline..HEAD (benign, recurrence of 2026-06-12 audit-change trip). No revert (would kill green 1.1 work) — re-run safe, HEAD now tracks APPROVED. Lesson recidiva noted.

## [2026-06-13 09:20] harden | ralph.sh | Preflight auto-stages the active change's untracked artifacts into an approve: commit before BASELINE_SHA — kills the recurring APPROVED-sweep exit-5. Scoped to CHANGE_DIR, idempotent. Verified: 3-case bash test + bash -n. Protected edit, Giovanni-OK'd.

## [2026-06-13 09:31] ralph | foundations-money-i18n-flags 1.2 Money | green | 2 files (app/Platform/Money/Money.php + MoneyTest 8/8); suite 166/166, phpstan 0 @ max, validate strict ok; no composer churn

## [2026-06-13 09:38] ralph | foundations-money-i18n-flags 1.3 | green | FxRate VO (decimal-string, no float, verbatim) + 5 tests | 2 files | suite 171/171, phpstan 0, pint clean

## [2026-06-13 09:49] ralph | foundations-money-i18n-flags 1.4 | green | DualCurrencyAmount VO (D18 dual-record, DEC-169 payload) + 6 tests; suite 177/177 (+6), phpstan 0 @max, pint+validate-strict ok; no composer churn | 2 files

## [2026-06-13 10:05] ralph | foundations-money-i18n-flags 1.5 | green | 3 files (MoneyCast + Feature test + tasks/progress) | Section 1 Money VOs 5/5 complete; suite 183/183, phpstan 0, no composer churn

## [2026-06-13 10:14] ralph | foundations-money-i18n-flags 2.1 SupportedLocale | green | enum SSOT + config/i18n.php derives; isSupported/assertSupported; 196/196 (+13), phpstan 0 @ max; §2 i18n started; 4 new files

## [2026-06-13 10:25] ralph | foundations-money-i18n-flags 2.2 | green | 9 files (6 lang/{locale}/welcome.php + docs/i18n.md + INDEX row + LocalizationTest) | PHP-array group files chosen over JSON, native per-key en fallback | suite 200/200, phpstan 0, composer diff empty

## [2026-06-13 10:39] ralph | foundations-money-i18n-flags 2.3 | green | 4 files (TranslatableText VO + cast, 2 tests) | suite 212/212 +12, phpstan 0 @ max, pint clean, validate --strict valid; i18n 3/4, 8/14 tasks

## [2026-06-13 10:52] ralph | foundations-money-i18n-flags 2.4 | green | 2 files (welcome.blade replaced→minimal localized placeholder; WelcomePageTest 3) | suite 215/215 +3, phpstan 0 max | §2 i18n 4/4 done; 9/14 tasks

## [2026-06-13 11:05] ralph | foundations-money-i18n-flags 3.1 | green | laravel/pennant v1.23.0 installed (latest stable, ^13 compat), features table SQLite-clean + container resolves; suite 218/218; composer.lock Pennant-only, php ^8.3 untouched; 7 files

## [2026-06-13 11:19] ralph | foundations-money-i18n-flags 3.2 | green | 6 files | EXT-1 nft-on-chain flag + FeatureFlag enum + Features accessor; AppServiceProvider boot reg; docs/feature-flags.md (NS fallback); suite 226/226, phpstan 0, no composer churn

## [2026-06-13 11:30] ralph | foundations-money-i18n-flags 4.1 | green | ActorContext seam (singleton + runAs scoped override, gate-safe no-auth); demo refactored to read role via seam; suite 233/233 (+7), phpstan 0; 4 files

## [2026-06-13 11:46] ralph | foundations-money-i18n-flags 5.1 | green | forward-ref cleanup (event-substrate.md + DomainEventRecorder docblock → present tense @App\Platform\Money) + GUIDE F1 3/3 + CONTEXT.md +8 glossary terms + 2 doc-pin test files; suite 243/243 (+10), phpstan 0, composer untouched | 6 files

## [2026-06-13 12:00] ralph | foundations-money-i18n-flags 5.2 | green | 0 files (verify-only) | 33/33 scenarios mapped, suite 243/243, phpstan 0, validate strict ok, composer Pennant-only, php ^8.3 unchanged | ALL 14 tasks done -> CHANGE_COMPLETE

## [2026-06-13 12:08] ralph | foundations-money-i18n-flags final-pass | green | 0 files — 14/14 already done; re-verified 243 tests + phpstan 0 + pint + validate --strict; composer Pennant-only; no protected loop edits; CHANGE_COMPLETE re-emitted

## [2026-06-13 12:31] close | foundations-money-i18n-flags | GUIDE 2.7 close: re-verified loop (243 tests/phpstan0/pint/validate-strict); semantic-verify 33/33 scenarios 0 CRITICAL; branch CI green SQLite+PG17; merged --no-ff main@1ee1e00; archived +10 reqs to openspec/specs; pushed main@8fe0ec4. F1 foundations complete 3/3.

## [2026-06-13 16:04] author+approve | substrate-hardening | Authored OpenSpec change (proposal/design/specs-delta/tasks), validate --strict green, 17 tasks/6 groups. 2 ADDED reqs on event-substrate (C1 race-safety, C3 dead-letter obs); C7-C10 coverage-only. Created APPROVED + approve: commit on main.

## [2026-06-13 16:17] ralph | substrate-hardening 1.1 | green | C1 race fix: attempt() locked re-check + recordFailure() pending-guarded write; 2 TDD reflection guards; 245/245 SQLite+PG17

## [2026-06-13 16:36] ralph | substrate-hardening 1.2 | green | C3 dead-letter Log::warning/error + sweep Log::info summary; deliverDue->tally, AttemptOutcome enum; 248/248 SQLite+PG17, phpstan 0 max | 6 files

## [2026-06-13 16:43] ralph | substrate-hardening 1.3 | green | 5 files | C2 sweep mutex TTL bounded to 2-min lease (withoutOverlapping(2)); 248/248 SQLite+PG17; section 1 complete 3/3

## [2026-06-13 16:51] ralph | substrate-hardening 2.1 | green | C4 PHP floor ^8.3->^8.5 + lock hash re-sync (no pkg upgrades) + PlatformRequirementsTest 80400->80500 + 2 falsified floor comments | 5 files | 248/248 SQLite

## [2026-06-13 17:00] ralph | substrate-hardening 2.2 | green | 3 files | C5 .env.example EVENTS_SWEEP_* commented block (5/30/3600); boot+config read-back verified

## [2026-06-13 17:09] ralph | substrate-hardening 2.3 | green | 4 files | C6 pgsql timezone=UTC in config/database.php; suite 249/249 on SQLite+PG17 (PG proof: filter 2 asserts + tinker show-time-zone=UTC), phpstan 0, validate ok

## [2026-06-13 17:17] ralph | substrate-hardening 3.1 | green | C7 event_id pinned UUIDv7 (test-only, +1 assert at DomainEventRecorderTest:116); 249/249 SQLite+PG (880 asserts); phpstan 0 @max; pint clean; openspec valid; 7/17

## [2026-06-13 17:26] ralph | substrate-hardening 3.2 | green | 3 files (SweepTest +cap test, tasks.md, progress.md); 250/250 889 asserts SQLite+PG17; non-vacuity via transient RED+revert; phpstan 0; openspec strict valid

## [2026-06-13 17:42] ralph | substrate-hardening 3.3 | green | C9 actor_role CHECK rejection: new ActorRoleConstraintTest (2 engine-guarded tests, 6 asserts), 252/252 both engines (895===895), pg sqlstate 23514 verified | 4 files

## [2026-06-13 17:51] ralph | substrate-hardening 3.4 C10 | green | combined structural+before/after UPDATE rejected; ImmutabilityTest +1 test, 253/253 & 899 asserts on SQLite AND PG17 (899===899); test-only; transient-RED non-vacuity (2 RED, reverted) | 1 test +3 memory

## [2026-06-13 17:56] ralph | substrate-hardening 4.1 | green | C11 workflow-level concurrency: group=ci-${{github.ref}} cancel-in-progress on ci.yml (governs both lanes) + CiWorkflowTest structural guards; 254/254 SQLite; 4 files

## [2026-06-13 18:05] ralph | substrate-hardening 5.1 | green | C12 GUIDE §2.7 local-PG-17 verify step before merge (verbatim rules.md recipe) + §8 cheatsheet pointer; renumber 2->5; FoundationsDocsTest 8/8, suite 254/254 SQLite, phpstan 0; docs-only; 2 files

## [2026-06-13 18:12] ralph | substrate-hardening 5.2 | green | 3 files (docs/development.md +RALPH_MODEL/.claude/PHP-floor 8.4->8.5; DevelopmentDocsTest 6/6; suite 254/254)

## [2026-06-13 18:19] ralph | substrate-hardening 5.3 | green | 5 files: README C14 exit codes +2/+5, /opsx:verify→semantic-verify (GUIDE §2.7), skills verify→sync, php floor 8.4→8.5; suite 254/254 unchanged, phpstan 0, pint clean

## [2026-06-13 18:28] ralph | substrate-hardening 5.4 | green | C15: 4 op/sec gates (secrets/observability/PCI/sec-review) registered in decisions/INDEX.md; suite 254/254 unchanged; 3 files

## [2026-06-13 18:33] ralph | substrate-hardening 6.1 | green | cross-engine: SQLite 254/254 + PostgreSQL 17 254/254 (904 asserts each), pint/phpstan-max/openspec-strict clean | 2 files (tasks.md, progress.md)

## [2026-06-13 23:11] close | substrate-hardening 6.2 | 6.2 done -> 17/17 openspec Complete; validate --strict valid; 5/5 delta scenarios -> named passing tests (InlineDeliveryTest:170,189; SweepTest:241,271,295); 2 patterns confirmed; doc-only. Loop exited early at iter16, completed 6.2 manually. CHANGE_COMPLETE.

## [2026-06-13 23:25] close | substrate-hardening 2.7 | Closed 2.7 ritual: SQLite+PG17 254/254 re-verified; merged 0e2f3a5 + pushed; semantic-verify CLEAN (0 CRITICAL, 3 minor); archived ecead30 -> 2 reqs into specs/event-substrate; branch deleted. main==origin clean. Loop had exited early 16/17; 6.2 done interactively.

## [2026-06-14 19:17] spec-to-change | catalog-product-spine | Authored + strict-valid: Catalog Module 0 first slice (product spine — 7 entities, 11 reqs, 11 tasks) + ADR per-type-attr-tables (resolves DEC-073). Giovanni approved (verbal); awaiting human APPROVED marker -> ./ralph.sh.

## [2026-06-14 19:24] ralph | catalog-product-spine 1.1 | green | 4 files: ProductType+LifecycleState enums (D2/D3) + Unit EnumsTest (4 tests). Suite 258/258, phpstan 0@max, strict-valid. No DB this task.

## [2026-06-14 19:42] ralph | catalog-product-spine 2.1 Format | green | 6 files (catalog_formats migration + Format model + factory + FormatCreated event + CreateFormat action + FormatTest) | 262/262 SQLite & PG17, phpstan 0 @ max, pint clean, openspec --strict valid

## [2026-06-14 19:52] ralph | catalog-product-spine 2.2 Case Configuration | green | 6 new files (migration+model+event+action+factory+test) | suite 267/267 SQLite + PG17 (driver=pgsql) | phpstan 0 @ max | no breakability column (BR-RefData-2)

## [2026-06-14 20:14] ralph | catalog-product-spine 3.1 Product Master | green | 276/276 SQLite + PG17(17.10) | 11 new (2 migr, ProductMaster+WineAttributes models, ProductMasterCreated, 2 exceptions, CreateProductMaster, factory, lang/en/catalog) + 9-test ProductMasterTest; multi-table core+per-type-attrs, dedup, fail-closed WINE

## [2026-06-14 20:30] ralph | catalog-product-spine 3.2 Product Variant | green | 8 files | 284/284 SQLite + PG17 (DRIVER=pgsql 17.10) | phpstan 0@max | within-module belongsTo + getColumnListing facade trap

## [2026-06-14 20:44] ralph | catalog-product-spine 3.3 | green | Product Reference: single-table, 2 within-module FKs + DB unique(variant,format), no case_configuration_id; 6 files; SQLite 292/292 + PG17 292/292; phpstan 0@max; 6/11 done

## [2026-06-14 20:59] ralph | catalog-product-spine 4.1 | green | Sellable SKU (Intrinsic): catalog_sellable_skus + model + factory + SellableSKUCreated event + CreateSellableSku action; PR FK cascade/CaseConfig restrict; no DB unique; commercial cols plain (not i18n). suite 300/300, phpstan 0@max, PG17 300/300. 6 files

## [2026-06-14 21:18] ralph | catalog-product-spine 4.2 | green | 9 files (2 migrations+model+event+exception+action+factory+lang+test) | Composite SKU: M:N join, N>=2 pre-tx, producer-agnostic | 312/312 SQLite + PG17

## [2026-06-14 21:27] ralph | catalog-product-spine 5.1 | green | 1 new test file (CatalogNamingCascadeTest 3/3), suite 315/315, phpstan 0@max; no DB/no prod-code change

## [2026-06-14 21:34] ralph | catalog-product-spine 5.2 | green | 2 files (CONTEXT.md spine glossary + 7-event payload-contract note; docs-only, suite 315/315 unchanged)

## [2026-06-14 21:46] ralph | catalog-product-spine 5.3 | green | full-chain integration test (5 tests/30 assert); suite 320/320 SQLite + PG17 (DRIVER=pgsql 17.10); trap-3 key-order fix; ALL 11 tasks done -> CHANGE_COMPLETE | 3 files

## [2026-06-14 21:54] ralph | catalog-product-spine final-pass | green | re-verified: 320/320 SQLite, phpstan 0@max, pint clean, openspec --strict valid, no composer diff, PG17 record present — CHANGE_COMPLETE re-confirmed, awaiting human review/merge

## [2026-06-15 09:07] close | catalog-product-spine | GUIDE 2.7 close (interactive): PG17 suite 320/320 driver-proven pgsql 17.10 + semantic-verify CLEAN (0 CRITICAL) -> merge --no-ff main 5789f3a + push + branch deleted + openspec archive 0ef9539. main 320/320 SQLite, in sync w/ origin. No active changes.

## [2026-06-15 09:42] ADR | 2026-06-15-identity-auth (Module K gate) | Identity/auth gate CLOSED (grill, 5Q): first-party all actors (Fortify+Sanctum, EU-resident, no IdP); auth=foundation, principal refs party by id; multi-guard; spatie RBAC operator-scoped. CONTEXT.md +Identity&Access, INDEX updated. catalog-lifecycle-approval UNBLOCKED.

## [2026-06-15 10:52] spec-to-change | operator-auth-foundation | authored+validated --strict, founder-APPROVED. NEW operator-identity (4 ADDED) + MODIFIED event-substrate (ActorContext->operator guard) + platform. 11 tasks/6 groups; User->Operator cutover; 2FA opt-in TOTP. Awaiting human APPROVED marker -> ralph.

## [2026-06-15 11:04] ralph | operator-auth-foundation 1.1 | green | spatie/laravel-permission 8.0.0 installed + config/migration published (teams off), 5 RBAC tables, migration phpstan-excluded, PG17-verified 324/324

## [2026-06-15 11:10] ralph | operator-auth-foundation 2.1 | green | 2 files (operators migration + test) | 328/328 SQLite + PG17, phpstan 0 @ max, pint clean | 2 of 12 done

## [2026-06-15 11:32] ralph | operator-auth-foundation 2.2 | human-needed | spec mandates table 'operators' + model in OperatorPanel, but ModulePersistenceConventionsTest requires 'operator_panel_' prefix — irreconcilable, unanticipated. Fix=exempt auth principals+ADR (needs OK). 3 files in tree, not committed.

## [2026-06-15 13:26] unblock | operator-auth-foundation 2.2 | HUMAN_NEEDED resolved (Option A): auth-principal models (Authenticatable) exempt from module-prefix arch test (Operator->operators). Test skip + module-architecture MODIFIED delta + design D7 + ADR. Green SQLite+PG17 336/336, phpstan 0 @ max, pint. 2.3 next.

## [2026-06-15 13:39] ralph | operator-auth-foundation 2.3 | green | 4 files | operator session guard+provider+broker in config/auth.php alongside web; OperatorGuardTest 7/20; 343/343 SQLite+PG17; phpstan0@max; pint; validate ok

## [2026-06-15 13:50] ralph | operator-auth-foundation 3.1 | green | 4 files | AdminPanelProvider→authGuard(operator)+passwordReset()+opt-in MFA(recoverable); OperatorPanelTest rewritten; 346/346 SQLite & PG17; phpstan 0@max

## [2026-06-15 14:01] ralph | operator-auth-foundation 4.1 | green | 4 files | ActorContext→operator guard lazy per-call (override→NewcoOps/id→System); 347/347 SQLite+PG17; phpstan 0 max

## [2026-06-15 14:10] ralph | operator-auth-foundation 4.2 | green | end-to-end recorder proof (test-only): operator session → ActorContext → DomainEventRecorder persists newco_ops + operator id; 349/349 SQLite + PG17; 3 files

## [2026-06-15 14:18] ralph | operator-auth-foundation 5.1 | green | RoleSeeder: 3 bare roles (Creator/Reviewer/Approver) on operator guard, idempotent firstOrCreate, no perms; 352/352 SQLite+PG17, phpstan 0@max; 2 new files

## [2026-06-15 14:31] ralph | operator-auth-foundation 5.2 | green | 6 files — OperatorSeeder+DatabaseSeeder cut to Operator, bootstrap op gets all 3 roles; 356/356 SQLite+PG17, phpstan 0@max

## [2026-06-15 14:48] ralph | operator-auth-foundation 6.1 | green | removed orphaned User+UserFactory, dropped users-table block, config/auth.php->operator default; Laravel base-merge keeps inert web/users (new knowledge/laravel rule); 359/359 SQLite+PG17; new AuthDefaultsTest

## [2026-06-15 15:01] ralph | operator-auth-foundation 6.2 | green | docs-only, 4 files (CONTEXT.md Actor-context, docs/development.md spatie+wiring+env, decisions/INDEX.md built-marker, FoundationsDocsTest comment); 359/359 SQLite, phpstan max, validate strict; PG17 deferred to 6.3

## [2026-06-15 15:05] ralph | operator-auth-foundation 6.3 | green | cross-engine close: SQLite 359/359 + PG17 359/359 (live DRIVER=pgsql 17.10), phpstan 0@max, pint clean; spatie 8.0.0 recorded | CHANGE COMPLETE 12/12

## [2026-06-15 15:45] close | operator-auth-foundation | GUIDE §2.7: re-verified SQLite 359/359 + PG17 359/359 (live driver) + phpstan 0 + pint + semantic CLEAN → merged --no-ff (1e46082) + pushed + branch deleted + archived (d74c8fc) + pushed; main in sync w/ origin

## [2026-06-15 16:41] spec-to-change | parties-core | authored+validated (--strict) Module K 1st slice 'parties-core' (cap party-registry): 7 entities, 10 reqs/25 scenarios, 11 tasks, 5 *Created; marker-on-subtype per user (ADR=task1.1); Supplier/Account eventless; OC field-only. Awaiting human APPROVED

## [2026-06-15 16:51] ralph | parties-core 1.1 | green | ADR party-type-marker-on-subtype + INDEX row (docs-only); pint+lint+openspec validate --strict green | 4 files

## [2026-06-15 16:56] ralph | parties-core 1.2 | green | 5 files (4 identity/account enums + EnumsTest); full suite 366 green, phpstan max 0 err, strict valid, composer diff empty; no DB (enum-only)

## [2026-06-15 17:03] ralph | parties-core 1.3 | green | 6 files (5 registry/membership enums + extended EnumsTest, 18 tests); all 10 Parties enums exist; no DB

## [2026-06-15 17:14] ralph | parties-core 2.1 Producer | green | first Parties DB entity (migration+model+factory+ProducerCreated+CreateProducer+test); 384/1408 green on SQLite AND PG17; phpstan max 0; 6 new files

## [2026-06-15 17:26] ralph | parties-core 2.2 Supplier | green | 5 files (migration+model+factory+action+test); minimal entity (no status/version/event); full 389/1420 SQLite+PG17; party_type CHECK proven on PG

## [2026-06-15 17:41] ralph | parties-core 3.1 Club | green | 8 new files (migration/model/factory/event/exception/action/test + lang/en/parties.php) | first FK + first Money field | 396/1460 SQLite+PG17 | CHECKs+FK proven on PG

## [2026-06-15 17:52] ralph | parties-core 3.2 ProducerAgreement | green | 10 files (7 new + lang/tasks/progress) | 404/1504 SQLite & PG17; CHECK+2 FKs proven on PG; single-active not enforced (activation rule)

## [2026-06-15 18:17] ralph | parties-core 4.1 Customer+Account | green | 10 new files (2 migrations, 2 models, 2 factories, CustomerCreated, DuplicateCustomerEmail, CreateCustomer, 2 tests) + lang; 416/1576 SQLite AND PG17; 7 PG constraints proven; 8/11 done
