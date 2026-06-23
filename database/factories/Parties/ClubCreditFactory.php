<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Profile;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubCredit>
 */
class ClubCreditFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<ClubCredit>
     */
    protected $model = ClubCredit::class;

    /**
     * An `active` Club Credit on a within-module parent Profile (built by its own factory, whose default Club
     * carries `generates_credit = true` and a 25000 EUR fee). The factory bypasses the IssueClubCredit action, so
     * it records nothing and runs no eligibility guard — a pure fixture standing up a credit cheaply. `amount`
     * mirrors the ClubFactory default fee (full-fee → full-credit, design L2); `remaining` = `amount` (an
     * untouched credit, K.17 carry-forward starts full); `valid_to` = 31 Dec of the issuance year. `state` is set
     * explicitly: the column carries NO default (the Action is the sole writer — design L4), so a factory omitting
     * it would hit the NOT-NULL floor. Override `state` (with `remaining`) for redeemed/forfeited fixtures.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // the full membership fee made spendable — the SAME Money for amount and remaining at issuance (design
        // L2); mirrors the ClubFactory default fee so a credit fixture is coherent with its issuing Club.
        $amount = Money::of(25000, Currency::EUR);

        return [
            // a within-module parent Profile (the non-nullable FK — design L1).
            'profile_id' => Profile::factory(),
            // the two Money fields persist through the MoneyCast as `{key}_minor`/`{key}_currency` (invariant 6).
            'amount' => $amount,
            'remaining' => $amount,
            // the validity window (design L2): issuance instant → 31 Dec of the issuance year at launch.
            'valid_from' => CarbonImmutable::now(),
            'valid_to' => CarbonImmutable::now()->endOfYear(),
            'state' => ClubCreditState::Active,
        ];
    }
}
