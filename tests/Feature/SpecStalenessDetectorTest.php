<?php

// Pins the fail-closed contract of scripts/spec-staleness.sh — the gate that stops a
// change being authored against a stale spec/ snapshot (ADR 2026-07-10-spec-vendoring-
// cadence-and-staleness-gate).
//
// The gate's ONLY interesting property is what it does when it cannot see the canon.
// A detector that guesses is worse than no detector: the first one drafted for the ADR
// queried the private canon URL unauthenticated, received an empty string, compared it
// to the pin, and reported STALE while the repo was perfectly in sync.
//
// Two distinct empty-answer modes exist and neither may become a verdict:
//   - unreachable remote       -> git exits non-zero, stdout empty
//   - reachable, ref not found -> git exits ZERO,     stdout empty
// The second is why `rc == 0` is not a sufficient guard, and it is the shape the
// original bug actually hit. Both are exercised against local file:// remotes, so this
// test never touches the network.
//
// Exit-code contract: 0 = fresh · 1 = stale (a verdict) · 2 = unknown (no verdict).

use Tests\Support\Platform\SpecStalenessSandbox;

const SPEC_STALENESS_FRESH = 0;
const SPEC_STALENESS_STALE = 1;
const SPEC_STALENESS_UNKNOWN = 2;

/** @param  callable(SpecStalenessSandbox): void  $body */
function specStalenessWithSandbox(callable $body): void
{
    $sandbox = SpecStalenessSandbox::create(base_path('scripts/spec-staleness.sh'));

    try {
        $body($sandbox);
    } finally {
        $sandbox->destroy();
    }
}

/** Strip comment lines: the guard below asserts on what the script DOES, not what it says. */
function specStalenessCode(string $source): string
{
    $lines = array_filter(
        explode("\n", $source),
        static fn (string $line): bool => ! str_starts_with(ltrim($line), '#'),
    );

    return implode("\n", $lines);
}

it('refuses to render a verdict when the canon is unreachable', function () {
    // The headline requirement: offline / lost credentials must NOT read as "stale".
    specStalenessWithSandbox(function (SpecStalenessSandbox $sandbox) {
        [$out, $rc] = $sandbox->run(['SPEC_REMOTE' => 'dead']);

        expect($rc)->toBe(SPEC_STALENESS_UNKNOWN)
            ->and($out)->toContain('status=unknown')
            ->and($out)->toContain('reason=ls_remote_failed')
            ->and($out)->not->toContain('status=stale')
            ->and($out)->not->toContain('status=fresh');
    });
});

it('refuses to render a verdict when the remote answers but carries no such ref', function () {
    // `git ls-remote <reachable> refs/heads/<absent>` exits 0 with EMPTY stdout.
    // Guarding on the exit status alone would compare "" against the pin and print STALE.
    specStalenessWithSandbox(function (SpecStalenessSandbox $sandbox) {
        [$out, $rc] = $sandbox->run(['SPEC_BRANCH' => 'doesnotexist']);

        expect($rc)->toBe(SPEC_STALENESS_UNKNOWN)
            ->and($out)->toContain('status=unknown')
            ->and($out)->toContain('reason=ref_absent_on_remote')
            ->and($out)->not->toContain('status=stale');
    });
});

it('reports fresh when the pin equals the canon tip', function () {
    specStalenessWithSandbox(function (SpecStalenessSandbox $sandbox) {
        [$out, $rc] = $sandbox->run();

        expect($rc)->toBe(SPEC_STALENESS_FRESH)
            ->and($out)->toContain('status=fresh')
            ->and($out)->toContain('distance=0');
    });
});

it('reports stale with a commit distance when the pin lags the canon tip', function () {
    specStalenessWithSandbox(function (SpecStalenessSandbox $sandbox) {
        $sandbox->pin($sandbox->firstSha);

        [$out, $rc] = $sandbox->run();

        expect($rc)->toBe(SPEC_STALENESS_STALE)
            ->and($out)->toContain('status=stale')
            ->and($out)->toContain('distance=1');
    });
});

it('refuses to render a verdict when the clone is absent', function () {
    // The canon is private: without the clone there is no authenticated route to it.
    specStalenessWithSandbox(function (SpecStalenessSandbox $sandbox) {
        [$out, $rc] = $sandbox->run([], $sandbox->base.'/no-such-clone');

        expect($rc)->toBe(SPEC_STALENESS_UNKNOWN)
            ->and($out)->toContain('status=unknown')
            ->and($out)->toContain('reason=clone_missing');
    });
});

it('refuses to render a verdict when spec.lock is missing', function () {
    specStalenessWithSandbox(function (SpecStalenessSandbox $sandbox) {
        $sandbox->removeLock();

        [$out, $rc] = $sandbox->run();

        expect($rc)->toBe(SPEC_STALENESS_UNKNOWN)
            ->and($out)->toContain('reason=spec_lock_missing');
    });
});

it('refuses to render a verdict when the pin is not a commit sha', function () {
    // The empty-string comparison, guarded at the pin side as well as the canon side.
    specStalenessWithSandbox(function (SpecStalenessSandbox $sandbox) {
        $sandbox->pin('not-a-sha');

        [$out, $rc] = $sandbox->run();

        expect($rc)->toBe(SPEC_STALENESS_UNKNOWN)
            ->and($out)->toContain('reason=spec_lock_sha_unparsable');
    });
});

it('queries the canon through the authenticated clone, never the URL', function () {
    // `git ls-remote https://github.com/c-mless/…` is unauthenticated and returns
    // "Repository not found" with empty stdout — the original failure. The only legal
    // ls-remote target is a named remote inside the clone.
    $code = specStalenessCode((string) file_get_contents(base_path('scripts/spec-staleness.sh')));

    expect($code)->toContain('git -C "$DOC_REPO" ls-remote "$REMOTE"');
    expect((int) preg_match('~ls-remote\s+"?https?://~i', $code))->toBe(0);
});

it('ships the detector as an executable script', function () {
    expect(is_executable(base_path('scripts/spec-staleness.sh')))->toBeTrue();
});

it('warns from the SessionStart hook without ever blocking the session', function () {
    // The hook must exit 0 in every state. A staleness warning that can brick a session
    // start is a worse failure than the drift it reports.
    $hook = specStalenessCode((string) file_get_contents(base_path('.claude/hooks/spec-staleness.sh')));

    expect($hook)->toContain('exit 0');
    expect((int) preg_match('~^\s*exit\s+[1-9]~m', $hook))->toBe(0);
});

it('pins a parseable spec.lock in the real repo', function () {
    // No network: the committed lock must be machine-readable for the detector to work.
    $lock = (string) file_get_contents(base_path('spec.lock'));

    expect((int) preg_match('/^source_sha:\s+[0-9a-f]{40}$/m', $lock))->toBe(1);
    expect((int) preg_match('/^source_ref:\s+\S+\/\S+$/m', $lock))->toBe(1);
});

it('gates change authoring on the detector in the spec-to-change skill', function () {
    // The precondition is the ADR's actual deliverable: no change authored against a
    // stale snapshot. If the skill stops calling the detector, the gate is decorative.
    $skill = (string) file_get_contents(base_path('.claude/skills/spec-to-change/SKILL.md'));

    expect($skill)->toContain('scripts/spec-staleness.sh')
        ->and($skill)->toContain('fail-closed');
});
