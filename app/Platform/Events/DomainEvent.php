<?php

namespace App\Platform\Events;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The append-only `domain_events` log (foundations-domain-events-audit, design D1/D2):
 * transactional outbox AND the 10-year audit / financial event store in one table. The
 * DomainEventRecorder (task 3.4) is the single write path; the immutability triggers
 * (migration 000004) make a persisted row insert-only at the storage layer, so this model
 * is effectively write-once — never update()/delete() an event (CLAUDE.md invariant 4).
 *
 * No created_at/updated_at: `occurred_at` (app-set UTC) is the envelope clock, so framework
 * timestamps are disabled (task 3.1). `$guarded` is empty because the recorder is the only
 * writer and assembles the envelope internally — there is no mass-assignment from request
 * input on a platform substrate model.
 *
 * Casts realise the Postgres-truthful columns under the SQLite/PG fallback (design D2):
 * `payload` jsonb ↔ array, `occurred_at` timestampTz ↔ CarbonImmutable, `actor_role` ↔ the
 * ActorRole enum (the value-set floor on both engines — invariant 8), `schema_version` int.
 *
 * @property int $id
 * @property string $event_id
 * @property string $name
 * @property int $schema_version
 * @property string $module
 * @property CarbonImmutable $occurred_at
 * @property ActorRole $actor_role
 * @property int|null $actor_id
 * @property string $entity_type
 * @property string $entity_id
 * @property string $correlation_id
 * @property int|null $causation_id
 * @property array<string, mixed> $payload
 */
class DomainEvent extends Model
{
    protected $table = 'domain_events';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'actor_role' => ActorRole::class,
            'schema_version' => 'integer',
        ];
    }
}
