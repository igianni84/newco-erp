#!/usr/bin/env bash
#
# sync-spec.sh — refresh ai-dev/spec/ from the shared product-spec repo.
#
# spec/ is a VENDORED SNAPSHOT of c-mless/documentation:handoff/ — the immutable
# build baseline the whole repo traces to (see CLAUDE.md "Spec authority").
# This script IS the "link": it pulls the latest handoff/ from the shared repo
# and regenerates spec/ wholesale (azzera-e-ricarica), preserving the exact
# folder structure so the ~360 spec/ citations across the repo never break.
# Only handoff/ is mirrored — reference/v1.1/ deliberately stays out of this repo.
#
# Refresh is DELIBERATE (run this script), not a live float: the build pins to a
# known upstream commit, recorded in spec.lock. Review `git diff -- spec/` and
# commit the refresh as one commit.
#
# ADR: decisions/2026-06-17-spec-synced-from-documentation-repo.md
#
# Env overrides: DOC_REPO (clone path), SPEC_REMOTE (default cmless), SPEC_BRANCH (default main).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SPEC_DIR="$REPO_ROOT/spec"
LOCKFILE="$REPO_ROOT/spec.lock"
DOC_REPO="${DOC_REPO:-$(cd "$REPO_ROOT/../documentation" 2>/dev/null && pwd || true)}"
REMOTE="${SPEC_REMOTE:-cmless}"
BRANCH="${SPEC_BRANCH:-main}"
SUBDIR="handoff"

if [[ -z "${DOC_REPO}" || ! -d "${DOC_REPO}/.git" ]]; then
  echo "ERROR: documentation clone not found (expected at \$REPO_ROOT/../documentation, or set \$DOC_REPO)." >&2
  echo "  Clone it:  git clone https://github.com/c-mless/documentation.git \"$REPO_ROOT/../documentation\"" >&2
  exit 1
fi

echo "→ Fetching $REMOTE/$BRANCH in $DOC_REPO …"
git -C "$DOC_REPO" fetch "$REMOTE" "$BRANCH"

if [[ -n "$(git -C "$DOC_REPO" status --porcelain)" ]]; then
  echo "ERROR: the documentation clone has uncommitted changes — resolve them before syncing." >&2
  exit 1
fi

git -C "$DOC_REPO" checkout --quiet "$BRANCH"
git -C "$DOC_REPO" merge --ff-only "$REMOTE/$BRANCH"

SRC="$DOC_REPO/$SUBDIR"
[[ -d "$SRC" ]] || { echo "ERROR: '$SRC' not found in the clone." >&2; exit 1; }

SRC_SHA="$(git -C "$DOC_REPO" rev-parse HEAD)"
SRC_SHORT="$(git -C "$DOC_REPO" rev-parse --short HEAD)"
SRC_DATE="$(git -C "$DOC_REPO" log -1 --format=%cI HEAD)"
SRC_SUBJ="$(git -C "$DOC_REPO" log -1 --format=%s HEAD)"

echo "→ Regenerating $SPEC_DIR from $SUBDIR/ @ $SRC_SHORT …"
mkdir -p "$SPEC_DIR"
rsync -a --delete --exclude='.DS_Store' "$SRC/" "$SPEC_DIR/"

cat > "$LOCKFILE" <<EOF
# spec.lock — provenance of ai-dev/spec/. Auto-written by scripts/sync-spec.sh; do not hand-edit.
# spec/ is a vendored mirror of the handoff/ subtree of the shared product-spec repo.
source_repo:   https://github.com/c-mless/documentation
source_ref:    $REMOTE/$BRANCH
source_subdir: $SUBDIR
source_sha:    $SRC_SHA
source_date:   $SRC_DATE
source_commit: $SRC_SUBJ
EOF

echo "✓ spec/ synced to $REMOTE/$BRANCH @ $SRC_SHORT — \"$SRC_SUBJ\""
echo "  Review: git -C \"$REPO_ROOT\" diff --stat -- spec/ spec.lock"
echo "  Commit: spec/ refresh + spec.lock together."
