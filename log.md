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
