# Interview & PRD Writing Methodology

Shared methodology for PRD skills. Each skill reads this file for generic patterns and adds its own domain-specific interview areas, confidence thresholds, and output structure.

---

## Phase 0: Context Gathering

Before asking the user anything, build your understanding:

1. **Check project context** — Read `knowledge/` index and any relevant domain folders for existing patterns, rules, and conventions
2. **Check existing PRDs** — Scan `tasks/prd-*.md` for format conventions and related features
3. **Check CLAUDE.md** — Review canonical terminology, key invariants, and project constraints

Each skill may add additional context steps (e.g., prd-tech explores the codebase, prd-product checks domain vocabulary).

---

## Import Mode: Gap Analysis

When the user provides an existing document:

1. Read it thoroughly
2. Produce a **Gap Analysis** before starting the interview — share it with the user:
   - What's already clear and well-defined
   - Sections that are vague or hand-wavy ("handle errors gracefully" = vague)
   - Missing acceptance criteria
   - Unstated assumptions you detected
   - Contradictions or inconsistencies
   - Missing edge cases
   - Areas where scope is ambiguous
3. Use the gap analysis to focus the interview — don't re-ask what's already clear

---

## Interview Rules

**No fixed question limit.** Ask as many rounds as needed. Each round should have 3-7 focused questions. Continue until every area reaches the skill's confidence threshold.

**Zero tolerance for ambiguity.** If something could be interpreted two ways, it's unclear. Ask. "I could assume X, but I want to confirm" is always the right move.

**Dig past the surface.** When the user says "I want feature X", understand *why* they want it. The why often reveals that the real need is slightly different from what was stated. This isn't about second-guessing — it's about uncovering the actual requirement behind the stated one.

---

## Question Style

Mix formats based on what you need to learn:

- **Open-ended** for exploration: "Walk me through what happens when a user tries to..."
- **Multiple choice with lettered options** for quick decisions: "A. Option X / B. Option Y / C. Other"
- **Scenario-based** for edge cases: "What should happen if X fails halfway through?"
- **Challenge questions** for assumptions: "You mentioned X — but given constraint Y, have you considered...?"
- **Clarification** for vague statements: "You said 'it should handle this gracefully' — what does that mean concretely?"

Use lettered options when there are discrete choices so the user can respond quickly (e.g., "1A, 2C, 3B"). But don't force multiple choice when an open answer is more appropriate — sometimes the right answer isn't on the list.

---

## Confidence Tracking

After each interview round, show the user where you stand using progress bars:

```
Confidence Assessment:
- Area 1:     ████████░░ 80% — need to clarify X
- Area 2:     ██████░░░░ 60% — workflow Y still unclear
- Area 3:     ████░░░░░░ 40% — not explored yet
```

The confidence threshold and specific areas vary by skill:
- **prd-product:** 95% across all 7 business areas before generating
- **prd-tech:** 90% across all relevant technical areas before generating

If the user wants to proceed below threshold, clearly warn about what's still unclear and the risks of proceeding. Let them decide, but make the trade-off visible.

---

## Anti-Patterns

- **Don't assume.** If something is unclear, ask. Never silently decide.
- **Don't accept vague answers.** "It should work well" → "What does 'well' mean concretely?"
- **Don't skip edge cases.** The person implementing needs to know what happens in unusual situations.
- **Don't front-load all questions.** Build on answers. If the user says "users can place orders", drill into the order flow next.
- **Don't repeat what you know.** If importing an existing document, focus on gaps.
- **Don't be satisfied too easily.** If an answer raises new questions, follow up. Depth over breadth.

---

## Review & Refinement

After generating the PRD, walk through it with the user:

1. **Highlight key decisions** — "Based on your answers, I structured X this way. Does this match your intent?"
2. **Flag lower-confidence areas** — "I'm least confident about section Y — let me know if this captures what you meant."
3. **Invite targeted feedback** — "Anything that feels off, missing, or not quite right?"

Iterate until the user explicitly confirms it's ready.

---

## Writing Guidelines

The PRD reader may be a non-technical stakeholder, a developer, or an AI agent. Write accordingly:

- Be explicit and unambiguous — if it can be misread, it will be
- Use domain vocabulary from the Canonical Terminology table in CLAUDE.md (if defined)
- Number requirements and stories for easy reference
- Use concrete examples for complex rules (worked examples showing input → expected output)
- When a rule has exceptions, list them explicitly
