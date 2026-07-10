#!/usr/bin/env bash
#
# spec-staleness.sh — how far is the vendored spec/ behind canon?
#
# Compares spec.lock:source_sha against the tip of the canon branch by ASKING THE
# REMOTE — never a memory, never a document, never this script's own comments.
# ADR: decisions/2026-07-10-spec-vendoring-cadence-and-staleness-gate.md
# ("a baseline you remember is a baseline that lies").
#
# ── FAIL-CLOSED ───────────────────────────────────────────────────────────────
# If the canon cannot be reached, or the answer does not look like a commit sha,
# this script exits 2 WITHOUT a verdict. A gate that cannot reach its oracle must
# REFUSE TO ANSWER, not guess.
#
# The first detector drafted for the ADR ran `git ls-remote` against the canon
# URL directly, without credentials, got an empty string back, compared "" to the
# pin, and cheerfully reported STALE — while perfectly in sync. Two distinct
# empty-answer modes exist, and NEITHER may become a verdict:
#
#   unreachable remote        -> git exits non-zero, stdout empty
#   reachable, ref not found  -> git exits ZERO,     stdout empty
#
# The second is why `rc == 0` alone is not a guard. We additionally require the
# answer to be a 40-hex sha.
#
# ── WHY THROUGH THE CLONE ─────────────────────────────────────────────────────
# The canon is a PRIVATE repo. `git ls-remote https://github.com/c-mless/…` from
# this repo is unauthenticated and fails ("Repository not found"). The clone at
# ../documentation carries the credentials on its `cmless` remote. Always query
# through the clone; never the URL.
#
# ── SIDE EFFECTS ──────────────────────────────────────────────────────────────
# Read-only by default: `ls-remote` mutates nothing. `--fetch` additionally
# updates the clone's remote-tracking refs (NOT its HEAD, NOT its worktree) so a
# commit distance can be computed when the canon commit is not yet local.
# `scripts/sync-spec.sh` is the ONLY thing that moves the clone's HEAD.
#
# ── EXIT CODES (the contract consumers rely on) ───────────────────────────────
#   0  FRESH    spec.lock == canon tip
#   1  STALE    spec.lock != canon tip                  (a verdict)
#   2  UNKNOWN  cannot determine — no verdict rendered  (fail-closed)
#
# Consumers must treat 1 and 2 alike when gating (neither authorises authoring),
# but must REPORT them differently: 2 is "I don't know", never "you are stale".
#
# Usage: spec-staleness.sh [--fetch] [--quiet]
# Env:   DOC_REPO, SPEC_REMOTE, SPEC_BRANCH, SPEC_STALENESS_TIMEOUT (default 15)
#
# stdout always carries exactly one machine-readable line:
#   SPEC_STALENESS: status={fresh|stale|unknown} …

set -uo pipefail   # deliberately NOT -e: we inspect return codes ourselves.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
LOCKFILE="$REPO_ROOT/spec.lock"

FETCH=0
QUIET=0
for arg in "$@"; do
  case "$arg" in
    --fetch) FETCH=1 ;;
    --quiet) QUIET=1 ;;
    -h|--help)
      grep '^#' "$0" | sed -e 's/^# \{0,1\}//' -e '1d'
      exit 0 ;;
    *)
      echo "spec-staleness: unknown argument '$arg'" >&2
      exit 2 ;;
  esac
done

say() { [ "$QUIET" -eq 1 ] || printf '%s\n' "$*"; }

# Fail-closed exit. Prints the machine line, then (unless --quiet) the reason.
# Never renders a staleness verdict.
unknown() {
  printf 'SPEC_STALENESS: status=unknown reason=%s\n' "$1"
  say "  Canon unreachable — no verdict rendered (fail-closed). $2"
  exit 2
}

is_sha() { [[ "$1" =~ ^[0-9a-f]{40}$ ]]; }

# ── read the pin ──────────────────────────────────────────────────────────────
[ -f "$LOCKFILE" ] || unknown "spec_lock_missing" "No spec.lock at $LOCKFILE."

LOCK_SHA="$(awk '$1 == "source_sha:" { print $2; exit }' "$LOCKFILE" 2>/dev/null)"
LOCK_REF="$(awk '$1 == "source_ref:" { print $2; exit }' "$LOCKFILE" 2>/dev/null)"
LOCK_DATE="$(awk '$1 == "source_date:" { print $2; exit }' "$LOCKFILE" 2>/dev/null)"

is_sha "${LOCK_SHA:-}" || unknown "spec_lock_sha_unparsable" \
  "spec.lock:source_sha is '${LOCK_SHA:-<empty>}', not a 40-hex commit sha."

REMOTE="${SPEC_REMOTE:-}"
BRANCH="${SPEC_BRANCH:-}"
if [ -z "$REMOTE" ] || [ -z "$BRANCH" ]; then
  case "${LOCK_REF:-}" in
    */*)
      [ -z "$REMOTE" ] && REMOTE="${LOCK_REF%%/*}"
      [ -z "$BRANCH" ] && BRANCH="${LOCK_REF#*/}"
      ;;
    *)
      unknown "spec_lock_ref_unparsable" \
        "spec.lock:source_ref is '${LOCK_REF:-<empty>}', not <remote>/<branch>."
      ;;
  esac
fi

# ── locate the authenticated clone ────────────────────────────────────────────
DOC_REPO="${DOC_REPO:-$(cd "$REPO_ROOT/../documentation" 2>/dev/null && pwd || true)}"
if [ -z "$DOC_REPO" ] || [ ! -d "$DOC_REPO/.git" ]; then
  unknown "clone_missing" \
    "No documentation clone (expected \$REPO_ROOT/../documentation, or set \$DOC_REPO). The canon is private; it is reachable only through the clone's authenticated '$REMOTE' remote."
fi

# ── never hang, never prompt ──────────────────────────────────────────────────
# A SessionStart hook that blocks on a credential prompt bricks the session.
export GIT_TERMINAL_PROMPT=0
export GIT_SSH_COMMAND="${GIT_SSH_COMMAND:-ssh -oBatchMode=yes}"

TIMEOUT_SECS="${SPEC_STALENESS_TIMEOUT:-15}"
TO=""
if command -v timeout >/dev/null 2>&1; then
  TO="timeout $TIMEOUT_SECS"
elif command -v gtimeout >/dev/null 2>&1; then
  TO="gtimeout $TIMEOUT_SECS"
fi

# ── ask the remote ────────────────────────────────────────────────────────────
# shellcheck disable=SC2086  # $TO is an intentional optional command prefix.
LS_OUT="$($TO git -C "$DOC_REPO" ls-remote "$REMOTE" "refs/heads/$BRANCH" 2>/dev/null)"
LS_RC=$?

if [ "$LS_RC" -ne 0 ]; then
  unknown "ls_remote_failed_rc${LS_RC}" \
    "'git -C $DOC_REPO ls-remote $REMOTE refs/heads/$BRANCH' failed (offline, or the clone lost its credentials)."
fi

CANON_SHA="$(printf '%s\n' "$LS_OUT" | awk 'NR == 1 { print $1 }')"

# rc==0 with empty output: the remote answered, but carries no such ref.
# This is the trap the ADR names. It is NOT a staleness verdict.
is_sha "${CANON_SHA:-}" || unknown "ref_absent_on_remote" \
  "Remote '$REMOTE' answered, but 'refs/heads/$BRANCH' resolved to no commit sha. Exit status alone would have read this as an empty canon and reported STALE."

# ── verdict ───────────────────────────────────────────────────────────────────
if [ "$CANON_SHA" = "$LOCK_SHA" ]; then
  printf 'SPEC_STALENESS: status=fresh pin=%s canon=%s distance=0\n' \
    "${LOCK_SHA:0:7}" "${CANON_SHA:0:7}"
  say "  spec/ is at the canon tip ($REMOTE/$BRANCH @ ${CANON_SHA:0:7})."
  exit 0
fi

# Stale. Enrich with a distance when the objects are local; never fetch silently.
if [ "$FETCH" -eq 1 ]; then
  # shellcheck disable=SC2086
  $TO git -C "$DOC_REPO" fetch --quiet "$REMOTE" "$BRANCH" >/dev/null 2>&1 || true
fi

DISTANCE="unknown"
FILES="unknown"
if git -C "$DOC_REPO" cat-file -e "${CANON_SHA}^{commit}" 2>/dev/null &&
   git -C "$DOC_REPO" cat-file -e "${LOCK_SHA}^{commit}" 2>/dev/null; then
  d="$(git -C "$DOC_REPO" rev-list --count "${LOCK_SHA}..${CANON_SHA}" 2>/dev/null)"
  [ -n "$d" ] && DISTANCE="$d"
  f="$(git -C "$DOC_REPO" diff --name-only "${LOCK_SHA}..${CANON_SHA}" -- handoff 2>/dev/null | grep -c .)"
  [ -n "$f" ] && FILES="$f"
fi

printf 'SPEC_STALENESS: status=stale pin=%s canon=%s distance=%s files=%s\n' \
  "${LOCK_SHA:0:7}" "${CANON_SHA:0:7}" "$DISTANCE" "$FILES"

if [ "$QUIET" -ne 1 ]; then
  echo "  spec/ is pinned at ${LOCK_SHA:0:7} (${LOCK_DATE:-date unknown})."
  echo "  Canon $REMOTE/$BRANCH is at ${CANON_SHA:0:7}."
  if [ "$DISTANCE" != "unknown" ]; then
    echo "  Distance: $DISTANCE commit(s), $FILES handoff file(s) changed."
  else
    echo "  Distance: not computable — the canon commit is not in the local clone."
    echo "            Re-run with --fetch to update remote-tracking refs (does not move HEAD)."
  fi
  echo "  Authoring against a stale snapshot is gated. To refresh:"
  echo "    scripts/sync-spec.sh   # ⚠ moves the ../documentation clone's HEAD — confirm first"
fi

exit 1
