<?php

namespace App\Platform\Events;

/**
 * The canonical seam that supplies the actor provenance — `(actor_role, actor_id)` —
 * for the current execution context, so the domain-event and audit recorders obtain
 * the acting principal from ONE place instead of hardcoding {@see ActorRole::System}
 * at every call site (foundations-money-i18n-flags, design D6; the event-substrate
 * "Actor Context Resolution" requirement).
 *
 * Launch behaviour: the default context is `(ActorRole::System, null)` — the role for
 * console, queue and unauthenticated work. {@see runAs()} applies an explicit role
 * (and optional actor id) for the duration of a callable and restores the PRIOR
 * context afterward (even if the callable throws), so overrides nest correctly.
 *
 * GATE-SAFE BY CONSTRUCTION: this seam reads NO authentication state and imports no
 * auth / Filament / module code, so it stays on the safe side of the identity/auth
 * ADR gate (which fires before Module K). Mapping an authenticated operator, producer
 * or customer to their `actor_role` is that ADR's responsibility (Module K); the auth
 * ADR will later wire the authenticated principal in HERE — to this one seam — with no
 * call-site churn. ActorContextTest pins the gate-safety both ways: an authenticated
 * session still resolves to System (behavioural), and an arch import assertion proves
 * no auth namespace is even referenced (structural).
 *
 * Shared process-wide as a container singleton (AppServiceProvider::register), so a
 * run-as override set on the resolved instance is observed by every consumer that
 * resolves it within that scope.
 */
class ActorContext
{
    private ActorRole $role = ActorRole::System;

    private ?int $actorId = null;

    /** The acting role for the current context (default {@see ActorRole::System}). */
    public function role(): ActorRole
    {
        return $this->role;
    }

    /** The acting principal's id for the current context, or null (the default). */
    public function actorId(): ?int
    {
        return $this->actorId;
    }

    /**
     * Run $callback with `($role, $actorId)` as the current context, restoring the
     * prior context afterward — even if $callback throws. The runner is transparent:
     * it returns the callback's own return value.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(ActorRole $role, ?int $actorId, callable $callback): mixed
    {
        $priorRole = $this->role;
        $priorActorId = $this->actorId;

        $this->role = $role;
        $this->actorId = $actorId;

        try {
            return $callback();
        } finally {
            $this->role = $priorRole;
            $this->actorId = $priorActorId;
        }
    }
}
