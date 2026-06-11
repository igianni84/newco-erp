#!/usr/bin/env bash
# PreToolUse hook (Bash matcher): blocks destructive git operations.
# Applies to ALL sessions in this repo — including autonomous ralph iterations
# running with --dangerously-skip-permissions (hooks still fire there).
#
# Input: hook JSON on stdin. Exit 2 blocks the tool call (stderr is shown to
# the model); exit 0 allows it. Fails open on missing jq/input so it never
# bricks a session.

command -v jq >/dev/null 2>&1 || exit 0

INPUT="$(cat 2>/dev/null)" || exit 0
CMD="$(printf '%s' "$INPUT" | jq -r '.tool_input.command // empty' 2>/dev/null)" || exit 0
[ -z "$CMD" ] && exit 0

block() {
  echo "BLOCKED by git-guardrails: $1. This operation is destructive and not allowed in this repo. If genuinely needed, the human must run it manually in a terminal." >&2
  exit 2
}

# Force pushes (any variant, including --force-with-lease)
echo "$CMD" | grep -qE 'git[^|;&]*push[^|;&]*(--force|-f([[:space:]]|$))' && block "force push"

# History/worktree destruction
echo "$CMD" | grep -qE 'git[^|;&]*reset[^|;&]*--hard' && block "git reset --hard"
echo "$CMD" | grep -qE 'git[^|;&]*clean[^|;&]*-[a-zA-Z]*f' && block "git clean -f"
echo "$CMD" | grep -qE 'git[^|;&]*branch[^|;&]*(-D([[:space:]]|$)|--delete[[:space:]]+--force)' && block "force branch delete"
echo "$CMD" | grep -qE 'git[^|;&]*stash[^|;&]*(drop|clear)' && block "git stash drop/clear"
echo "$CMD" | grep -qE 'git[^|;&]*(filter-branch|filter-repo)' && block "history rewrite"
echo "$CMD" | grep -qE 'git[^|;&]*checkout[^|;&]*--[[:space:]]+\.' && block "git checkout -- . (discards all changes)"
echo "$CMD" | grep -qE 'git[^|;&]*restore[^|;&]*[[:space:]]\.([[:space:]]|$)' && echo "$CMD" | grep -qv -- '--staged' && block "git restore . (discards all changes)"

# Protect the immutable layers from shell-level deletion/overwrite
echo "$CMD" | grep -qE '(rm|mv)[^|;&]*[[:space:]](\./)?(spec|openspec/specs)(/|[[:space:]]|$)' && block "deleting/moving the immutable spec layers"

# Write-verbs aimed at the immutable layers (read access stays free)
echo "$CMD" | grep -qE '>>?[[:space:]]*(\./)?(spec|openspec/specs)/' && block "shell redirect into the immutable spec layers"
echo "$CMD" | grep -qE '(^|[;&|[:space:]])(sed[[:space:]]+-[a-zA-Z]*i|tee|cp|touch|install)[[:space:]]+([^|;&]*[[:space:]])?(\./)?(spec|openspec/specs)/' && block "writing into the immutable spec layers (read them with cat/Read instead)"

# -----------------------------------------------------------------------------
# Loop-only rules — active when the ralph loop runs (RALPH_LOOP=1 exported by
# ralph.sh; permission_mode=bypassPermissions catches other headless runs).
# These steps belong to the human operator, not the loop.
# -----------------------------------------------------------------------------
PERM="$(printf '%s' "$INPUT" | jq -r '.permission_mode // empty' 2>/dev/null)"
LOOP=0
[ "${RALPH_LOOP:-0}" = "1" ] && LOOP=1
[ "$PERM" = "bypassPermissions" ] && LOOP=1

if [ "$LOOP" -eq 1 ]; then
  block_loop() {
    echo "BLOCKED by git-guardrails (loop mode): $1. This step belongs to the human operator (GUIDE.md). End the iteration normally and record state in progress.md." >&2
    exit 2
  }
  echo "$CMD" | grep -qE '(^|[;&|[:space:]])git[^|;&]*[[:space:]]push([[:space:]]|$)' && block_loop "git push — the loop commits locally; humans push after review"
  echo "$CMD" | grep -qE '(^|[;&|[:space:]])(touch|cp|mv|tee|install)[[:space:]][^|;&]*APPROVED' && block_loop "creating/altering an APPROVED marker — the human's signature"
  echo "$CMD" | grep -qE '>>?[[:space:]]*[^|;&[:space:]]*APPROVED' && block_loop "writing an APPROVED marker — the human's signature"
  echo "$CMD" | grep -qE '(^|[;&|[:space:]])openspec[^|;&]*[[:space:]]archive([[:space:]]|$)' && block_loop "openspec archive — humans archive after review (GUIDE.md §2.7)"
fi

exit 0
