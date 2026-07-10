#!/usr/bin/env bash
# PreToolUse hook (Edit|Write|NotebookEdit matcher): blocks edits to protected paths.
# Applies to ALL sessions in this repo — including autonomous ralph iterations
# running with --dangerously-skip-permissions (hooks still fire there).
#
# Tier 1 — blocked in EVERY mode (no legitimate Claude-edit path exists):
#   spec/**            never hand-edited; only scripts/sync-spec.sh writes it, via
#                      rsync in Bash (CLAUDE.md invariant 11). "Never edit" is not
#                      "never changes": spec/ chases canon by deliberate refresh.
#   openspec/specs/**  generated truth — changes only via `openspec archive` (Bash)
#   **/APPROVED        the human's signature; on explicit approval the marker is
#                      created via Bash `touch` (GUIDE.md §2.5), never via Write
#
# Tier 2 — blocked only in loop mode (RALPH_LOOP=1, exported by ralph.sh, or
# permission_mode=bypassPermissions for any other headless run):
#   CLAUDE.md, RALPH.md, ralph.sh, .claude/** (except .claude/memory/**)
#   openspec/changes/<X>/** for any X other than $RALPH_ACTIVE_CHANGE
# Interactive sessions keep tier-2 files editable: there the permission prompt
# and the "only on explicit user request" rule (CLAUDE.md) are the gate.
#
# Input: hook JSON on stdin. Exit 2 blocks the tool call (stderr is shown to
# the model); exit 0 allows it. Fails open on missing jq/input so it never
# bricks a session.

command -v jq >/dev/null 2>&1 || exit 0

INPUT="$(cat 2>/dev/null)" || exit 0
[ -n "$INPUT" ] || exit 0

FILE="$(printf '%s' "$INPUT" | jq -r '.tool_input.file_path // .tool_input.notebook_path // empty' 2>/dev/null)"
[ -n "$FILE" ] || exit 0

CWD="$(printf '%s' "$INPUT" | jq -r '.cwd // empty' 2>/dev/null)"
PERM="$(printf '%s' "$INPUT" | jq -r '.permission_mode // empty' 2>/dev/null)"
[ -n "$CWD" ] || CWD="$PWD"

deny() {
  echo "BLOCKED by protected-paths hook: $1" >&2
  exit 2
}

# --- normalize to a repo-relative path ---------------------------------------
case "$FILE" in
  /*) ABS="$FILE" ;;
  *)  ABS="$CWD/$FILE" ;;
esac
D="$(dirname "$ABS")"
if [ -d "$D" ]; then
  ABS="$(cd "$D" 2>/dev/null && pwd -P)/$(basename "$ABS")"
fi

REPO_ROOT="$(git -C "$CWD" rev-parse --show-toplevel 2>/dev/null)" || REPO_ROOT="$CWD"
case "$ABS" in
  "$REPO_ROOT"/*) REL="${ABS#"$REPO_ROOT"/}" ;;
  *) exit 0 ;;  # outside this repo — not this hook's concern
esac

# --- tier 1: immutable in every mode ------------------------------------------
case "$REL" in
  spec/*)
    deny "spec/** is never hand-edited (CLAUDE.md invariant 11). It is a vendored mirror of canon: only 'scripts/sync-spec.sh' writes it, as its own code-free commit. To pick up a canon change, refresh — do not edit." ;;
  openspec/specs/*)
    deny "openspec/specs/** is generated truth — it changes only via 'openspec archive <change>' run in Bash." ;;
esac
case "$(basename "$REL")" in
  APPROVED)
    deny "APPROVED markers are the human's signature. On explicit human approval, create it via Bash: touch openspec/changes/<name>/APPROVED." ;;
esac

# --- tier 2: the machine itself — loop mode only -------------------------------
LOOP=0
[ "${RALPH_LOOP:-0}" = "1" ] && LOOP=1
[ "$PERM" = "bypassPermissions" ] && LOOP=1
[ "$LOOP" -eq 1 ] || exit 0

case "$REL" in
  .claude/memory/*) ;;  # team memory stays writable in every mode
  CLAUDE.md|RALPH.md|ralph.sh|.claude/*)
    deny "$REL is part of the machine — the loop never modifies CLAUDE.md, RALPH.md, ralph.sh or .claude/** (RALPH.md 'NEVER modify'). Record the need in progress.md and end the iteration." ;;
esac

if [ -n "${RALPH_ACTIVE_CHANGE:-}" ]; then
  case "$REL" in
    openspec/changes/*)
      CH="${REL#openspec/changes/}"
      CH="${CH%%/*}"
      if [ "$CH" != "$RALPH_ACTIVE_CHANGE" ]; then
        deny "openspec/changes/$CH is not the active change ($RALPH_ACTIVE_CHANGE) — the loop works on ONE change only (RALPH.md)."
      fi ;;
  esac
fi

exit 0
