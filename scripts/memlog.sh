#!/usr/bin/env bash
# memlog.sh — append ONE well-formed entry to log.md with a real-clock timestamp.
#
# Why this exists (root-cause fix, 2026-06-13 audit): log.md timestamps were
# model-estimated and drifted up to hours (false chronology), and the {outcome}
# field absorbed multi-KB narratives (104KB in 63 giant single lines). This
# helper makes both impossible AT THE SOURCE:
#   - the timestamp comes from `date`, never passed in, never estimated;
#   - an over-long outcome is REJECTED (detail belongs in the change's
#     progress.md; the ledger stays a one-line summary).
#
# Usage:   scripts/memlog.sh "<op>" "<target>" "<outcome>"
# Example: scripts/memlog.sh ralph "module-k 2.1" "green | 3 files"
#
# Works from any cwd (locates log.md via git). Exit 0 on success, 1 on misuse.
set -euo pipefail

MAX_OUTCOME=280

die() { echo "memlog: $1" >&2; exit 1; }

[ "$#" -eq 3 ] || die 'usage: scripts/memlog.sh "<op>" "<target>" "<outcome>"'
op="$1"; target="$2"; outcome="$3"

for pair in "op:$op" "target:$target" "outcome:$outcome"; do
  name="${pair%%:*}"; val="${pair#*:}"
  [ -n "$val" ] || die "$name must not be empty"
  case "$val" in
    *$'\n'*) die "$name must be a single line (no newlines) — the ledger is one line per entry" ;;
  esac
done

if [ "${#outcome}" -gt "$MAX_OUTCOME" ]; then
  die "outcome is ${#outcome} chars (max $MAX_OUTCOME). Keep the ledger to a one-line summary; put the narrative in the change's progress.md."
fi

root="$(git rev-parse --show-toplevel 2>/dev/null)" || die "not inside a git repo"
log="$root/log.md"
[ -f "$log" ] || die "log.md not found at $log"

ts="$(date '+%Y-%m-%d %H:%M')"
printf '\n## [%s] %s | %s | %s\n' "$ts" "$op" "$target" "$outcome" >> "$log"
echo "memlog: appended [$ts] $op | $target"
