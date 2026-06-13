#!/usr/bin/env bash
# =============================================================================
# Ralph — autonomous agent loop over OpenSpec changes (NewCo ERP edition)
#
# Each iteration launches a fresh Claude Code instance that implements exactly
# ONE unchecked task from the active change's tasks.md, runs the quality loop,
# commits, and persists memory (progress.md, log.md, hot.md). State between
# iterations lives ONLY in files + git.
#
# Usage:
#   ./ralph.sh [--change <name>] [--force] [max_iterations]
#
#   --change <name>   Run a specific change from openspec/changes/<name>/
#                     (default: alphabetically-first APPROVED change with
#                      unchecked tasks)
#   --force           Skip the APPROVED-file gate (use only for smoke tests)
#   max_iterations    Default: 10
#
# Exit codes: 0 change complete · 1 max iterations reached · 2 preflight error
#             3 agent requested human help · 4 stalled (3 iterations, no progress)
#             5 integrity violation (a protected layer was modified by the loop)
#
# Env: CLAUDE_FLAGS — extra flags appended to the claude invocation.
# Env: RALPH_MODEL  — model per iteration (default: claude-opus-4-8[1m] — Opus 4.8, 1M context).
# Env: RALPH_EFFORT — reasoning effort per iteration: low|medium|high|xhigh|max (default: max).
# Prerequisites: claude CLI, jq, openspec (or npx fallback), git.
# =============================================================================

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

MAX_ITERATIONS=10
CHANGE=""
FORCE=0

while [[ $# -gt 0 ]]; do
  case $1 in
    --change)   CHANGE="$2"; shift 2 ;;
    --change=*) CHANGE="${1#*=}"; shift ;;
    --force)    FORCE=1; shift ;;
    *)
      if [[ "$1" =~ ^[0-9]+$ ]]; then MAX_ITERATIONS="$1"; fi
      shift ;;
  esac
done

CHANGES_DIR="openspec/changes"

# ------------------------------------
# Preflight
# ------------------------------------
fail() { echo "Error: $1" >&2; exit 2; }

command -v claude >/dev/null || fail "claude CLI not found"
command -v jq >/dev/null || fail "jq not found"
git rev-parse --git-dir >/dev/null 2>&1 || fail "not a git repository"

if command -v openspec >/dev/null; then
  OPENSPEC="openspec"
else
  OPENSPEC="npx -y @fission-ai/openspec"
  echo "Note: openspec CLI not installed; falling back to npx (slower)."
fi

# ------------------------------------
# Change selection
# Auto mode: alphabetically-first non-archive change that has an APPROVED
# marker AND at least one unchecked task.
# ------------------------------------
if [ -z "$CHANGE" ]; then
  for dir in "$CHANGES_DIR"/*/; do
    name="$(basename "$dir")"
    [ "$name" = "archive" ] && continue
    [ -f "$dir/APPROVED" ] || continue
    [ -f "$dir/tasks.md" ] || continue
    if grep -qE '^[[:space:]]*- \[ \]' "$dir/tasks.md"; then
      CHANGE="$name"
      break
    fi
  done
  [ -z "$CHANGE" ] && fail "no approved change with unchecked tasks found (create APPROVED marker or pass --change)"
fi

CHANGE_DIR="$CHANGES_DIR/$CHANGE"
TASKS_FILE="$CHANGE_DIR/tasks.md"
PROGRESS_FILE="$CHANGE_DIR/progress.md"
LAST_OUTPUT_FILE="$CHANGE_DIR/.last-output"

[ -d "$CHANGE_DIR" ] || fail "change not found: $CHANGE_DIR"
[ -f "$TASKS_FILE" ] || fail "tasks.md missing in $CHANGE_DIR (run /spec-to-change first)"

if [ ! -f "$CHANGE_DIR/APPROVED" ] && [ "$FORCE" -ne 1 ]; then
  fail "change '$CHANGE' is not approved. Review it, then: touch $CHANGE_DIR/APPROVED  (or use --force)"
fi

if [ -n "$(git status --porcelain)" ]; then
  echo "Warning: working tree is dirty. The loop commits everything it touches; consider starting clean."
fi

# ------------------------------------
# Branch: each change runs on ralph/<change>
# ------------------------------------
BRANCH="ralph/$CHANGE"
CURRENT_BRANCH="$(git branch --show-current)"
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
  if git rev-parse --verify "$BRANCH" >/dev/null 2>&1; then
    git checkout "$BRANCH" || fail "could not checkout $BRANCH"
  else
    git checkout -b "$BRANCH" || fail "could not create $BRANCH"
  fi
fi

# ------------------------------------
# Loop identity + immutable-layer baseline
# ------------------------------------
# Exported so the .claude/hooks guardrails apply their loop-only rules (no
# push, no APPROVED creation, no machine-file edits) even though the agent
# runs with --dangerously-skip-permissions.
export RALPH_LOOP=1
export RALPH_ACTIVE_CHANGE="$CHANGE"

# Preflight: commit the active change's own artifacts BEFORE baselining.
# proposal/design/tasks/delta-specs and the human APPROVED marker are the
# already-approved inputs to this loop. If any are still untracked at launch,
# iteration 1's `git add -A` sweeps them into a task commit, and the integrity
# gate below then flags the swept-in APPROVED path as a protected-layer change
# — benign, but it halts the loop on exit 5 (see lessons.md 2026-06-12 /
# 2026-06-13, two occurrences of exactly this). Staging is scoped strictly to
# $CHANGE_DIR (the change's own folder; .last-output is gitignored); the
# immutable spec/ and openspec/specs/ layers are never auto-committed here.
if [ -n "$(git status --porcelain -- "$CHANGE_DIR" 2>/dev/null)" ]; then
  git add -- "$CHANGE_DIR" || fail "preflight: could not stage change artifacts under $CHANGE_DIR"
  git commit -q -m "approve: $CHANGE (ralph preflight auto-stage of change artifacts)" \
    || fail "preflight: could not commit change artifacts under $CHANGE_DIR"
  echo "Preflight: committed untracked/modified artifacts under $CHANGE_DIR so the integrity baseline includes them."
fi

BASELINE_SHA="$(git rev-parse HEAD)"

if [ -n "$(git status --porcelain -- spec/ openspec/specs/ 2>/dev/null)" ]; then
  fail "spec/ or openspec/specs/ has uncommitted modifications — these layers are immutable; resolve before looping"
fi

# Protected layers the loop must never touch (RALPH.md "NEVER modify"):
# checks commits since loop start + the spec-layer working tree.
check_integrity() {
  INTEGRITY_VIOLATIONS="$(
    {
      git diff --name-only "$BASELINE_SHA" HEAD -- spec/ openspec/specs/ CLAUDE.md RALPH.md ralph.sh .claude/ 2>/dev/null
      git diff --name-only "$BASELINE_SHA" HEAD 2>/dev/null | grep -E '(^|/)APPROVED$'
      git status --porcelain -- spec/ openspec/specs/ 2>/dev/null | cut -c4-
    } | sed '/^$/d' | sort -u
  )"
  [ -z "$INTEGRITY_VIOLATIONS" ]
}

# ------------------------------------
# Progress file init
# ------------------------------------
if [ ! -f "$PROGRESS_FILE" ]; then
  {
    echo "# Progress — $CHANGE"
    echo ""
    echo "## Codebase Patterns"
    echo "(consolidated reusable patterns — read first each iteration)"
    echo ""
    echo "---"
  } > "$PROGRESS_FILE"
fi

count_tasks() { grep -cE "^[[:space:]]*- \[$1\]" "$TASKS_FILE" 2>/dev/null || true; }

DONE_BEFORE="$(count_tasks x)"
TOTAL="$(( $(count_tasks ' ') + DONE_BEFORE ))"

echo "Starting Ralph — change: $CHANGE — branch: $BRANCH — progress: $DONE_BEFORE/$TOTAL — max iterations: $MAX_ITERATIONS"

# =============================================================================
# Main loop
# =============================================================================
STALL_COUNT=0

for i in $(seq 1 "$MAX_ITERATIONS"); do
  echo ""
  echo "==============================================================="
  echo "  Ralph Iteration $i of $MAX_ITERATIONS — $CHANGE ($(count_tasks x)/$TOTAL done)"
  echo "==============================================================="

  PREV_DONE="$(count_tasks x)"

  RUN_CONTEXT="## Current Run Context
- Active change: $CHANGE
- Tasks file: $TASKS_FILE
- Progress file: $PROGRESS_FILE
- Last output file: $LAST_OUTPUT_FILE
- Iteration: $i of $MAX_ITERATIONS
- Tasks done: $PREV_DONE of $TOTAL
"

  # Fresh instance, autonomous mode. Project hooks (.claude/settings.json)
  # still apply: hot.md injection, git guardrails.
  OUTPUT=$({ echo "$RUN_CONTEXT"; cat "$SCRIPT_DIR/RALPH.md"; } | claude --dangerously-skip-permissions --print --model "${RALPH_MODEL:-claude-opus-4-8[1m]}" --effort "${RALPH_EFFORT:-max}" ${CLAUDE_FLAGS:-} 2>&1 | tee /dev/stderr) || true

  # ------------------------------------
  # Immutable-layer integrity gate
  # ------------------------------------
  if ! check_integrity; then
    echo "$OUTPUT" | tail -100 > "$LAST_OUTPUT_FILE"
    echo ""
    echo "INTEGRITY VIOLATION at iteration $i — protected layer modified since loop start:"
    printf '%s\n' "$INTEGRITY_VIOLATIONS" | sed 's/^/    /'
    echo "Inspect:  git log --oneline $BASELINE_SHA..HEAD ; git status"
    echo "Recover:  git revert the offending commit(s) on $BRANCH, note the cause in lessons.md, then re-run."
    exit 5
  fi

  # ------------------------------------
  # Stop tokens
  # ------------------------------------
  if echo "$OUTPUT" | grep -q "<promise>CHANGE_COMPLETE</promise>"; then
    echo ""
    echo "Change '$CHANGE' complete at iteration $i ($(count_tasks x)/$TOTAL tasks)."
    if [ -d "$CHANGE_DIR/specs" ]; then
      $OPENSPEC validate "$CHANGE" --strict || echo "Warning: strict validation reported issues — review before archiving."
    fi
    rm -f "$LAST_OUTPUT_FILE"
    echo ""
    echo "Next steps (human) — full ritual in GUIDE.md §2.7:"
    echo "  1. Review the branch:    git log --oneline main..$BRANCH ; git diff main...$BRANCH"
    echo "  2. Merge to main:        git checkout main && git merge --no-ff $BRANCH"
    echo "  3. Semantic check:       run the verification prompt from GUIDE.md §2.7 in Claude Code"
    echo "  4. Archive the change:   $OPENSPEC archive $CHANGE --yes"
    exit 0
  fi

  if echo "$OUTPUT" | grep -q "<promise>HUMAN_NEEDED</promise>"; then
    echo "$OUTPUT" | tail -100 > "$LAST_OUTPUT_FILE"
    echo ""
    echo "Agent requested human help at iteration $i. See $PROGRESS_FILE and $LAST_OUTPUT_FILE."
    exit 3
  fi

  # ------------------------------------
  # Failure context + stall detection
  # ------------------------------------
  echo "$OUTPUT" | tail -100 > "$LAST_OUTPUT_FILE"

  NEW_DONE="$(count_tasks x)"
  if [ "$NEW_DONE" -gt "$PREV_DONE" ]; then
    STALL_COUNT=0
  else
    STALL_COUNT=$((STALL_COUNT + 1))
    echo "No task completed this iteration (stall $STALL_COUNT/3)."
    if [ "$STALL_COUNT" -ge 3 ]; then
      echo ""
      echo "Stalled: 3 consecutive iterations without progress on '$CHANGE'."
      echo "Read the failure notes: $PROGRESS_FILE and $TASKS_FILE (look for '⚠ FAILED')."
      exit 4
    fi
  fi

  echo "Progress: $NEW_DONE/$TOTAL tasks done. Continuing..."
  sleep 2
done

echo ""
echo "Reached max iterations ($MAX_ITERATIONS) — $(count_tasks x)/$TOTAL tasks done on '$CHANGE'."
echo "Check $PROGRESS_FILE for status, then re-run: ./ralph.sh --change $CHANGE"
exit 1
