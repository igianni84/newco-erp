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

exit 0
