<?php

namespace Database\Factories\Parties;

use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\AccountType;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Customer;
use App\Platform\Money\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * The model lives outside `App\Models`, so the factory declares it explicitly — Factory::modelName()
     * returns `$this->model` directly, and the model points back via its `newFactory()` override.
     *
     * @var class-string<Account>
     */
    protected $model = Account::class;

    /**
     * An Account born `active`/`personal` (this slice transitions nothing), under a parent Customer built by the
     * Customer factory (a WITHIN-module reference). In production the Account is co-provisioned by the
     * CreateCustomer action; this factory stands one up directly for Account-focused tests — it is event-silent
     * either way (the PRD names no Account event — design D7). `default_currency` is an ISO 4217 PREFERENCE
     * string (design D9), never a money amount; `name` is the single personal account's "Personal" label.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // a within-module parent Customer (the non-nullable FK is structurally single-parent — § 4.7).
            'customer_id' => Customer::factory(),
            'account_type' => AccountType::Personal,
            'name' => 'Personal',
            'status' => AccountStatus::Active,
            // ISO 4217 PREFERENCE string (design D9) — the typed-anchor backing value, stored as a plain string.
            'default_currency' => Currency::EUR->value,
            'version' => 1,
        ];
    }
}
