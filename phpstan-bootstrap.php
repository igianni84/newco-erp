<?php

// Ensure static analysis has enough memory. Larastan loads reflection for the whole
// Laravel framework, which overflows tight CLI memory_limit values such as the 128M
// Homebrew default and OOMs the bare `vendor/bin/phpstan analyse` command from the
// CLAUDE.md Quality Commands table. memory_limit is PHP_INI_ALL, and PHPStan loads
// this bootstrap file in both the main and parallel-worker processes
// (CommandHelper::begin), so raising it here fixes every invocation path without a
// CLI flag. '-1' (unlimited, e.g. CI's setup-php default) is left untouched; only a
// finite, tighter limit is raised.

$limit = ini_get('memory_limit');

if ($limit !== false && $limit !== '-1') {
    ini_set('memory_limit', '1G');
}
