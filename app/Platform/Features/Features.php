<?php

namespace App\Platform\Features;

use Laravel\Pennant\Feature;

/**
 * The reusable feature-flag accessor (foundations-money-i18n-flags, design D5) — the one
 * way to define and resolve a platform feature flag, wrapping Laravel Pennant behind the
 * typed {@see FeatureFlag} registry so call-sites never pass a magic string.
 *
 * Flags are global: each resolver ignores scope (a no-argument closure), so a flag reads
 * the same for every caller until an operational value is stored. Operationally flipping a
 * flag is the operator surface's job (a later change); here flags ship at their launch
 * default ({@see FeatureFlag::defaultState()}).
 */
class Features
{
    /**
     * Register every defined feature flag with Pennant at its launch default. Called once
     * from AppServiceProvider::boot(); defining only records an in-memory resolver (no DB
     * access), so it is safe before the `features` table migration has run.
     */
    public static function define(): void
    {
        foreach (FeatureFlag::cases() as $flag) {
            Feature::define($flag->value, fn (): bool => $flag->defaultState());
        }
    }

    /**
     * Resolve whether a feature flag is active (its stored operational value, or its
     * launch default when none is stored).
     */
    public static function active(FeatureFlag $flag): bool
    {
        return Feature::active($flag->value);
    }
}
