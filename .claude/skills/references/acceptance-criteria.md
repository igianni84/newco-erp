# Acceptance Criteria Standards

Shared standards for mandatory acceptance criteria. Referenced by `/prd-tech` (implementation tasks) and `/ralph` (user stories).

---

## Mandatory Criteria by Type

**ALL stories/tasks must include:**
```
"Typecheck passes"
"Tests pass"
```

Tests are NEVER optional. Every story/task must have at least one test.

**Backend stories/tasks (migrations, services, models, controllers) must also include:**
```
"Unit/Feature tests cover happy path and at least one failure case"
```

**UI stories/tasks (Filament, Inertia, frontend components) must also include:**
```
"Verify in browser using dev-browser skill"
```

Frontend stories are NOT complete until visually verified.

---

## Criteria Quality Rules

Each criterion must be something that can be **checked**, not something vague.

### Good criteria (verifiable):
- "Add `status` column to tasks table with default 'pending'"
- "Filter dropdown has options: All, Active, Completed"
- "Clicking delete shows confirmation dialog"

### Bad criteria (vague):
- "Works correctly"
- "User can do X easily"
- "Good UX"
- "Handles edge cases"

### Include test hints

When writing acceptance criteria, include hints about WHAT to test:

**Good:**
```json
"acceptanceCriteria": [
  "Service returns calculated margin for valid wine",
  "Service throws exception for wine without Liv-ex price",
  "Unit tests cover both cases",
  "Typecheck passes",
  "Tests pass"
]
```

**Bad:**
```json
"acceptanceCriteria": [
  "Service works correctly",
  "Typecheck passes"
]
```

The more specific the criteria, the better the tests will be.
