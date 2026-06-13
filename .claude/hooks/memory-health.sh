#!/usr/bin/env bash
# memory-health.sh — Stop hook. Non-blocking second-brain health check.
#
# Root-cause backstop (2026-06-13 audit): the memory files drifted out of their
# documented shape (hot.md bloated past its word charter; log.md ballooned to
# 104KB of giant single-line entries) because nothing validated the writes.
# This hook WARNS (never blocks — always exit 0) so the next session self-corrects.
# It complements scripts/memlog.sh (which enforces log entries at write time).
#
# Checks (warn-only):
#   - hot.md  > 550 words             (charter: ~500-word cache, not a journal)
#   - log.md  > 200 KB                (rotate to log-archive-YYYY-H{1,2}.md)
#   - any log.md line > 500 chars     (an outcome absorbed a narrative)
#
# Output: a single MEMORY_HEALTH line on stdout, shown to the model at Stop.

root="${CLAUDE_PROJECT_DIR:-$PWD}"
warn=""

hot="$root/hot.md"
if [ -f "$hot" ]; then
  words="$(wc -w < "$hot" 2>/dev/null | tr -d ' ')"
  if [ -n "$words" ] && [ "$words" -gt 550 ] 2>/dev/null; then
    warn="${warn} hot.md is ${words} words (>550; charter ~500 — trim on next overwrite)."
  fi
fi

log="$root/log.md"
if [ -f "$log" ]; then
  bytes="$(wc -c < "$log" 2>/dev/null | tr -d ' ')"
  if [ -n "$bytes" ] && [ "$bytes" -gt 204800 ] 2>/dev/null; then
    warn="${warn} log.md is $((bytes / 1024))KB (>200KB — rotate to log-archive-$(date +%Y)-H?.md and start a fresh log.md)."
  fi
  longest="$(awk '{ if (length > m) m = length } END { print m + 0 }' "$log" 2>/dev/null)"
  if [ -n "$longest" ] && [ "$longest" -gt 500 ] 2>/dev/null; then
    warn="${warn} log.md has a ${longest}-char line (>500 — keep entries one-line; narrative goes to progress.md)."
  fi
fi

if [ -n "$warn" ]; then
  echo "MEMORY_HEALTH:${warn} New log entries: use scripts/memlog.sh (real timestamp + length cap)."
fi
exit 0
