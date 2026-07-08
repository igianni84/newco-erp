<?php

namespace App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource\Pages;

use App\Modules\OperatorPanel\Filament\Console\OperatorConsoleCreateRecord;
use App\Modules\OperatorPanel\Filament\Resources\Parties\CustomerResource;
use App\Modules\Parties\Actions\CreateCustomer as CreateCustomerAction;
use App\Platform\I18n\SupportedLocale;
use App\Platform\Money\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * The write-through Create page for a Customer (operator-console-parties-customer, task 2.1; design D6/D7; ADR
 * 2026-06-19 + 2026-06-20; spec — Operator creates a Customer through the console).
 *
 * The console NEVER saves the model directly: the kit base's `handleRecordCreation()` delegates to
 * {@see createViaAction()} here, which routes the form data into the Parties domain action
 * {@see CreateCustomerAction} and returns the new `Customer` (born `pending`, co-provisioning its 1:1 Account and
 * recording ONLY `CustomerCreated` — the Account is event-silent; no Profile is created — design D7). Filament's
 * default `new Model($data); $record->save()` stays fully overridden by the base — there is no `$model->save()`
 * here (the no-Eloquent-write PHPStan rule guards it). The actor envelope (`actor_role: newco_ops` + the operator
 * id) is resolved by the action through the platform `ActorContext` seam off the authenticated `operator` guard —
 * the page constructs none.
 *
 * The page/action class-name collision (this page is `CreateCustomer`, the domain action is also
 * `CreateCustomer`) is resolved by aliasing the action import to `CreateCustomerAction` (design D6, mirrors the
 * sibling `CreateProducer as CreateProducerAction`). The create operands are PLATFORM-level — `Currency::of()`
 * and `SupportedLocale::from()`, both always-importable — so this page imports NO `Parties\Enums` and the
 * operand-enum carve-out (ADR 2026-06-21) is NOT exercised; the boundary needs no widening (design D6). A
 * colliding email is rejected by the action's in-transaction guard with a localized `DuplicateCustomerEmail`
 * (a `RuntimeException`, named in prose so Pint cannot re-add a forbidden `Parties\Exceptions` import — lessons.md
 * 2026-06-20) which the base catch maps to a form error on {@see createRejectionField()} (`email`).
 *
 * TWO create-rejections, TWO fields (task 6.3, BR-K-Identity-6 / canon MVP-DEC-022). The registration age gate —
 * a `date_of_birth` implying an age below `CreateCustomer::MINIMUM_REGISTRATION_AGE`, or a missing DOB — must
 * surface on the DATE-OF-BIRTH field, not the base's `email`; but the base maps every domain `RuntimeException`
 * to the single `createRejectionField()`, and the concrete `BelowMinimumRegistrationAge` type is NOT importable
 * here (the {Models, Actions, Enums} boundary — ModuleBoundariesTest). So {@see createViaAction()} catches the
 * action's rejection by base `RuntimeException` and ROUTES it: the age gate — discriminated by re-deriving its
 * pure-input condition (`date_of_birth` vs the action's shared public `MINIMUM_REGISTRATION_AGE` constant, the one
 * source of truth — no magic number) — is re-raised as a {@see ValidationException} carrying the domain's own
 * localized message on `date_of_birth`; every other rejection (`DuplicateCustomerEmail`) is re-thrown untouched to
 * the base's `email` mapping. The DOB field is thus EFFECTIVELY required — the domain rejects a null/under-age
 * value and the console surfaces it there — though the form itself marks it optional (design D6).
 */
class CreateCustomer extends OperatorConsoleCreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function createRejectionField(): string
    {
        return 'email';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createViaAction(array $data): Model
    {
        // Filament types the post-validation form state as array<string, mixed>; narrow each value to the Parties
        // action's typed contract at the boundary. The form's `required` email/name/preferred_currency/
        // preferred_locale make the happy path well-formed; phone/date_of_birth are optional. The DatePicker
        // dehydrates date_of_birth as a 'Y-m-d' string → CarbonImmutable. InvalidArgumentException is a
        // LogicException, so it propagates past the base's RuntimeException catch — a programming bug, not a form
        // error.
        $email = $data['email'] ?? null;
        $name = $data['name'] ?? null;
        $currencyCode = $data['preferred_currency'] ?? null;
        $localeCode = $data['preferred_locale'] ?? null;
        $phone = $data['phone'] ?? null;
        $dateOfBirth = $data['date_of_birth'] ?? null;

        if (
            ! is_string($email)
            || ! is_string($name)
            || ! is_string($currencyCode)
            || ! is_string($localeCode)
            || ! (is_null($phone) || is_string($phone))
            || ! (is_null($dateOfBirth) || is_string($dateOfBirth))
        ) {
            throw new InvalidArgumentException('Unexpected Customer create payload.');
        }

        // The DatePicker dehydrates date_of_birth as a 'Y-m-d' string; assemble the immutable date ONCE so the same
        // value drives the action call AND the age-gate discriminator in the catch below.
        $dateOfBirthValue = ($dateOfBirth === null || $dateOfBirth === '') ? null : CarbonImmutable::parse($dateOfBirth);

        try {
            return app(CreateCustomerAction::class)->handle(
                email: $email,
                name: $name,
                preferredCurrency: Currency::of($currencyCode),
                preferredLocale: SupportedLocale::from($localeCode),
                phone: ($phone === null || $phone === '') ? null : $phone,
                dateOfBirth: $dateOfBirthValue,
            );
        } catch (RuntimeException $exception) {
            // The action raised a localized create-rejection. The base catch would map ANY of them to the single
            // `createRejectionField()` (`email`), but the registration age gate must land on the DATE-OF-BIRTH field
            // (delta spec). We cannot `instanceof BelowMinimumRegistrationAge` — it is a `Parties\Exceptions` type,
            // outside the {Models, Actions, Enums} boundary — so we discriminate by RE-DERIVING the age gate's pure,
            // DB-free input condition (a null or under-minimum `date_of_birth`) against the action's shared public
            // `MINIMUM_REGISTRATION_AGE` constant: the single source of truth, mirroring the action's own boundary
            // (born > "now − min" ⇒ below minimum; inclusive at exactly the minimum), so no magic number can drift.
            if (
                $dateOfBirthValue === null
                || $dateOfBirthValue->greaterThan(CarbonImmutable::now()->subYears(CreateCustomerAction::MINIMUM_REGISTRATION_AGE))
            ) {
                // Re-raise on `date_of_birth`, surfacing the domain's OWN localized reason verbatim (no console-owned
                // copy). ValidationException is not a RuntimeException, so the base catch leaves it untouched.
                throw ValidationException::withMessages([
                    'data.date_of_birth' => $exception->getMessage(),
                ]);
            }

            // Not the age gate (e.g. DuplicateCustomerEmail) → let the base catch map it to `email`.
            throw $exception;
        }
    }
}
