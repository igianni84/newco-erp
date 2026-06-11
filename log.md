# Operations Log

> Append-only ledger. One entry per significant operation: `## [YYYY-MM-DD HH:MM] {op} | {target} | {outcome}`.
> Rotate to `log-archive-YYYY.md` past ~5000 lines. Chronology lives here; current state lives in `hot.md`.

---

## [2026-06-11 08:30] scaffold | repository | infrastructure created (CLAUDE.md, RALPH.md, ralph.sh, hooks, skills, memory files)
## [2026-06-11 08:30] scaffold | spec/ | immutable copy of handoff v0.3-MVP (45 files, source commit e55dfc8)
## [2026-06-11 08:35] openspec | init | structure + 5 opsx skills/commands generated (CLI 1.4.1)
## [2026-06-11 08:40] openspec | bootstrap-laravel-app | change drafted (9 tasks) — awaiting human approval
## [2026-06-11 09:05] push | origin/main | initial scaffold published by Giovanni (85881df)
## [2026-06-11 09:20] docs | GUIDE.md | operator playbook added (fase 0→go-live); ralph.sh/README verify references aligned (no /opsx:verify in CLI 1.4.1 core profile)
## [2026-06-11 09:41] harden | guardrails | P0 enforcement live: protected-paths hook (Edit/Write), git-guardrails write-verbs + loop-only rules (push/APPROVED/archive), ralph.sh integrity gate (exit 5) — 60/60 hook tests green
## [2026-06-11 10:20] review | bootstrap-laravel-app | fase-0 rilievi Giovanni: pinned Laravel 13.x + Filament 5.x (ADR superseded → stack-versions-and-filament-ai-tooling), task 3.2 Boost aggiunto (ora 10 task, CI→3.3 docs→3.4), direzione frontend TanStack registrata (.claude/memory + ADR; gate Module S invariato) — strict valid, NOT approved; edit CLAUDE.md in attesa di OK esplicito
## [2026-06-11 10:25] config | CLAUDE.md | edit autorizzato esplicitamente da Giovanni (AskUserQuestion, entrambe le righe): tech-stack pinnata a Laravel 13.x + Filament 5.x; riga gate storefront riscritta con direzione TanStack
## [2026-06-11 10:27] approve | bootstrap-laravel-app | APPROVED creato su istruzione esplicita di Giovanni ("mi tornano, procedi") dopo review fase-0 — change pronto per il loop (10 task)
## [2026-06-11 10:40] config | ralph.sh | --effort max di default su ogni iterazione (richiesta esplicita Giovanni; override per-lancio via RALPH_EFFORT) — flag validato su CLI installata, bash -n ok, commit db386d8
## [2026-06-11 10:51] ralph | bootstrap-laravel-app 1.1 | green | 21 files (Laravel 13.15.0 skeleton merged at root, .gitignore union, quality loop green, no pre-existing file touched)
## [2026-06-11 10:59] ralph | bootstrap-laravel-app 1.2 | green | 7 files (Pest 4.7 installed + tests/Pest.php, OPERATOR_* env contract in .env.example, /up health + :memory: environment Pest tests, fresh-file migrate verified)
## [2026-06-11 12:00] ralph | bootstrap-laravel-app 2.1 | green | 5 files (pint.json laravel-preset, composer format/lint aliases mirroring CLAUDE.md table, QualityToolingTest pins scripts+preset vs composer-require rewrites; full suite 9/16, both aliases green)
## [2026-06-11 12:30] ralph | bootstrap-laravel-app 2.2 | green | 5 files (both skeleton ExampleTests → Pest idiom, QualityToolingTest +test-script pin, composer test confirmed not re-added, test_filter verified --filter='that true is true'→1; full suite 10/18)
## [2026-06-11 12:19] ralph | bootstrap-laravel-app 2.3 | green | 8 files (Larastan v3.10.0 + PHPStan 2.2.2; phpstan.neon level=max no-baseline over app/database/routes/tests; phpstan-bootstrap.php ini_set 1G fixes 128M Homebrew OOM on the bare vendor/bin/phpstan analyse, no --memory-limit needed; composer analyse alias; 6 own-code level-max type errors fixed no-suppression; QualityToolingTest 5th pin; full suite 11/21)
## [2026-06-11 12:27] ralph | bootstrap-laravel-app 2.4 | green | 5 files (all 5 CLAUDE.md Quality Commands run in table order, all green: format/test_filter/test/type_check/lint; canonical version snapshot recorded in progress.md — PHP 8.5.2, Composer 2.9.2, Laravel 13.15.0, Filament n/a, Pest 4.7.2 (+plugin-laravel 4.1.0), PHPStan 2.2.2, Larastan 3.10.0, Pint 1.29.1; PlatformRequirementsTest pins PHP≥8.4 + Laravel ^13 floors; full suite 13/25)
## [2026-06-11 12:40] ralph | bootstrap-laravel-app 3.1 | green | 51 files (Filament v5.6.7 + Livewire 4.3.1 installed --no-interaction; AdminPanelProvider id=admin path=/admin generated+registered; User implements FilamentUser (403-gate outside local env, revisit at Module K ADR); config/operator.php + OperatorSeeder standalone idempotent w/ RuntimeException guards; phpstan stubFiles=filament/forms/.stubs.php for typed fillForm at level max; OperatorPanelTest 4 + OperatorSeederTest 3 incl. wrong-password + missing-password edges; 37 published assets committed; full suite 20/48, phpstan 0 @ max)
