<?php

namespace App\Modules\Parties\Actions;

use App\Modules\Module;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\AccountType;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Events\CustomerCreated;
use App\Modules\Parties\Exceptions\DuplicateCustomerEmail;
use App\Modules\Parties\Models\Customer;
use App\Platform\Events\ActorContext;
use App\Platform\Events\DomainEventRecorder;
use App\Platform\I18n\SupportedLocale;
use App\Platform\Money\Currency;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Customer in `pending`, co-provisions its 1:1 `Account`, and records its {@see CustomerCreated}
 * event — all atomically in ONE transaction (parties-core, design D3/D5/D6/D7; party-registry — Requirements:
 * Customer Identity, Account — Billing Container, Spine Creation Events).
 *
 * One guard makes the launch invariant enforced, not advised:
 *   - GLOBALLY-UNIQUE EMAIL (§ 4.1, BR-K-Identity-1): a Customer's email is unique across all Customers. Inside
 *     the transaction a presence check rejects a colliding email with a localized {@see DuplicateCustomerEmail}
 *     reason. The `unique` index on `parties_customers.email` is the true structural guard; the pre-check
 *     surfaces a clean operator reason ahead of the raw integrity error (design D5).
 *
 * The action takes the {@see Currency} / {@see SupportedLocale} typed anchors for the currency/locale
 * preferences — fail-closed at the boundary (a code outside the launch set cannot be passed), persisted as the
 * plain ISO-code / locale strings the columns hold (design D9). Then, in ONE {@see DB::transaction}:
 *   1. insert the Customer — born `pending`, marker `customer` (BR-K-Identity-5), `originating_club_id` NULL
 *      (the Originating Club is born unset with NO mutation surface here — design D6);
 *   2. co-provision the 1:1 Account through the within-module {@see Customer::account()} relation — born
 *      `active`/`personal`, its default currency mirroring the Customer's preference (§ 4.7, § 7.1 step 3);
 *   3. record ONLY {@see CustomerCreated} via the platform {@see DomainEventRecorder} (the actor resolved from
 *      the {@see ActorContext} seam — System until real principals wire in), with a STRICT PII-free payload.
 *
 * Two deliberate spec-faithful choices: the Account is EVENT-SILENT — no `AccountCreated` is recorded (the PRD
 * § 15 catalog names none — design D7); and there is NO setter for `originating_club_id` (this action's public
 * surface is exactly construction + {@see handle()} — the BR-K-OC-2 "no admin-override" floor, satisfiable in a
 * creation-only slice). The recorder's own transaction guard makes write + emit atomic. The models stay
 * persistence-only; this action is the seam the deferred lifecycle change extends.
 */
class CreateCustomer
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
        private readonly ActorContext $actor,
    ) {}

    public function handle(
        string $email,
        string $name,
        Currency $preferredCurrency,
        SupportedLocale $preferredLocale,
        ?string $phone = null,
        ?CarbonInterface $dateOfBirth = null,
    ): Customer {
        return DB::transaction(function () use (
            $email,
            $name,
            $preferredCurrency,
            $preferredLocale,
            $phone,
            $dateOfBirth,
        ): Customer {
            // § 4.1 / BR-K-Identity-1: a Customer's email is globally unique. Reject a collision with a clean
            // localized reason ahead of the unique-index integrity error (the index is the structural backstop —
            // design D5).
            if (Customer::query()->where('email', $email)->exists()) {
                throw DuplicateCustomerEmail::forEmail($email);
            }

            $customer = Customer::create([
                'email' => $email,
                'name' => $name,
                'phone' => $phone,
                'date_of_birth' => $dateOfBirth,
                'party_type' => PartyType::Customer,
                'preferred_currency' => $preferredCurrency->value,
                'preferred_locale' => $preferredLocale->value,
                'status' => CustomerStatus::Pending,
                // born unset — design D6 provides no mutation surface for the Originating Club this change.
                'originating_club_id' => null,
            ]);

            // co-provision the 1:1 Account through the within-module relation (design D5): born active/personal,
            // its default currency mirroring the Customer's preference. `name` ("Personal") and `version` rely on
            // the column defaults. The Account is EVENT-SILENT — no AccountCreated is recorded (design D7).
            $customer->account()->create([
                'account_type' => AccountType::Personal,
                'status' => AccountStatus::Active,
                'default_currency' => $preferredCurrency->value,
            ]);

            // record ONLY CustomerCreated (the Account leg is silent — design D7). The payload is STRICT
            // PII-free (no email/name/phone/date_of_birth — design D7).
            $this->recorder->record(
                name: CustomerCreated::NAME,
                module: Module::Parties->value,
                actorRole: $this->actor->role(),
                actorId: $this->actor->actorId(),
                entityType: CustomerCreated::ENTITY_TYPE,
                entityId: (string) $customer->id,
                payload: CustomerCreated::payload($customer),
            );

            return $customer;
        });
    }
}
