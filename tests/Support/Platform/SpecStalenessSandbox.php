<?php

namespace Tests\Support\Platform;

/**
 * An isolated fixture for scripts/spec-staleness.sh: a canon repo (two commits), a
 * clone that reaches it over file://, and a fake repo root holding a copy of the real
 * script plus a rewritable spec.lock.
 *
 * Everything is local — the detector's contract is exercised without touching the
 * network, including the two empty-answer modes that must never become a verdict.
 */
final class SpecStalenessSandbox
{
    private function __construct(
        public readonly string $base,
        public readonly string $root,
        public readonly string $clone,
        public readonly string $firstSha,
        public readonly string $tipSha,
    ) {}

    /** Build the sandbox on disk. Call {@see destroy()} when done. */
    public static function create(string $scriptPath): self
    {
        $base = sys_get_temp_dir().'/spec-staleness-'.bin2hex(random_bytes(6));
        $canon = "$base/canon";
        $clone = "$base/clone";
        $root = "$base/root";

        mkdir("$root/scripts", 0o777, true);
        mkdir($canon, 0o777, true);

        $git = 'git -c user.email=t@example.com -c user.name=Test';
        $inCanon = 'cd '.escapeshellarg($canon).' && ';
        $inClone = 'cd '.escapeshellarg($clone).' && ';

        self::shell("$git init -q -b main ".escapeshellarg($canon));
        file_put_contents("$canon/one.md", "one\n");
        self::shell($inCanon."$git add -A && $git commit -qm one");
        $first = trim(self::shell($inCanon.'git rev-parse HEAD')[0]);

        file_put_contents("$canon/two.md", "two\n");
        self::shell($inCanon."$git add -A && $git commit -qm two");
        $tip = trim(self::shell($inCanon.'git rev-parse HEAD')[0]);

        // In production the clone is the only authenticated route to the private canon;
        // here it is a plain file:// remote. `dead` points nowhere, so a test can make
        // the canon unreachable without going offline.
        self::shell("$git init -q -b main ".escapeshellarg($clone));
        self::shell($inClone."git remote add cmless file://$canon");
        self::shell($inClone."git remote add dead file://$base/nope.git");
        // Fetch so the objects are local and the commit distance is computable.
        self::shell($inClone.'git fetch -q cmless main');

        copy($scriptPath, "$root/scripts/spec-staleness.sh");
        chmod("$root/scripts/spec-staleness.sh", 0o755);

        $sandbox = new self($base, $root, $clone, $first, $tip);
        $sandbox->pin($tip);

        return $sandbox;
    }

    /** Rewrite spec.lock to pin an arbitrary sha (or a deliberately malformed one). */
    public function pin(string $sha, string $ref = 'cmless/main'): void
    {
        file_put_contents($this->root.'/spec.lock', <<<LOCK
            # spec.lock — provenance of ai-dev/spec/.
            source_repo:   https://example.invalid/canon
            source_ref:    $ref
            source_subdir: handoff
            source_sha:    $sha
            source_date:   2026-01-01T00:00:00+00:00
            source_commit: fixture
            LOCK);
    }

    public function removeLock(): void
    {
        unlink($this->root.'/spec.lock');
    }

    /**
     * Run the detector under a doctored environment.
     *
     * @param  array<string, string>  $env
     * @return array{0: string, 1: int} [combined output, exit code]
     */
    public function run(array $env = [], ?string $docRepo = null): array
    {
        $assignments = 'DOC_REPO='.escapeshellarg($docRepo ?? $this->clone);
        foreach ($env as $key => $value) {
            $assignments .= ' '.$key.'='.escapeshellarg($value);
        }

        return self::shell('env '.$assignments.' bash '.escapeshellarg($this->root.'/scripts/spec-staleness.sh'));
    }

    public function destroy(): void
    {
        self::shell('rm -rf '.escapeshellarg($this->base));
    }

    /** @return array{0: string, 1: int} */
    private static function shell(string $command): array
    {
        $output = [];
        $rc = 0;
        exec($command.' 2>&1', $output, $rc);

        return [implode("\n", $output), $rc];
    }
}
