<?php

namespace App\Platform\Audit;

use App\Platform\Events\ActorRole;
use App\Platform\Events\NotInTransactionException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single write path for `audit_records` (foundations-domain-events-audit, task 3.3; design D3) —
 * the operator/system action trail: WHO did WHAT to WHICH entity, with the before/after structural
 * snapshot and the authorization basis (CLAUDE.md invariant 8).
 *
 * Assembles the shared envelope core (app-set UTC `occurred_at`; `module` as a plain string;
 * `actor_role`; nullable `actor_id`; entity reference; `correlation_id`, defaulting to a fresh
 * UUIDv7 when the caller passes none) plus the audit-specific `action`, `before`, `after` and
 * `authorization_basis`, then appends one row through the {@see AuditRecord} model.
 *
 * `module` is a `string`, NOT the `App\Modules\Module` enum: the boundary law forbids platform code
 * from depending on `App\Modules` (design D1; arch test `it_forbids_platform_code_from_depending_on
 * _any_module`), so the substrate speaks the storage primitive — module emitters pass
 * `Module::X->value` (the registry stays their typed anchor), platform-emitted records pass
 * `'platform'`. Same shape as `event_deliveries.consumer` holding a string FQCN. (Design D3 sketched
 * `Module|string`; that realization is refined to `string` here because D1 — the canonical boundary
 * — outranks a D3 realization detail it conflicts with.)
 *
 * Mirrors the domain-event recorder's envelope assembly (task 3.4) and shares its
 * transaction guard ({@see NotInTransactionException}), but — unlike the event recorder — creates NO
 * `event_deliveries` rows: audit records are write-only with respect to the substrate; no consumer
 * machinery reads them (event-substrate spec, Audit Records). The immutability triggers (migration
 * 000004) then make the written row append-only save for GDPR redaction of `before`/`after`.
 *
 * That redaction is the ONE other DML the triggers permit, so it flows through this same class:
 * {@see redactEntity()} nulls the `before`/`after` snapshots of an entity's audit rows in place — the
 * GDPR erasure seam a module's right-to-erasure job reserves (event-substrate spec; the reserved
 * `redactor` role runbook). The substrate stays module-agnostic: the caller passes the entity's
 * `entity_type`/`entity_id` (a string envelope, like `module`), never an `App\Modules` type. Append
 * ({@see record()}) and redact are the two mutations `audit_records` accepts; both share the
 * no-dual-write transaction guard.
 */
class AuditRecorder
{
    /**
     * Append one operator/system action to `audit_records`. MUST run inside an open database
     * transaction (the no-dual-write guard); returns the persisted record.
     *
     * @param  string  $module  the emitting module's registry value (`Module::X->value`) or `'platform'`
     * @param  array<string, mixed>|null  $before  pre-action snapshot (null for a creation)
     * @param  array<string, mixed>|null  $after  post-action snapshot (both nulled on GDPR redaction)
     *
     * @throws NotInTransactionException when no database transaction is active
     */
    public function record(
        string $action,
        string $module,
        ActorRole $actorRole,
        ?int $actorId,
        string $entityType,
        string $entityId,
        ?array $before,
        ?array $after,
        string $authorizationBasis,
        ?string $correlationId = null,
    ): AuditRecord {
        if (DB::transactionLevel() === 0) {
            throw NotInTransactionException::forRecording('an audit record');
        }

        return AuditRecord::create([
            'occurred_at' => CarbonImmutable::now('UTC'),
            'module' => $module,
            'actor_role' => $actorRole,
            'actor_id' => $actorId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'correlation_id' => $correlationId ?? (string) Str::uuid7(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'authorization_basis' => $authorizationBasis,
        ]);
    }

    /**
     * Redact the PII snapshots of every `audit_records` row for one entity — null its `before`/`after`
     * — the GDPR erasure seam. This is the SOLE mutation the immutability triggers permit on an audit
     * row (a `before`/`after`-only UPDATE; migration `2026_06_12_000004`): structural columns are never
     * touched, so the trigger passes the statement and the record skeleton stays frozen; rows are NEVER
     * deleted. The reserved PostgreSQL `redactor` role (immutability layer 2, applied at the hosting
     * gate — event-substrate spec / ADR 2026-06-12) is a REVOKE runbook, NOT a query-time dependency:
     * the trigger itself allows the before/after-only UPDATE on both engines regardless of role.
     *
     * Written on the base query builder (the `ImmutabilityTest` redaction idiom) so `before`/`after`
     * bind as true SQL NULL — no Eloquent array cast turning `null` into the string `"null"`, no column
     * qualification the SQLite SET clause would reject. Only rows still carrying a snapshot are matched,
     * so the return is the number of rows ACTUALLY redacted — 0 when the entity has a PII-free (or empty)
     * audit trail, the common case for a module recording only PII-free snapshots.
     *
     * MUST run inside an open transaction so the redaction is atomic with the caller's erasure — the
     * same no-dual-write guard as {@see record()}.
     *
     * @return int the number of audit rows redacted
     *
     * @throws NotInTransactionException when no database transaction is active
     */
    public function redactEntity(string $entityType, string $entityId): int
    {
        if (DB::transactionLevel() === 0) {
            throw NotInTransactionException::forRecording('an audit redaction');
        }

        return DB::table('audit_records')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where(fn (Builder $carrying) => $carrying->whereNotNull('before')->orWhereNotNull('after'))
            ->update(['before' => null, 'after' => null]);
    }
}
