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
