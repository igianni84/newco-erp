# Tasks — parties-hero-package-residuals

> One task per loop iteration. Tests are NEVER optional. Full suite: `php -d memory_limit=-1 vendor/bin/pest` (`artisan test` OOMs). PG17 lane: prefix `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=newco_test DB_USERNAME=newco DB_PASSWORD=newco` (container `pg` up). Typecheck `php -d memory_limit=-1 vendor/bin/phpstan analyse`; format `vendor/bin/pint`.
>
> **Read `design.md` R1–R5 first, and the archived `2026-07-09-parties-hero-package/progress.md` § Codebase Patterns.**
>
> **Three rules this change dies on:**
> 1. **NO production code changes.** `app/` is not touched by any task. The code is already correct — it is the spec and the suite that are wrong. If a task seems to want an `app/` edit, re-read design.md R1.
> 2. **Every pin is mutation-tested before its task is checked off** (design R5). A pin that survives its own mutant is decoration, and a later iteration will "clean it up".
> 3. **Only the full suite is proof.** 7 of the 16 files that execute these Actions drive them through Filament `callAction('approve')`, invisible to `grep ApproveProfile`. A `--filter` run is not proof.

## 1. The ordering pin — make the corrected requirement enforceable

- [x] 1.1 Pin **guard-before-lock** negatively in `tests/Feature/Modules/Parties/ProfileApprovalCapacityGateTest.php` (R1, R2)
  - Capture SQL with `DB::listen`; assert an `ApproveProfile` on a Profile outside `{applied, waiting_list}` emits **no `parties_clubs` statement at all** (`toBeEmpty()`), and raises the **from-state** reason (`cannot_approve`), never `club_at_capacity`
  - Assert the Profile is **not** moved to `waiting_list`
  - **Dataset across the later gate's outcomes** so independence is shown, not coincidental: at parity (capacity `0` — full while empty, the cheapest fixture), a free seat, and **explicitly uncapped** (an explicit `null` in `by_club_id` beneath a capped `default` — the only honest uncapped fixture; `PHP_INT_MAX` exercises the *capped* branch)
  - Drive at least `active` and `lapsed` from-states — the two the old prose would have diverted
  - Reuse the file's existing `DB::listen` idiom and fixture helpers. **Do not add a second Pest global helper with a name already declared elsewhere** — a redeclare is a fatal error that kills the entire run at suite-build time, not a shadow
  - **Mutant:** move `ApproveProfile.php`'s from-state `if` below `lockAndCountOccupiedSeats()`; run this file only; confirm the `parties_clubs`-statement assertion reds (and the `active` Profile lands on the waitlist); restore; re-run
  - Full suite green on SQLite **and** PG17; PHPStan 0; Pint clean

## 2. The event-envelope pin — `WaitingListJoined` is a root event

- [ ] 2.1 Pin root-ness at the **birth** entry point in `tests/Feature/Modules/Parties/ProfileBirthStateRoutingTest.php` (R3)
  - On the recorded `WaitingListJoined`: `causation_id` is null **and** `correlation_id === event_id`. Mirror `ProfileActivationTest.php:74-75` verbatim
  - **Not** in `WaitingListJoinedEventTest` — that is a pure `payload()` unit test that never touches the recorder, so root-ness cannot live there
  - **Mutant:** pass a `causationId` into `CreateProfile`'s `record()` call; confirm only this pin reds; restore
  - Full suite green both engines; typecheck; format

- [ ] 2.2 Pin root-ness at the **divert** entry point in `tests/Feature/Modules/Parties/ProfileApprovalCapacityGateTest.php` (R3)
  - Same two assertions on the `WaitingListJoined` recorded by `ApproveProfile`'s capacity divert. Two `record()` call sites ⇒ two pins; 2.1 does not cover this one
  - **Mutant:** pass a `causationId` into `ApproveProfile`'s `record()` call; confirm only this pin reds; restore
  - Full suite green both engines; typecheck; format

## 3. The console pins — two requirements that exist only as prose

- [ ] 3.1 Console **create-at-capacity** in `tests/Feature/Modules/OperatorPanel/Parties/ProfileCreateConsoleTest.php` (R4; operator-console — *Operator creates a Profile through the console*)
  - Drive the Filament create surface against an `active` Club at exactly its Hero-Package capacity: the Profile is born `waiting_list` (not `applied`), and **both** `ProfileCreated` and `WaitingListJoined` carry `actor_role: newco_ops` and `actor_id` = the operator's id (the domain test records `System` — this envelope is what only the console can break)
  - Assert the create form exposes **no** capacity field (the birth state is decided by the domain, never by the operator)
  - **Mutant:** hardcode `CreateProfile`'s birth state to `Applied`; confirm red; restore
  - Full suite green both engines; typecheck; format

- [ ] 3.2 Console **renew-at-capacity** in `tests/Feature/Modules/OperatorPanel/Parties/ProfileLifecycleConsoleTest.php` (R4; operator-console — *Operator advances a Profile through its lifecycle*)
  - A Profile lapsed within the 30-day grace whose Club has since reached exactly its capacity: `renew` is **visible** from `lapsed`, the domain rejects, and the console surfaces a **danger** notification whose body is the localized `parties.profile.club_at_capacity`. The Profile stays `lapsed` with `lapsed_at` intact and is **not** moved to `waiting_list`; no event recorded
  - Read `session('filament.notifications')` for `title` + `status` + `body`. **Not** `assertNotified()` — it compares titles only and PULLS destructively, so it can see neither `status` nor `body`
  - This is `renew`'s **second** UI-reachable rejection; the past-grace one is already pinned at `ProfileLifecycleConsoleTest.php:116`. While here, correct the `lang/{en,it}/operator_console.php` `notifications` comment, which enumerates the UI-reachable `action_failed` scenarios and omits this one
  - **Mutant:** drop `RenewProfile`'s capacity gate; confirm red; restore
  - Full suite green both engines; typecheck; format

## 4. Close

- [ ] 4.1 Close gate — full verification, and the residual sweep
  - `php -d memory_limit=-1 vendor/bin/pest` on **SQLite** and on **PG17**; `phpstan analyse` 0; `pint --test` clean; `openspec validate --all --strict` green
  - Confirm `git diff --stat` touches **no file under `app/`** (design R1 / Non-Goals). If it does, the change went wrong
  - Sweep for claims this change makes false: `grep -rn "only if a seat is free" openspec/ app/ docs/` and `grep -rn "assert the from-state" openspec/` — the corrected sequence must not survive anywhere in its old form. **Read the grep, do not count it**: this change's own delta quotes the superseded prose in its `_Source:_` line, on purpose
  - Update `docs/validation/Remediation_Tracker.md`: RM-05 still closes against a **documented subset** — this change closes **none** of `AC-K-J-14` / `J-15` / `J-15a` / `XM-19`. Say so, or a later reader will read "residuals closed" as "capacity is compliant"
  - `progress.md` § Codebase Patterns consolidated; `log.md` via `scripts/memlog.sh`; `hot.md` overwritten (≤550 words)
