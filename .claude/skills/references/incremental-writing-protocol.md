# Incremental Writing Protocol

Shared protocol for progressively writing large documents. Used by PRD skills to prevent data loss from crashes or timeouts during generation.

---

## The Problem

A full PRD for a non-trivial module can be thousands of lines. If you generate the whole document in a single `Write` call, the tool call stays open while you stream tokens. During that time the user sees nothing on disk, and a crash, timeout, or context exhaustion loses the entire draft.

## The Fix

Write the file progressively. Use a sentinel marker as a movable "insertion point" that travels down the file as you append sections with `Edit`. The file exists on disk after the very first call and grows visibly with each subsequent one.

**Why it works:**
- **Visibility** — the file appears on disk after step 1, so the user can watch it grow
- **Resilience** — every `Edit` is a checkpoint; a failure loses at most the in-flight batch
- **Bounded per-turn token budget** — no single tool call has to emit the entire document
- **Natural resumption point** — the sentinel tells you exactly where to continue if a session is interrupted

**Hard rule: never generate the full PRD in a single `Write` call.**

---

## Protocol Steps

### Step 1 — Initial `Write`

Create the file with the document title + Section 1 + the primary sentinel:

```markdown
# [Document Title]

## 1. [First Section]
[content]

<!-- PRD_NEXT_SECTION -->
```

After this call the file exists on disk. Print a progress line so the user sees the checkpoint.

### Steps 2..N — Append each section with `Edit`

For each remaining section, use `Edit` to replace `<!-- PRD_NEXT_SECTION -->` with the new section's content followed by the sentinel again:

- `old_string`: `<!-- PRD_NEXT_SECTION -->`
- `new_string`:
  ```
  ## N. [Section Title]
  [content]

  <!-- PRD_NEXT_SECTION -->
  ```

The sentinel walks down to the bottom of the file with each `Edit`.

### Final Step — Remove the sentinel

After the last section, do one final `Edit` to delete the trailing `<!-- PRD_NEXT_SECTION -->` line. The document is now complete and clean.

---

## Batching Large Sections

Some sections (User Stories, Business Rules, Implementation Tasks) can be very large. A single `Edit` that emits 60 items recreates the problem this protocol prevents.

Use the secondary sentinel `<!-- PRD_MORE_ITEMS -->` for internal batching within a section:

**First `Edit` of the section:**

- `old_string`: `<!-- PRD_NEXT_SECTION -->`
- `new_string`:
  ```
  ## N. [Section Title]

  [first batch of items]

  <!-- PRD_MORE_ITEMS -->

  <!-- PRD_NEXT_SECTION -->
  ```

**Subsequent batches:**

- `old_string`: `<!-- PRD_MORE_ITEMS -->`
- `new_string`:
  ```
  [next batch of items]

  <!-- PRD_MORE_ITEMS -->
  ```

**Last batch:**

- `old_string`: `<!-- PRD_MORE_ITEMS -->`
- `new_string`: `[final batch, no trailing sentinel]`

After closing, `<!-- PRD_NEXT_SECTION -->` remains in place for the next section.

### Batch Size Ceilings

| Content type | Max per Edit |
|---|---|
| User Stories | 20 |
| Business Rules / Functional Requirements | 20 |
| Implementation Tasks | 10 (more detailed, so smaller batches) |

If a section has fewer items than the ceiling, write them in one `Edit`.

---

## Progress Feedback

After each `Write` or `Edit`, print one concise progress line:

```
Section 1/N written: [Section Name]
Section 2/N written: [Section Name]
Section 3/N written: [Section Name] — batch 1 (20 items so far)
Section 3/N written: [Section Name] — batch 2 complete (35 items total)
...
Section N/N written: [Section Name]
Final cleanup: sentinel removed — PRD complete at [path]
```

The denominator `N` is the total sections you plan to write. For batched sections, include a running total of items placed so the user can see the section grow.

---

## Pre-Save Checklist (Common)

Before saving any PRD, verify these shared items:

- [ ] All sentinel markers (`<!-- PRD_NEXT_SECTION -->` and `<!-- PRD_MORE_ITEMS -->`) are fully removed from the final file
- [ ] User reviewed and approved the final version
- [ ] Saved to the correct `tasks/` path with the expected filename

Each skill adds its own domain-specific checklist items.
