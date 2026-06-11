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

(no lessons yet)
## 2026-06-11 — Protected files need explicit per-file authorization
- **Mistake:** Treated a generic "procedi" as sufficient authorization to edit protected files (.claude/settings.json, .claude/hooks/, ralph.sh).
- **Correction:** The permission classifier blocked the edits; explicit per-file confirmation (AskUserQuestion) was required before retrying.
- **Rule:** Before touching anything in the CLAUDE.md "Protected Files" list, name the exact files and obtain an explicit per-file OK in that same exchange.
