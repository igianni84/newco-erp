<?php

namespace App\Platform\Audit;

use App\Platform\Events\ActorRole;
use Illuminate\Database\Eloquent\Model;

/**
 * The operator-action audit trail row (foundations-domain-events-audit, design D1/D2):
 * WHO did WHAT to WHICH entity, with the before/after structural snapshot and the authorization
 * basis (CLAUDE.md invariant 8). The AuditRecorder (task 3.3) is the single write path; the
 * immutability triggers (migration 000004) reject every DELETE and every *structural* UPDATE,
 * allowing only `before`/`after` to change — the GDPR redaction seam (design D7).
 *
 * No created_at/updated_at: `occurred_at` (app-set UTC) is the envelope clock, so framework
 * timestamps are disabled (task 3.1). `$guarded` is empty because the recorder is the only
 * writer. Casts (design D2): `before`/`after` jsonb ↔ array (the snapshot), `actor_role` ↔ the
 * ActorRole enum (the value-set floor on both engines). `occurred_at` is left uncast per the
 * task's explicit cast list — the audit clock is written, never read back as a date object here.
 *
 * @property int $id
 * @property string $occurred_at
 * @property string $module
 * @property ActorRole $actor_role
 * @property int|null $actor_id
 * @property string $entity_type
 * @property string $entity_id
 * @property string $correlation_id
 * @property string $action
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string $authorization_basis
 */
class AuditRecord extends Model
{
    protected $table = 'audit_records';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'actor_role' => ActorRole::class,
        ];
    }
}
