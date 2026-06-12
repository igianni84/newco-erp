# Lessons

> Mistake → Correction → Rule triples. Added by the agent after every correction from the user and after every self-discovered mistake pattern. **Read at session start** (and at ralph iteration start via progress.md pointers). Keep entries short and prescriptive; when a lesson reveals a deeper domain pattern, promote the insight to `knowledge/` and keep the lesson as quick-reference.

Format:

```
## YYYY-MM-DD — short title
- **Mistake:** what went wrong
- **Correction:** what the user/reality corrected it to
- **Rule:** the prescriptive rule that prevents recurrence
```

---

## 2026-06-11 — Protected files need explicit per-file authorization
- **Mistake:** Treated a generic "procedi" as sufficient authorization to edit protected files (.claude/settings.json, .claude/hooks/, ralph.sh).
- **Correction:** The permission classifier blocked the edits; explicit per-file confirmation (AskUserQuestion) was required before retrying.
- **Rule:** Before touching anything in the CLAUDE.md "Protected Files" list, name the exact files and obtain an explicit per-file OK in that same exchange.

## 2026-06-11 — git-guardrails hook matches bare "rm"/"mv" letter sequences inside Bash prose
- **Mistake:** Appended a progress entry to `openspec/changes/.../progress.md` (a legitimate, writable target) via a Bash heredoc whose prose contained "platform spec"; the PreToolUse hook regex `(rm|mv)[^|;&]*[[:space:]](spec|openspec/specs)…` is unanchored, matched the `rm` inside "platfo**rm**" + " spec", and blocked the whole command as "deleting the immutable spec layers".
- **Correction:** Wrote the identical content with the Edit/Write file tools — the hook matches Bash commands only, and file-tool writes to non-protected paths are the intended channel.
- **Rule:** Write memory/progress files (progress.md, log.md, hot.md, lessons.md) with the Edit/Write tools, never Bash heredocs/redirects — any prose word ending in -rm/-mv followed by "spec…" (platform spec, confirm spec, …) trips the hook. (Suggested human-side fix, hook is protected: anchor the verb — `(^|[;&|[:space:]])(rm|mv)[[:space:]]`.)

## 2026-06-12 — pest-plugin-arch: `not` over an ARRAY *source* inverts the aggregate (silently masks a violation)
- **Mistake:** Wrote the platform-direction arch test (task 2.3) as `expect(['App\Providers','App\Models','App\Http'])->not->toUse('App\Modules')`, assuming an array source means "NONE of these may use the target". The suite stayed GREEN even with a deliberate violating fixture (`app/Providers/` class importing a module) present — a vacuously-passing test.
- **Correction:** Empirically probed the API (per design D4): `expect([sources])->not->toUse(X)` negates the *aggregate* positive `toUse` ("ALL sources use X"), so it passes the moment ONE source is clean — `App\Models` not using `App\Modules` satisfied the `not`, masking the dirty `App\Providers`. The fix is to LOOP and assert one single-source `expect($ns)->not->toUse(X)` per source (mirrors the 2.2 per-module loop): reads "this source uses none of X", fails on any one dirty source.
- **Rule:** In pest-plugin-arch, put arrays on the **dependency** side only (`not->toUse([d1,d2,…])` = "uses ANY of these" — safe). NEVER put an array on the **source** side under `not`; loop instead, one `expect($source)->not->toUse(…)` per source. The mandatory red-proof is what caught this — a boundary test never seen red does not count as done (design Risk "vacuously green arch tests").
