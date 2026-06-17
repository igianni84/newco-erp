---
type: decision
status: active
date: 2026-06-17
---

## Decision: spec/ is a vendored snapshot of the shared documentation repo, refreshed by a deliberate sync script

The product spec now lives in the shared private repo **`c-mless/documentation`** (owner: Paolo; the engineering team's single build source). `ai-dev/spec/` becomes a **vendored mirror of that repo's `handoff/` subtree**, regenerated wholesale by **`scripts/sync-spec.sh`** and pinned to a known upstream commit in **`spec.lock`**. Only `handoff/` is mirrored; `reference/v1.1/` (the frozen, superseded v1.1 spec) deliberately stays out of this repo.

The "link" is **deliberate, not live**: `spec/` does not float to upstream `HEAD`. Refreshing is an explicit operation — run `scripts/sync-spec.sh`, review `git diff -- spec/`, commit the refresh + `spec.lock` as one commit. The script fast-forwards the sibling clone `../documentation` from remote `cmless`, then `rsync -a --delete handoff/ → spec/`.

## Context: why this came up

The NewCo documentation was uploaded to GitHub (`c-mless/documentation`, PRIVATE, accessed via the `GiovanniCrurated` account; also cloned locally at `NewCo/documentation/` with remotes `cmless` → c-mless/documentation and `origin` → GiovanniCrurated/NewCoDocumentation). Giovanni asked (2026-06-17) to "azzerare" `spec/` and link it to that repo so we pull the updated version.

Two facts shaped the mechanism:
1. **`spec/` is structurally identical to the repo's `handoff/` subtree** (`00-business-model`, `02-prd`, `03-acceptance`, `04-decisions`, `04-roadmap`, `05-release`, `_provenance`, `README.md`, `CHANGES_v1.1_to_v0.3-MVP.md`) — `spec/` was the one-time copy of `handoff/` taken at the v0.3-MVP handoff (2026-06-11). The repo's own README states the one rule: **build from `handoff/`**.
2. **~360 citations across 57 files** (CLAUDE.md, GUIDE.md, README, CONTEXT.md, 9 ADRs, the `openspec/specs/**` truth specs, the `spec-to-change` skill, and every archived change) reference `spec/0X/...` paths. Any mechanism that shifts those paths breaks all of them.

CLAUDE.md also requires `reference/v1.1/` to live **outside** this repo and never be a build source.

## Alternatives considered

- **Git submodule at `spec/`.** A true live link to a pinned commit. **Rejected:** a submodule mounts the *whole* repo at the mount point, so paths become `spec/handoff/02-prd/...` (breaks all ~360 citations) and `reference/v1.1/` enters the repo (violates the "v1.1 outside" convention). Would force a repo-wide citation rewrite + CLAUDE.md/GUIDE convention changes.
- **Git subtree.** Same whole-repo-into-prefix path problem as submodule; pulling only a subdirectory of a remote is not clean. **Rejected.**
- **Sync script, `handoff/` → `spec/` (CHOSEN).** Preserves the exact structure (zero citation breakage), keeps `reference/v1.1/` out, gives a deliberate pinned refresh, works offline against the existing clone.

## Reasoning: why the sync script won

- **Zero blast radius.** `spec/02-prd/...` etc. stay put; none of the ~360 citations move. Verified: the first sync produced a `spec/` diff identical to the upstream `handoff/` diff (content-only).
- **Honors the existing conventions.** `reference/v1.1/` stays out; `spec/` remains the immutable-by-convention build baseline (we never hand-edit it — the script regenerates it).
- **Deliberate refresh is the right default for a spec the build traces to.** A pinned snapshot (recorded in `spec.lock`) keeps the build reproducible and auditable, and prevents upstream edits from silently desyncing in-flight `openspec` changes that cite `spec/` sections. Refresh is a reviewed event, not a float.
- **Reuses what exists.** The `NewCo/documentation/` clone already tracks `cmless`; the script just ff-updates it and mirrors `handoff/`.

## Trade-offs accepted

- `spec/` is a **vendored copy, not a live pointer** — refreshing requires running the script (a human/loop step), not `git pull`. Accepted: deliberate refresh is a feature here, not a bug.
- The script assumes the clone exists as a sibling (`../documentation`); it errors with remediation if absent (override via `$DOC_REPO`).
- `spec.lock` and `scripts/sync-spec.sh` are **ai-dev-only additions** not present in `handoff/` — provenance lives outside `spec/` so `spec/` stays a pure mirror.

## References

- Giovanni's instruction, 2026-06-17 (link `spec/` to `c-mless/documentation`).
- `scripts/sync-spec.sh` (the mechanism); `spec.lock` (provenance pin — currently `cmless/main` @ `4f48277`).
- Shared repo: `https://github.com/c-mless/documentation` (PRIVATE); local clone `NewCo/documentation/`.
- CLAUDE.md → "Spec authority" (build from `spec/`; never edit `spec/**`; v1.1 lives outside this repo).
- Blast-radius finding: ~360 `spec/` citations across 57 files.
- Related: the refresh brought the producer-KYC clarification → [2026-06-17-producer-kyc-gate-not-required-clears.md](2026-06-17-producer-kyc-gate-not-required-clears.md).
