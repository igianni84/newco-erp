#!/usr/bin/env bash
# spec-staleness.sh — SessionStart hook. Non-blocking canon-drift warning.
#
# Root cause it exists for (ADR 2026-07-10-spec-vendoring-cadence-and-staleness-gate):
# the distance between our vendored spec/ and canon was INVISIBLE, so nobody could tell
# a decision needed making. It was rediscovered by accident, three times, each time from
# a memory artefact asserting the state of a remote it never queried. This hook asks the
# remote, once per session, and puts the number on the screen.
#
# WARNS, NEVER BLOCKS — always exit 0. A stale spec/ is a fact to know at session start,
# not a reason to refuse the session. The authoring gate lives in /spec-to-change.
#
# On an unreachable canon (offline, lost credentials) it reports "unreachable", which is
# NOT a staleness verdict. See scripts/spec-staleness.sh for why that distinction is the
# whole point: a gate that cannot reach its oracle must refuse to answer, not guess.
#
# Output: SPEC_STALENESS line(s) on stdout, injected as session context.

root="${CLAUDE_PROJECT_DIR:-$PWD}"
detector="$root/scripts/spec-staleness.sh"

[ -x "$detector" ] || exit 0

# Keep session start snappy: a hung network call must never delay a session.
out="$(SPEC_STALENESS_TIMEOUT=10 "$detector" --quiet 2>/dev/null)"
rc=$?

case "$rc" in
  0)
    # Say so out loud. A silent detector is indistinguishable from a broken one.
    echo "$out"
    ;;
  1)
    echo "$out"
    echo "SPEC_STALENESS_WARNING: spec/ lags canon. Authoring a new change against this snapshot is gated (/spec-to-change checks first). Refresh with scripts/sync-spec.sh — it moves the ../documentation clone's HEAD, so confirm with the user before running it. The refresh is its own code-free commit (spec/ + spec.lock only), followed by a triage pass over the diff."
    ;;
  *)
    echo "$out"
    echo "SPEC_STALENESS_WARNING: the canon could not be reached, so NO staleness verdict was rendered. This is 'unknown', not 'fresh' and not 'stale'. Do not infer that spec/ is current."
    ;;
esac

exit 0
