<?php

use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Exceptions\IllegalClubTransition;
use App\Modules\Parties\Exceptions\IllegalProducerAgreementTransition;
use App\Modules\Parties\Exceptions\IllegalProducerTransition;
use Tests\TestCase;

// Pins the three supply-side transition-guard exceptions (parties-producer-lifecycle, task 1.1;
// design L2; party-registry — Requirements: Producer/ProducerAgreement/Club Lifecycle). Each
// transition Action (tasks 2.x–4.x) throws one of these on an out-of-state call; here we assert each
// named factory builds the right class with a localized, PII-free reason that names the offending
// state. Booting the app (TestCase, NO RefreshDatabase — no DB is touched) makes the translator
// available so __() resolves the lang/en/parties.php copy instead of echoing the key back.

uses(TestCase::class);

// Each chosen from-state's token is ABSENT from its key's literal template, so the token's presence
// in the message proves :state was interpolated — not merely that the copy spells a similar word.

it('rejects activating a Producer that is not draft, naming the offending state', function () {
    $exception = IllegalProducerTransition::cannotActivate(ProducerStatus::Retired);

    expect($exception)->toBeInstanceOf(IllegalProducerTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('retired');
});

it('rejects retiring a Producer that is not active, naming the offending state', function () {
    $exception = IllegalProducerTransition::cannotRetire(ProducerStatus::Draft);

    expect($exception)->toBeInstanceOf(IllegalProducerTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('draft');
});

it('rejects activating a ProducerAgreement that is not draft, naming the offending state', function () {
    $exception = IllegalProducerAgreementTransition::cannotActivate(ProducerAgreementStatus::Terminated);

    expect($exception)->toBeInstanceOf(IllegalProducerAgreementTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('terminated');
});

it('rejects terminating a ProducerAgreement that is not active, naming the offending state', function () {
    $exception = IllegalProducerAgreementTransition::cannotTerminate(ProducerAgreementStatus::Draft);

    expect($exception)->toBeInstanceOf(IllegalProducerAgreementTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('draft');
});

it('rejects sunsetting a Club that is not active, naming the offending state', function () {
    $exception = IllegalClubTransition::cannotSunset(ClubStatus::Closed);

    expect($exception)->toBeInstanceOf(IllegalClubTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('closed');
});

it('rejects closing a Club that is not sunset, naming the offending state', function () {
    $exception = IllegalClubTransition::cannotClose(ClubStatus::Active);

    expect($exception)->toBeInstanceOf(IllegalClubTransition::class)
        ->and($exception->getMessage())->not->toBe('')
        ->and($exception->getMessage())->toContain('active');
});

it('resolves every new transition lang key with the :state placeholder wired', function (string $key) {
    // 'retired' appears in none of the six literal templates, so containing it proves :state is
    // interpolated for every key; a missing key would make Laravel echo the key back unchanged.
    $resolved = __($key, ['state' => 'retired']);

    expect($resolved)->not->toBe($key)
        ->and($resolved)->toContain('retired');
})->with([
    'parties.producer.cannot_activate',
    'parties.producer.cannot_retire',
    'parties.club.cannot_sunset',
    'parties.club.cannot_close',
    'parties.producer_agreement.cannot_activate',
    'parties.producer_agreement.cannot_terminate',
]);

it('preserves the existing parties creation-rejection lang groups', function () {
    // The transition keys are ADDED alongside the parties-core creation guards — not a rewrite; the
    // four pre-existing keys must still resolve (acceptance: existing groups preserved).
    expect(__('parties.club.missing_producer', ['producer' => 7]))
        ->not->toBe('parties.club.missing_producer')
        ->toContain('7');

    expect(__('parties.producer_agreement.missing_producer', ['producer' => 9]))
        ->not->toBe('parties.producer_agreement.missing_producer')
        ->toContain('9');

    expect(__('parties.customer.duplicate_email'))
        ->not->toBe('parties.customer.duplicate_email');

    expect(__('parties.profile.duplicate_for_club', ['customer' => 1, 'club' => 2]))
        ->not->toBe('parties.profile.duplicate_for_club');
});
