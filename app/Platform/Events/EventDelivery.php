<?php

namespace App\Platform\Events;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The per-consumer delivery ledger row (foundations-domain-events-audit, design D2/D5):
 * one mutable row per (domain_event × consumer) carrying the lifecycle pending → done | failed,
 * the attempt count and the exponential-backoff clock. Unlike DomainEvent/AuditRecord this table
 * is deliberately MUTABLE (the executor and sweep flip status/attempts/available_at as delivery
 * is retried) and carries framework timestamps — it is delivery infrastructure, not the
 * append-only audit/event record, so no immutability trigger guards it.
 *
 * `$guarded` is empty: the recorder (3.4) and the InlineDeliveryExecutor (4.1) are the only
 * writers, assembling rows internally — no request-driven mass assignment. Casts (design D2):
 * `status` ↔ the DeliveryStatus enum (the value-set floor on both engines), `available_at`
 * timestampTz ↔ CarbonImmutable (NULL = due now).
 *
 * @property int $id
 * @property int $domain_event_id
 * @property string $consumer
 * @property DeliveryStatus $status
 * @property int $attempts
 * @property CarbonImmutable|null $available_at
 * @property string|null $last_error
 */
class EventDelivery extends Model
{
    protected $table = 'event_deliveries';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'available_at' => 'immutable_datetime',
        ];
    }
}
