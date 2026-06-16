---
name: dreaming
description: Scheduled propose-only curation of the repo's memory system. Reviews knowledge/, log.md and progress.md, then promotes confirmed hypotheses to rules, extracts recurring mistakes into lessons and cross-change patterns into knowledge, and flags stale memory — applied on a dream/<date> branch + PR for human review, never to main. Invoke on a cadence (cloud routine) or manually to validate.
disable-model-invocation: true
---

<what-to-do>

You are running a **Dreaming** pass: a propose-only curation of this repo's memory
system (the project's "second brain"). You read the memory, decide what it has
earned, apply only the safe curations **on a new branch**, open a **PR** whose body
is the dream report, and stop. The PR is the review gate — that is what makes
Dreaming safe to schedule. You NEVER commit to `main`, NEVER merge.

Unlike the ralph loop (which never pushes — humans do), Dreaming's whole purpose is
to open a PR, so it DOES push its own `dream/<date>` branch. Never `main`.

Steps:

1. **Setup.** Read the clock with `date`. From `main`, create/checkout
   `dream/<YYYY-MM-DD>`. Find the previous dream (latest `dreams/*.md` or `dreaming`
   line in `log.md`) to scope work to memory changed *since then*; first run = full scan.
2. **Read the memory** (only what changed since the last dream, except first run):
   - `knowledge/*/hypotheses.md` — confirmation counts; `knowledge/*/rules.md` —
     contradictions (files where present)
   - `log.md` — `blocked` / `human-needed` / repeated friction
   - recent `openspec/changes/**/progress.md` → `## Codebase Patterns` (active + recently archived)
   - `lessons.md` — what's already captured (avoid duplicates)
   - `hot.md` — Open Patterns (stale check only)
3. **Decide** the curations (four actions below).
4. **Apply** only the safe, unambiguous curations as edits on the branch; everything
   judgment-heavy goes to the report as a *proposal*, unedited. **Cap the diff:** on a
   first run or any large backlog, APPLY at most the ~3–5 highest-confidence curations
   and list the rest under *Proposed* — never open an unreviewably large PR. Update
   `knowledge/INDEX.md` if you added/moved a domain.
5. **Log it:** `scripts/memlog.sh "dreaming" "<scope>" "<n promoted, n lessons, n knowledge, n flags>"`.
6. **Report + PR:** write the report to `dreams/<YYYY-MM-DD>.md`, commit, push the
   branch, and `gh pr create` with the report as the body. If push/`gh` is
   unavailable, leave the branch + report file for the human and say so.
7. Output a five-line summary (counts + PR link).

</what-to-do>

<supporting-info>

## What this is NOT
- NOT auto-apply to `main` — everything lands via PR review.
- NOT a replacement for `memory-health.sh` (that Stop hook catches mechanical drift:
  hot.md word count, log.md size). Dreaming does the *semantic* curation it can't.
- NOT a place to invent confirmations or fabricate patterns. Only promote what the
  memory already earned.

## The five curation actions

**1. Promote hypothesis → rule.** A hypothesis in `knowledge/<domain>/hypotheses.md`
with **≥3 genuine dated confirmations** (per `.claude/CLAUDE.md`) → move it to
`rules.md`, remove from `hypotheses.md`. At 2 confirmations, leave it and note "1
away" in the report. Never invent the third.

**2. Demote rule → hypothesis.** A rule contradicted by recent `progress.md`/`log.md`
evidence → move back to `hypotheses.md` with a dated note of the contradiction. Flag
prominently — a demotion is a real signal, not noise.

**3. Extract lesson.** A mistake/blocker pattern recurring in `log.md` or `progress.md`
`> ⚠ FAILED` notes that is NOT yet in `lessons.md` → append a `Mistake → Correction →
Rule` entry (lessons.md format). If it already exists and recurred, add a dated
"Recurred" note instead (mirror the existing recurrence style).

**4. Extract knowledge.** A reusable pattern appearing across different changes'
`## Codebase Patterns` (or progress/log evidence), not yet in `knowledge/`:
- **≥3 genuine dated confirmations in DIFFERENT changes → it is rule-grade on arrival.**
  Promote it DIRECTLY to `knowledge/<domain>/rules.md`, citing the changes as the dated
  confirmations. A scan over history finds patterns already confirmed many times — do
  NOT route those through a 1-confirmation hypothesis.
- **seen in only 1–2 changes → add to `knowledge/<domain>/hypotheses.md`** as a
  hypothesis with its dated confirmation(s) (the framework default — let it earn its way
  to a rule later).
Create the domain + update `knowledge/INDEX.md` if new.

**5. Confirm an existing rule.** When a recurrence matches a rule ALREADY in a
`rules.md`, do NOT recreate it — append a dated confirmation/observation line to that
rule and note the confirmation in the report. A rule re-seen in the wild is
strengthened, not duplicated.

## Report-only — flag, do NOT edit
- `hot.md` is volatile (overwritten every session) — never edit it here; just note
  stale Open Patterns.
- Domains stuck at `seed` / 0-confirmation hypotheses for a long time → promote-or-prune.
- `decisions/INDEX.md` drift, orphaned references.

## Hard safety rules
- Work ONLY on `dream/<date>`. Never commit/merge/push to `main`; never force-push,
  hard-reset, or `git clean` (a hook blocks these).
- NEVER edit Protected Files: `CLAUDE.md`, `RALPH.md`, `ralph.sh`, `.claude/**` (incl.
  this skill), `spec/**`, `openspec/specs/**`, any `APPROVED`. NEVER author/modify a
  change under `openspec/changes/` — Dreaming *reads* progress.md.
- **Conservative bias:** when a curation needs judgment you're unsure about, propose it
  in the report — don't apply it. A short clean PR + a "considered but deferred" list
  beats an over-eager diff.
- Timestamps from the real clock (`date` / `scripts/memlog.sh`), never estimated.

## Dream report format (= PR body)
```
# Dream — <YYYY-MM-DD>
Scope: since <last dream | repo start>. Reviewed: knowledge/ (<n> domains), log.md, <n> progress.md, lessons.md.

## Applied (in this PR)
- Promote: <domain> "<hypothesis>" → rule — confirmations <dates>
- Lesson: <title> — recurring evidence <refs>
- Knowledge: <domain>/<entry> — new hypothesis (1 confirmation)

## Proposed — your call, NOT applied
- <judgment-heavy suggestion + why deferred>

## Flags — report-only
- <stale hot.md pattern / seed domain / index drift>

## Reviewed, nothing to do
- <areas checked and found clean — proves coverage, not skipped>
```
Always fill "Reviewed, nothing to do" — silence must mean *reviewed and fine*, never *skipped*.

</supporting-info>
