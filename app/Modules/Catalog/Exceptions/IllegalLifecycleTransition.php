<?php

namespace App\Modules\Catalog\Exceptions;

use App\Modules\Catalog\Enums\LifecycleState;
use RuntimeException;

/**
 * Raised when a lifecycle transition is attempted from a state the FSM does not allow
 * (design D1/D2; product-catalog — Requirement: Product Lifecycle State Machine).
 *
 * Every spine entity shares the IDENTICAL four-state FSM `draft → reviewed → active → retired`,
 * plus the `retired → reviewed` reopen (Module 0 PRD § 4.1). The transition map is uniform — submit
 * is valid only from `draft`, activate only from `reviewed`, retire only from `active`, reopen only
 * from `retired`, and the two `reviewed → reviewed` governance decisions (§ 4.3) — a review rejection
 * and its twin re-submit (RM-06, re-arming review after a rejection) — only from `reviewed`
 * — so a SINGLE parameterized exception serves all seven entities (design D2): the
 * entity name is a factory parameter, not a class-per-entity (this is the faithful analogue of
 * Module K's `IllegalProducerTransition`, which needs distinct classes only because its three FSMs
 * genuinely differ). The shared transition mechanism is the SOLE writer of `lifecycle_state`; it
 * re-reads the target row `lockForUpdate` inside its transaction and asserts the from-state before
 * writing, so an out-of-state call throws this and the transaction rolls back — the row, the audit
 * trail and the event log are left unchanged (nothing is recorded).
 *
 * The reason is localized through Laravel's translator (CLAUDE.md invariant 12 — no hardcoded
 * user-facing strings): the English baseline lives in the `lifecycle` group of `lang/en/catalog.php`
 * (keys `cannot_submit` / `cannot_activate` / `cannot_retire` / `cannot_reopen` / `cannot_reject` / `cannot_resubmit`), with `:state` and
 * `:entity` placeholders. The offending state token (`$from->value`) is a business enum value and the
 * entity name (`$entity`, e.g. `ProductMaster`) is an entity-type label — NEITHER is PII — so both are
 * interpolated to make the reason self-documenting. `(string)` coerces the translator return (typed
 * `mixed` by Larastan) to the RuntimeException message contract.
 */
class IllegalLifecycleTransition extends RuntimeException
{
    public static function cannotSubmit(LifecycleState $from, string $entity): self
    {
        return self::build('cannot_submit', $from, $entity);
    }

    public static function cannotActivate(LifecycleState $from, string $entity): self
    {
        return self::build('cannot_activate', $from, $entity);
    }

    public static function cannotRetire(LifecycleState $from, string $entity): self
    {
        return self::build('cannot_retire', $from, $entity);
    }

    public static function cannotReopen(LifecycleState $from, string $entity): self
    {
        return self::build('cannot_reopen', $from, $entity);
    }

    public static function cannotReject(LifecycleState $from, string $entity): self
    {
        return self::build('cannot_reject', $from, $entity);
    }

    public static function cannotResubmit(LifecycleState $from, string $entity): self
    {
        return self::build('cannot_resubmit', $from, $entity);
    }

    private static function build(string $key, LifecycleState $from, string $entity): self
    {
        return new self((string) __("catalog.lifecycle.{$key}", [
            'state' => $from->value,
            'entity' => $entity,
        ]));
    }
}
