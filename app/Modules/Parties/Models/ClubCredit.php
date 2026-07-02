<?php

namespace App\Modules\Parties\Models;

use App\Modules\Parties\Enums\ClubCreditState;
use App\Platform\Money\Money;
use App\Platform\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Parties\ClubCreditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ClubCredit — the per-Profile prepayment instrument the membership fee converts into (Module K PRD § 11;
 * DEC-007; change club-credit design L1/L3/L4). It belongs to EXACTLY ONE Profile via a WITHIN-module FK to
 * `parties_profiles`, so the cross-module ban (invariant 10) does not apply: the {@see profile()} relation is a
 * within-module `belongsTo`. At most ONE `active` credit exists per Profile at any moment — enforced
 * structurally by the partial unique index `(profile_id) WHERE state = 'active'` (design L1), not by this model.
 *
 * Persistence-only by design (L4): the four within-module writer Actions (`IssueClubCredit`
 * + Apply/Forfeit/Restore — landing in tasks 2–4) are the SOLE writers; each assembles the attributes internally
 * inside one `DB::transaction`, so `$guarded = []` carries no mass-assignment-from-request risk (mirrors the
 * sibling spine models {@see Club} / {@see Profile}). The writers are **audit-only**: they record state and emit
 * NO domain event — § 11.4 makes the four Club Credit events + `MembershipFeePaid` events Module K *consumes*,
 * never emits (design L3): `ClubCreditAccrued` (issuance; canon DEC-018 renamed it from `ClubCreditIssued`),
 * `ClubCreditRestored` + `ClubCreditForfeited` are Module-E-emitted, while the *application* event
 * `ClubCreditApplied` is Module-S-emitted per DEC-018 (re-home a deferred seam, Module S unbuilt — see
 * decisions/2026-07-02-adopt-dec-018-clubcredit-accrued.md). Module K consumes them either way. The entity
 * state + the append-only audit trail are the launch record.
 *
 * The two Money fields follow the MoneyCast `{key}_minor`/`{key}_currency` convention (integer minor units + an
 * ISO 4217 code, NEVER a float — invariant 6): `amount` (the issued credit, = `Club.fee` verbatim at full-fee
 * issuance — design L2) and `remaining` (the spendable balance, § 11.2 K.17 carry-forward). Both are NOT NULL — a
 * credit ALWAYS has an amount, unlike the nullable `Club.fee`. `valid_from`/`valid_to` are the validity window
 * (issuance instant → 31 Dec of the issuance year at launch), `immutable_datetime`. `state` is the § 11 FSM
 * ({@see ClubCreditState} — `active → redeemed | forfeited`); it carries NO DB default (the Action sets it
 * explicitly), so the cast is the in-memory floor and the model never "borns" a credit by a bare insert.
 *
 * @property int $id
 * @property int $profile_id
 * @property Money $amount
 * @property Money $remaining
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable $valid_to
 * @property ClubCreditState $state
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Profile $profile
 */
class ClubCredit extends Model
{
    /** @use HasFactory<ClubCreditFactory> */
    use HasFactory;

    protected $table = 'parties_club_credits';

    /**
     * The four within-module writer Actions are the only writers; each assembles the attributes internally, so
     * there is no mass-assignment from request input to guard (mirrors the sibling spine models).
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The owning Profile — a WITHIN-module `belongsTo` (both entities are Module K, so the cross-module relation
     * ban does not apply). The reference is required (a non-nullable FK). Its inverse is the at-most-one
     * {@see Profile::activeClubCredit()} (task 1.4).
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /**
     * The factory lives outside the `Database\Factories\` convention (it is namespaced per module under
     * `Database\Factories\Parties\`), so the model names it explicitly — and the explicit return type lets
     * static analysis infer the factory's model for `ClubCredit::factory()->create()`.
     */
    protected static function newFactory(): ClubCreditFactory
    {
        return ClubCreditFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'remaining' => MoneyCast::class,
            'state' => ClubCreditState::class,
            'valid_from' => 'immutable_datetime',
            'valid_to' => 'immutable_datetime',
        ];
    }
}
