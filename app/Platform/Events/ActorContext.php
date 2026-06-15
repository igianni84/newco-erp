<?php

namespace App\Platform\Events;

use Illuminate\Support\Facades\Auth;

/**
 * The canonical seam that supplies the actor provenance — `(actor_role, actor_id)` —
 * for the current execution context, so the domain-event and audit recorders obtain
 * the acting principal from ONE place instead of hardcoding {@see ActorRole::System}
 * at every call site (foundations-money-i18n-flags, design D6; the event-substrate
 * "Actor Context Resolution" requirement).
 *
 * Resolution is LAZY and PER-CALL, in this precedence (operator-auth-foundation, design D5):
 *   1. an explicit scoped {@see runAs()} override wins (console, queue, jobs, tests, system work);
 *   2. else an operator authenticated on the `operator` session guard →
 *      (`ActorRole::NewcoOps`, the operator id);
 *   3. else (`ActorRole::System`, null) — unauthenticated / console / queue work.
 *
 * The operator guard is read BY NAME (`Auth::guard('operator')`); this seam imports NOTHING
 * from `App\Modules\OperatorPanel` (no `Operator` model), so it stays boundary-clean platform
 * code — `ModuleBoundariesTest` (App\Platform must not depend on App\Modules) pins that
 * globally and `ActorContextTest` adds a localised structural guard. The customer and producer
 * guards are NOT consulted yet: their identity changes (deferred) extend step 2.
 *
 * {@see runAs()} applies an explicit role (and optional actor id) for the duration of a
 * callable and restores the PRIOR override afterward (even if the callable throws), so
 * overrides nest correctly.
 *
 * Shared process-wide as a container singleton (AppServiceProvider::register). The override
 * is a NULLABLE field distinct from the default so that, with no override in force, the guard
 * is consulted at `role()`/`actorId()` call time and NEVER memoised across a request or worker
 * (correct regardless; matters under Octane). A non-null override role marks an override as
 * present even when it overrides to (`System`, null) — which a defaulted field could not tell
 * apart from "no override".
 */
class ActorContext
{
    /**
     * The role of an active {@see runAs()} override, or null when none is in force. Nullable BY
     * DESIGN (not defaulted to a role): its null-ness signals "no override" so steps 2/3 resolve
     * the guard lazily, and a non-null value records an override even when it targets
     * (`System`, null).
     */
    private ?ActorRole $overrideRole = null;

    private ?int $overrideActorId = null;

    /** The acting role for the current context (override → operator guard → {@see ActorRole::System}). */
    public function role(): ActorRole
    {
        if ($this->overrideRole !== null) {
            return $this->overrideRole;
        }

        return Auth::guard('operator')->check() ? ActorRole::NewcoOps : ActorRole::System;
    }

    /** The acting principal's id for the current context (override → operator id → null). */
    public function actorId(): ?int
    {
        if ($this->overrideRole !== null) {
            return $this->overrideActorId;
        }

        // Guard::id() is contract-typed int|string|null; coerce the operator's bigint key to int.
        $id = Auth::guard('operator')->id();

        return $id === null ? null : (int) $id;
    }

    /**
     * Run $callback with `($role, $actorId)` as the current context, restoring the
     * prior override afterward — even if $callback throws. The runner is transparent:
     * it returns the callback's own return value.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(ActorRole $role, ?int $actorId, callable $callback): mixed
    {
        $priorRole = $this->overrideRole;
        $priorActorId = $this->overrideActorId;

        $this->overrideRole = $role;
        $this->overrideActorId = $actorId;

        try {
            return $callback();
        } finally {
            $this->overrideRole = $priorRole;
            $this->overrideActorId = $priorActorId;
        }
    }
}
