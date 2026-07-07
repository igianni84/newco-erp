<?php

namespace Database\Seeders;

use App\Modules\Catalog\Actions\CreateProductMaster;
use App\Modules\Catalog\Actions\SubmitProductMasterForReview;
use App\Modules\Catalog\Enums\LifecycleState;
use App\Modules\Catalog\Enums\ProducerProjectionStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Catalog\Models\CaseConfiguration;
use App\Modules\Catalog\Models\CompositeSku;
use App\Modules\Catalog\Models\Format;
use App\Modules\Catalog\Models\ProducerState;
use App\Modules\Catalog\Models\ProductMaster;
use App\Modules\Catalog\Models\ProductReference;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\SellableSku;
use App\Modules\OperatorPanel\Models\Operator;
use App\Modules\Parties\Actions\CreateProducer;
use App\Modules\Parties\Enums\AccountStatus;
use App\Modules\Parties\Enums\AccountType;
use App\Modules\Parties\Enums\ClubCreditState;
use App\Modules\Parties\Enums\ClubRegistrationFlowType;
use App\Modules\Parties\Enums\ClubStatus;
use App\Modules\Parties\Enums\CustomerStatus;
use App\Modules\Parties\Enums\HoldScope;
use App\Modules\Parties\Enums\HoldStatus;
use App\Modules\Parties\Enums\HoldType;
use App\Modules\Parties\Enums\KycStatus;
use App\Modules\Parties\Enums\PartyType;
use App\Modules\Parties\Enums\ProducerAgreementStatus;
use App\Modules\Parties\Enums\ProducerStatus;
use App\Modules\Parties\Enums\ProfileState;
use App\Modules\Parties\Enums\SanctionsStatus;
use App\Modules\Parties\Enums\ScreeningTriggerSource;
use App\Modules\Parties\Models\Account;
use App\Modules\Parties\Models\Club;
use App\Modules\Parties\Models\ClubCredit;
use App\Modules\Parties\Models\Customer;
use App\Modules\Parties\Models\Hold;
use App\Modules\Parties\Models\Producer;
use App\Modules\Parties\Models\ProducerAgreement;
use App\Modules\Parties\Models\Profile;
use App\Modules\Parties\Models\Supplier;
use App\Platform\Events\ActorContext;
use App\Platform\Events\ActorRole;
use App\Platform\I18n\SupportedLocale;
use App\Platform\I18n\TranslatableText;
use App\Platform\Money\Currency;
use App\Platform\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Curated DEMO fixture for Module 0 (Catalog/PIM) and Module K (Parties) — a
 * recognizable fine-wine dataset that fills every operator-panel table and makes
 * the relationships between them legible for a stakeholder walkthrough.
 *
 * NOT a spec artifact and NOT part of the bootstrap {@see DatabaseSeeder} (which
 * seeds only roles + the single env-driven operator). It is opt-in demo tooling that
 * SELF-PROVISIONS a complete walkable environment — it chains {@see RoleSeeder} +
 * {@see OperatorDemoSeeder} (the ≥2 distinct operator logins the SoD / rejection
 * walkthroughs need, RM-07) before seeding data, so one command stands up the demo:
 *
 *   php artisan db:seed --class=Database\\Seeders\\DemoSeeder
 *
 * Refuses to run in production: it truncates real business tables (customers, catalog)
 * and the platform event/audit log below, so it fails closed outside a demo database.
 *
 * The BULK of the data is built DIRECT (plain `create()` + explicit lifecycle states),
 * bypassing the domain actions on purpose: the real activation path runs the shared
 * LifecycleTransition with Creator → Reviewer → Approver separation-of-duties (distinct
 * operator principals per transition) and the producer/cascade gates — correct for
 * production, but disproportionate for seeding dozens of rows. Direct rows let the data
 * read like real wine (DRC, Margaux, Krug…) with a deliberate lifecycle/status spread
 * (mostly active, a few in-pipeline and terminal) and emit NO events or audit records.
 *
 * The ONE exception is the SoD / rejection walkthrough fixture (@see seedSodReviewScenario):
 * a single Master built through the REAL Catalog actions so it carries genuine
 * creator/reviewer lineage — a directly-seeded `reviewed` row has none, so a distinct-actor
 * activation would pass vacuously and prove nothing.
 *
 * Re-runnable: {@see reset()} truncates the demo-owned tables AND the platform event/audit
 * log (so the fixture's lineage events are deterministic on every run); operators and roles
 * are NOT truncated but re-seeded idempotently. Run it repeatedly while iterating on the demo.
 */
class DemoSeeder extends Seeder
{
    /**
     * The name of the SoD / rejection walkthrough fixture Master ({@see seedSodReviewScenario}). A public
     * constant so tests resolve the fixture by a single source of truth rather than a duplicated literal.
     */
    public const SOD_FIXTURE_MASTER_NAME = 'Échézeaux Grand Cru';

    /**
     * The name of the Producer activation separation-of-duties walkthrough fixture
     * ({@see seedProducerApprovalScenario}) — the RM-08 counterpart to {@see SOD_FIXTURE_MASTER_NAME}. A public
     * constant so tests resolve the fixture by a single source of truth rather than a duplicated literal.
     */
    public const SOD_FIXTURE_PRODUCER_NAME = 'Domaine Leroy';

    public function run(): void
    {
        // A demo fixture must NEVER touch a production database: it truncates real business tables and the
        // platform event/audit log. Fail closed outside a demo environment.
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder must not run in production — it truncates business data.');
        }

        // Self-provision the walkable environment: roles + the ≥2 distinct operator logins the SoD / rejection
        // walkthroughs need (RM-07). RoleSeeder BEFORE OperatorDemoSeeder so the role grants resolve; both are
        // idempotent and left untouched by reset(), so re-running the demo keeps the same logins.
        $this->call([
            RoleSeeder::class,
            OperatorDemoSeeder::class,
        ]);

        $this->reset();

        // --- Module K (Parties) — supply + demand side --------------------------
        $producers = $this->seedProducers();
        $clubs = $this->seedClubs($producers);
        $this->seedProducerAgreements($producers, $clubs);
        $this->seedSuppliers();
        [$customers, $accounts] = $this->seedCustomers($clubs);
        $profiles = $this->seedProfiles($customers, $clubs);
        $this->seedClubCredits($profiles);
        $this->seedHolds($customers, $accounts, $profiles);

        // --- Module 0 (Catalog) — product spine ---------------------------------
        $this->seedProducerStates($producers);
        $formats = $this->seedFormats();
        $cases = $this->seedCaseConfigurations();
        $masters = $this->seedProductMasters($producers);
        $variants = $this->seedProductVariants($masters);
        $references = $this->seedProductReferences($variants, $formats);
        $this->seedSellableSkus($references, $cases);
        $this->seedCompositeSkus($references);

        // SoD / rejection walkthrough fixtures — built through the REAL domain actions so they carry genuine
        // lineage the separation-of-duties floors read back (a directly-seeded row has none → a distinct-actor
        // gate would pass vacuously). Catalog: a reviewable Master (3-step Creator → Reviewer → Approver), built
        // after the catalog spine so its producer projection (seedProducerStates) already exists (RM-07).
        // Parties: a draft, KYC-cleared Producer (2-step Creator → Approver) — the RM-08 activation SoD fixture.
        $this->seedSodReviewScenario($producers);
        $this->seedProducerApprovalScenario();

        $this->summarize();
    }

    /**
     * Empty the demo-owned tables so the seeder is idempotent. FK checks are lifted for the truncate sweep
     * (the order would otherwise matter). The platform event store + audit log ARE cleared so the SoD
     * fixture's lineage events ({@see seedSodReviewScenario}) are deterministic on every run (safe: the
     * production guard in {@see run()} keeps this off any real event store). Operators and roles are NOT
     * truncated — they are re-seeded idempotently by the chained RoleSeeder + OperatorDemoSeeder.
     */
    private function reset(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            foreach ([
                // Platform log (demo-only: the fixture's own events/audit; cleared for a deterministic re-run).
                'domain_events',
                'audit_records',
                // Catalog (children first is irrelevant under disabled FK checks).
                'catalog_composite_sku_constituents',
                'catalog_composite_skus',
                'catalog_sellable_skus',
                'catalog_product_references',
                'catalog_product_variant_wine_attributes',
                'catalog_product_variants',
                'catalog_product_master_wine_attributes',
                'catalog_product_masters',
                'catalog_formats',
                'catalog_case_configurations',
                'catalog_producer_states',
                // Parties.
                'parties_club_credits',
                'parties_holds',
                'parties_profiles',
                'parties_accounts',
                'parties_customers',
                'parties_producer_agreements',
                'parties_clubs',
                'parties_suppliers',
                'parties_producers',
            ] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        });
    }

    // =========================================================================
    // Module K — Parties
    // =========================================================================

    /**
     * Eight real wine houses with region/appellation/country and a short bilingual
     * (EN+IT) house story. Status + KYC spread: six fully active & verified, one
     * active-but-KYC-pending (Penfolds), one still in draft with no KYC (Leflaive).
     *
     * @return array<string, Producer>
     */
    private function seedProducers(): array
    {
        $rows = [
            'drc' => ['Domaine de la Romanée-Conti', 'Côte de Nuits', 'Vosne-Romanée Grand Cru', 'France', ProducerStatus::Active, KycStatus::Verified, 'https://www.romanee-conti.fr', 'The most coveted estate in Burgundy, monopole holder of Romanée-Conti and La Tâche.', 'Il dominio più ambìto della Borgogna, proprietario in monopolio di Romanée-Conti e La Tâche.'],
            'margaux' => ['Château Margaux', 'Médoc', 'Margaux 1er Grand Cru Classé', 'France', ProducerStatus::Active, KycStatus::Verified, 'https://www.chateau-margaux.com', 'First Growth of the 1855 classification, the quintessential expression of the Margaux appellation.', 'Premier Cru della classificazione 1855, espressione quintessenziale dell\'appellation Margaux.'],
            'krug' => ['Champagne Krug', 'Champagne', 'Champagne', 'France', ProducerStatus::Active, KycStatus::Verified, 'https://www.krug.com', 'Prestige Champagne house founded in 1843, celebrated for its multi-vintage Grande Cuvée.', 'Maison de Champagne di prestigio fondata nel 1843, celebre per la Grande Cuvée multi-millesimata.'],
            'gaja' => ['Gaja', 'Piedmont', 'Barbaresco DOCG', 'Italy', ProducerStatus::Active, KycStatus::Verified, 'https://www.gaja.com', 'Iconic Piedmont producer that redefined the prestige of Nebbiolo from Barbaresco.', 'Iconico produttore piemontese che ha ridefinito il prestigio del Nebbiolo di Barbaresco.'],
            'sanguido' => ['Tenuta San Guido', 'Tuscany', 'Bolgheri Sassicaia DOC', 'Italy', ProducerStatus::Active, KycStatus::Verified, 'https://www.tenutasanguido.com', 'Birthplace of Sassicaia, the original Super Tuscan and a Bolgheri benchmark.', 'Culla del Sassicaia, il Super Tuscan originale e punto di riferimento di Bolgheri.'],
            'vegasicilia' => ['Bodegas Vega Sicilia', 'Castilla y León', 'Ribera del Duero DO', 'Spain', ProducerStatus::Active, KycStatus::Verified, 'https://www.vega-sicilia.com', 'Spain\'s most legendary estate, home of the long-aged Único.', 'La tenuta più leggendaria di Spagna, casa dell\'Único dal lungo affinamento.'],
            'penfolds' => ['Penfolds', 'South Australia', 'Barossa Valley', 'Australia', ProducerStatus::Active, KycStatus::Pending, 'https://www.penfolds.com', 'Australia\'s flagship winery, maker of the multi-region Grange.', 'La cantina di punta dell\'Australia, produttrice del Grange multi-regionale.'],
            'leflaive' => ['Domaine Leflaive', 'Côte de Beaune', 'Puligny-Montrachet Grand Cru', 'France', ProducerStatus::Draft, null, 'https://www.leflaive.fr', 'Benchmark white-Burgundy domaine of Puligny-Montrachet, onboarding in progress.', 'Dominio di riferimento per i bianchi di Borgogna a Puligny-Montrachet, onboarding in corso.'],
        ];

        $producers = [];

        foreach ($rows as $slug => [$name, $region, $appellation, $country, $status, $kyc, $website, $storyEn, $storyIt]) {
            $producers[$slug] = Producer::create([
                'name' => $name,
                'region' => $region,
                'appellation' => $appellation,
                'country' => $country,
                'description' => TranslatableText::of(['en' => $storyEn, 'it' => $storyIt]),
                'website' => $website,
                'status' => $status,
                'kyc_status' => $kyc,
            ]);
        }

        return $producers;
    }

    /**
     * Ten clubs across the producers (Gaja and Krug carry two, to show a producer
     * with multiple clubs). Every registration flow, both fee/no-fee, and the active
     * vs. sunset status are represented.
     *
     * @param  array<string, Producer>  $producers
     * @return array<string, Club>
     */
    private function seedClubs(array $producers): array
    {
        $rows = [
            'drc' => ['drc', 'Romanée-Conti Cercle', ClubStatus::Active, ClubRegistrationFlowType::InvitationOnly, 100_000, true],
            'margaux' => ['margaux', 'Premier Cru Margaux Club', ClubStatus::Active, ClubRegistrationFlowType::ApplicationWithApproval, 50_000, true],
            'krug' => ['krug', 'Krug Ambassade', ClubStatus::Active, ClubRegistrationFlowType::ApplicationWithApproval, 35_000, true],
            'krug-decouverte' => ['krug', 'Krug Découverte', ClubStatus::Active, ClubRegistrationFlowType::OpenRegistration, 12_000, true],
            'gaja' => ['gaja', 'Langhe Collectors', ClubStatus::Active, ClubRegistrationFlowType::OpenRegistration, 15_000, true],
            'gaja-riserva' => ['gaja', 'Gaja Riserva Circle', ClubStatus::Active, ClubRegistrationFlowType::InvitationOnly, 60_000, true],
            'sanguido' => ['sanguido', 'Sassicaia Club', ClubStatus::Active, ClubRegistrationFlowType::OpenRegistration, 20_000, false],
            'vegasicilia' => ['vegasicilia', 'Único Reserva Club', ClubStatus::Active, ClubRegistrationFlowType::ApplicationWithApproval, 25_000, true],
            'penfolds' => ['penfolds', 'Penfolds Collectors', ClubStatus::Sunset, ClubRegistrationFlowType::LinkOnboarding, null, false],
            'leflaive' => ['leflaive', 'Montrachet Circle', ClubStatus::Active, ClubRegistrationFlowType::InvitationOnly, 40_000, true],
        ];

        $clubs = [];

        foreach ($rows as $slug => [$producerSlug, $name, $status, $flow, $feeMinor, $generatesCredit]) {
            $clubs[$slug] = Club::create([
                'producer_id' => $producers[$producerSlug]->id,
                'display_name' => $name,
                'status' => $status,
                'fee' => $feeMinor === null ? null : Money::of($feeMinor, Currency::EUR),
                'registration_flow_type' => $flow,
                'generates_credit' => $generatesCredit,
            ]);
        }

        return $clubs;
    }

    /**
     * One commercial agreement per producer, plus a superseded older DRC contract.
     * Status spread (active / draft / terminated / superseded) and the producer-wide
     * (null club) vs. club-scoped split are both shown.
     *
     * @param  array<string, Producer>  $producers
     * @param  array<string, Club>  $clubs
     */
    private function seedProducerAgreements(array $producers, array $clubs): void
    {
        $rows = [
            ['drc', 'drc', ProducerAgreementStatus::Active, '2026-01-01', '2026-12-31', 'monthly'],
            ['drc', null, ProducerAgreementStatus::Superseded, '2025-01-01', '2025-12-31', 'monthly'],
            ['margaux', null, ProducerAgreementStatus::Active, '2026-01-01', '2026-12-31', 'quarterly'],
            ['krug', 'krug', ProducerAgreementStatus::Active, '2026-01-01', '2026-12-31', 'monthly'],
            ['gaja', 'gaja', ProducerAgreementStatus::Active, '2026-01-01', '2026-12-31', 'monthly'],
            ['sanguido', null, ProducerAgreementStatus::Active, '2026-01-01', '2026-12-31', 'semi_annual'],
            ['vegasicilia', null, ProducerAgreementStatus::Draft, '2026-07-01', '2027-06-30', 'quarterly'],
            ['penfolds', null, ProducerAgreementStatus::Terminated, '2024-01-01', '2024-12-31', 'monthly'],
            ['leflaive', null, ProducerAgreementStatus::Draft, null, null, null],
        ];

        foreach ($rows as [$producerSlug, $clubSlug, $status, $start, $end, $cadence]) {
            ProducerAgreement::create([
                'producer_id' => $producers[$producerSlug]->id,
                'club_id' => $clubSlug === null ? null : $clubs[$clubSlug]->id,
                'status' => $status,
                'term_start' => $start,
                'term_end' => $end,
                'settlement_cadence' => $cadence,
            ]);
        }
    }

    /**
     * A few logistics/market suppliers (no operator page yet, but the table is real).
     */
    private function seedSuppliers(): void
    {
        foreach (['Vinlock SAS', 'Millésima SA', 'Liv-ex Limited'] as $legalName) {
            Supplier::create([
                'legal_name' => $legalName,
                'party_type' => PartyType::Supplier,
            ]);
        }
    }

    /**
     * Twelve international collectors, each co-provisioned with a personal Account
     * (the operator panel reads `account.status` per customer). Currency, locale,
     * status, KYC and sanctions are spread so every badge column shows variety.
     *
     * @param  array<string, Club>  $clubs
     * @return array{0: array<string, Customer>, 1: array<string, Account>}
     */
    private function seedCustomers(array $clubs): array
    {
        $now = CarbonImmutable::now();
        $screenedAt = $now->subMonths(2);
        $rescreenAt = $now->addMonths(10);

        // slug => [name, email, phone, dob, currency, locale, status, ocClubSlug,
        //          kyc, sanctions, accountStatus, screened(bool)]
        $rows = [
            'henry' => ['Henry Whitfield', 'henry.whitfield@example.com', '+44 20 7946 0010', '1968-03-14', Currency::GBP, SupportedLocale::En, CustomerStatus::Active, 'margaux', KycStatus::Verified, SanctionsStatus::Passed, AccountStatus::Active, true],
            'sofia' => ['Sofia Bianchi', 'sofia.bianchi@example.com', '+39 02 8901 2233', '1979-11-02', Currency::EUR, SupportedLocale::It, CustomerStatus::Active, 'gaja', KycStatus::Verified, SanctionsStatus::Passed, AccountStatus::Active, true],
            'jeanluc' => ['Jean-Luc Moreau', 'jl.moreau@example.com', '+33 1 42 65 67 89', '1972-06-21', Currency::EUR, SupportedLocale::Fr, CustomerStatus::Active, 'krug', KycStatus::Verified, SanctionsStatus::Passed, AccountStatus::Active, true],
            'klaus' => ['Klaus Hofmann', 'klaus.hofmann@example.com', '+41 44 668 1800', '1965-01-30', Currency::CHF, SupportedLocale::De, CustomerStatus::Active, 'leflaive', KycStatus::Verified, SanctionsStatus::Passed, AccountStatus::Active, true],
            'eleanor' => ['Eleanor Vance', 'eleanor.vance@example.com', '+1 212 555 0182', '1981-09-09', Currency::USD, SupportedLocale::En, CustomerStatus::Active, 'margaux', KycStatus::Verified, SanctionsStatus::Passed, AccountStatus::Active, true],
            'matteo' => ['Matteo Conti', 'matteo.conti@example.com', '+39 055 234 5566', '1976-04-18', Currency::EUR, SupportedLocale::It, CustomerStatus::Active, 'sanguido', KycStatus::Verified, SanctionsStatus::Passed, AccountStatus::Active, true],
            'isabelle' => ['Isabelle Dubois', 'isabelle.dubois@example.com', '+33 4 91 12 34 56', '1990-12-05', Currency::EUR, SupportedLocale::Fr, CustomerStatus::Pending, null, KycStatus::Pending, SanctionsStatus::Pending, AccountStatus::Active, false],
            'hiroshi' => ['Hiroshi Tanaka', 'hiroshi.tanaka@example.com', '+81 3 6701 1000', '1970-07-07', Currency::JPY, SupportedLocale::Ja, CustomerStatus::Active, 'drc', KycStatus::Verified, SanctionsStatus::Passed, AccountStatus::Active, true],
            'carlos' => ['Carlos Mendoza', 'carlos.mendoza@example.com', '+34 91 123 4567', '1974-02-28', Currency::EUR, SupportedLocale::En, CustomerStatus::Active, 'vegasicilia', KycStatus::Verified, SanctionsStatus::Passed, AccountStatus::Active, true],
            'amelia' => ['Amelia Brooks', 'amelia.brooks@example.com', '+44 161 496 0123', '1985-08-23', Currency::GBP, SupportedLocale::En, CustomerStatus::Suspended, 'krug', KycStatus::Verified, SanctionsStatus::Passed, AccountStatus::Suspended, true],
            'lukas' => ['Lukas Schneider', 'lukas.schneider@example.com', '+49 30 2000 4000', '1993-05-11', Currency::EUR, SupportedLocale::De, CustomerStatus::Pending, null, null, null, AccountStatus::Active, false],
            'giulia' => ['Giulia Romano', 'giulia.romano@example.com', '+39 06 4567 8899', '1988-10-16', Currency::EUR, SupportedLocale::It, CustomerStatus::Active, 'gaja', KycStatus::Verified, SanctionsStatus::UnderReview, AccountStatus::Active, true],
        ];

        $customers = [];
        $accounts = [];

        foreach ($rows as $slug => [$name, $email, $phone, $dob, $currency, $locale, $status, $ocSlug, $kyc, $sanctions, $accountStatus, $screened]) {
            $customer = Customer::create([
                'email' => $email,
                'name' => $name,
                'phone' => $phone,
                'date_of_birth' => $dob,
                'party_type' => PartyType::Customer,
                'preferred_currency' => $currency->value,
                'preferred_locale' => $locale->value,
                'status' => $status,
                'originating_club_id' => $ocSlug === null ? null : $clubs[$ocSlug]->id,
                'kyc_status' => $kyc,
                'kyc_required' => $kyc !== null,
                'sanctions_status' => $sanctions,
                'last_screening_at' => $screened ? $screenedAt : null,
                'next_rescreen_at' => $screened ? $rescreenAt : null,
                'screening_trigger_source' => $screened ? ScreeningTriggerSource::Onboarding : null,
                'email_verified_at' => $screened ? $screenedAt : null,
                'tc_accepted_at' => $screened ? $screenedAt : null,
                'privacy_accepted_at' => $screened ? $screenedAt : null,
            ]);

            $accounts[$slug] = Account::create([
                'customer_id' => $customer->id,
                'account_type' => AccountType::Personal,
                'name' => 'Personal',
                'status' => $accountStatus,
                'default_currency' => $currency->value,
            ]);

            $customers[$slug] = $customer;
        }

        return [$customers, $accounts];
    }

    /**
     * Memberships wiring customers to clubs. The state spread populates the
     * membership approval queue (applied / waiting_list / approved) alongside the
     * steady-state active members and the terminal lapsed / cancelled / rejected /
     * suspended outcomes. At most one non-terminal profile per (customer, club).
     *
     * @param  array<string, Customer>  $customers
     * @param  array<string, Club>  $clubs
     * @return array<string, Profile>
     */
    private function seedProfiles(array $customers, array $clubs): array
    {
        // slug => [customerSlug, clubSlug, state, tier, lapsedDaysAgo|null, cancelReason|null]
        $rows = [
            'henry-margaux' => ['henry', 'margaux', ProfileState::Active, 'Founding Member', null, null],
            'henry-krug' => ['henry', 'krug', ProfileState::Applied, null, null, null],
            'sofia-gaja' => ['sofia', 'gaja', ProfileState::Active, 'Member', null, null],
            'jeanluc-krug' => ['jeanluc', 'krug', ProfileState::Active, 'Member', null, null],
            'jeanluc-krugd' => ['jeanluc', 'krug-decouverte', ProfileState::Approved, null, null, null],
            'klaus-leflaive' => ['klaus', 'leflaive', ProfileState::Active, 'Invitation Only', null, null],
            'eleanor-margaux' => ['eleanor', 'margaux', ProfileState::Active, 'Member', null, null],
            'eleanor-drc' => ['eleanor', 'drc', ProfileState::WaitingList, null, null, null],
            'eleanor-krug' => ['eleanor', 'krug', ProfileState::Rejected, null, null, null],
            'matteo-sanguido' => ['matteo', 'sanguido', ProfileState::Active, 'Member', null, null],
            'isabelle-margaux' => ['isabelle', 'margaux', ProfileState::Applied, null, null, null],
            'hiroshi-drc' => ['hiroshi', 'drc', ProfileState::Active, 'Founding Member', null, null],
            'carlos-vega' => ['carlos', 'vegasicilia', ProfileState::Active, 'Member', null, null],
            'carlos-drc' => ['carlos', 'drc', ProfileState::Suspended, null, null, null],
            'amelia-krug' => ['amelia', 'krug', ProfileState::Lapsed, 'Member', 12, null],
            'giulia-gaja' => ['giulia', 'gaja', ProfileState::Active, 'Member', null, null],
            'giulia-sanguido' => ['giulia', 'sanguido', ProfileState::Cancelled, null, null, 'Customer requested cancellation'],
            'lukas-gaja' => ['lukas', 'gaja', ProfileState::Applied, null, null, null],
        ];

        $now = CarbonImmutable::now();
        $profiles = [];

        foreach ($rows as $slug => [$customerSlug, $clubSlug, $state, $tier, $lapsedDaysAgo, $cancelReason]) {
            $profiles[$slug] = Profile::create([
                'customer_id' => $customers[$customerSlug]->id,
                'club_id' => $clubs[$clubSlug]->id,
                'state' => $state,
                'tier' => $tier,
                'role' => null,
                'invited_by_customer_id' => null,
                'lapsed_at' => $lapsedDaysAgo === null ? null : $now->subDays($lapsedDaysAgo),
                'cancellation_reason' => $cancelReason,
            ]);
        }

        return $profiles;
    }

    /**
     * Club credits on credit-generating memberships: active (some partially spent),
     * one fully redeemed, one forfeited on the lapsed membership. At most one active
     * credit per profile.
     *
     * @param  array<string, Profile>  $profiles
     */
    private function seedClubCredits(array $profiles): void
    {
        $now = CarbonImmutable::now();
        $validFrom = $now->subMonths(3);
        $validTo = $now->endOfYear();

        // profileSlug => [amountMinor, remainingMinor, state]
        $rows = [
            'henry-margaux' => [50_000, 50_000, ClubCreditState::Active],
            'sofia-gaja' => [15_000, 9_000, ClubCreditState::Active],
            'jeanluc-krug' => [35_000, 35_000, ClubCreditState::Active],
            'klaus-leflaive' => [40_000, 22_500, ClubCreditState::Active],
            'hiroshi-drc' => [100_000, 100_000, ClubCreditState::Active],
            'giulia-gaja' => [15_000, 15_000, ClubCreditState::Active],
            'carlos-vega' => [25_000, 0, ClubCreditState::Redeemed],
            'amelia-krug' => [35_000, 0, ClubCreditState::Forfeited],
        ];

        foreach ($rows as $profileSlug => [$amount, $remaining, $state]) {
            ClubCredit::create([
                'profile_id' => $profiles[$profileSlug]->id,
                'amount' => Money::of($amount, Currency::EUR),
                'remaining' => Money::of($remaining, Currency::EUR),
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'state' => $state,
            ]);
        }
    }

    /**
     * Account restrictions across the six hold types and three scopes, including a
     * lifted payment hold (with the lift envelope filled in). `scope_id` is a plain
     * within-module reference (no FK by design).
     *
     * @param  array<string, Customer>  $customers
     * @param  array<string, Account>  $accounts
     * @param  array<string, Profile>  $profiles
     */
    private function seedHolds(array $customers, array $accounts, array $profiles): void
    {
        $now = CarbonImmutable::now();

        // KYC holds on the two unscreened customers (system-placed, auto-managed).
        foreach (['isabelle', 'lukas'] as $slug) {
            Hold::create([
                'hold_type' => HoldType::Kyc,
                'scope_type' => HoldScope::Customer,
                'scope_id' => $customers[$slug]->id,
                'status' => HoldStatus::Active,
                'reason' => null,
                'placed_actor_role' => ActorRole::System,
                'placed_actor_id' => null,
            ]);
        }

        // Compliance + fraud holds on the suspended customer (operator-placed).
        Hold::create([
            'hold_type' => HoldType::Compliance,
            'scope_type' => HoldScope::Customer,
            'scope_id' => $customers['amelia']->id,
            'status' => HoldStatus::Active,
            'reason' => 'Account under compliance review',
            'placed_actor_role' => ActorRole::NewcoOps,
            'placed_actor_id' => null,
        ]);
        Hold::create([
            'hold_type' => HoldType::Fraud,
            'scope_type' => HoldScope::Account,
            'scope_id' => $accounts['amelia']->id,
            'status' => HoldStatus::Active,
            'reason' => 'Suspected fraudulent payment instrument',
            'placed_actor_role' => ActorRole::NewcoOps,
            'placed_actor_id' => null,
        ]);

        // Admin hold on a pending membership application (profile scope).
        Hold::create([
            'hold_type' => HoldType::Admin,
            'scope_type' => HoldScope::Profile,
            'scope_id' => $profiles['henry-krug']->id,
            'status' => HoldStatus::Active,
            'reason' => 'Manual review of membership application',
            'placed_actor_role' => ActorRole::NewcoOps,
            'placed_actor_id' => null,
        ]);

        // A payment hold that has already been lifted — shows the lift envelope.
        Hold::create([
            'hold_type' => HoldType::Payment,
            'scope_type' => HoldScope::Account,
            'scope_id' => $accounts['carlos']->id,
            'status' => HoldStatus::Lifted,
            'reason' => null,
            'placed_actor_role' => ActorRole::System,
            'placed_actor_id' => null,
            'lift_reason' => 'Payment cleared',
            'lifted_actor_role' => ActorRole::System,
            'lifted_actor_id' => null,
            'lifted_at' => $now->subDays(3),
        ]);
    }

    // =========================================================================
    // Module 0 — Catalog
    // =========================================================================

    /**
     * Catalog's own producer-state projection. Mirrors the active producers so the
     * Product Master table renders the producer's NAME (denormalized onto the
     * projection) with its gate status; Leflaive (draft) is left unprojected on
     * purpose to demonstrate the "unprojected" fallback label.
     *
     * @param  array<string, Producer>  $producers
     */
    private function seedProducerStates(array $producers): void
    {
        $watermark = 0;

        foreach ($producers as $slug => $producer) {
            if ($slug === 'leflaive') {
                continue; // deliberately unprojected
            }

            ProducerState::create([
                'producer_id' => $producer->id,
                'producer_name' => $producer->name,
                // Geography denormalized onto the projection so the Product Master create form prefills
                // Country + Region from the producer (display-only, never a Module K read — invariant 10).
                'region' => $producer->region,
                'country' => $producer->country,
                'status' => ProducerProjectionStatus::Active,
                'last_event_id' => ++$watermark,
            ]);
        }
    }

    /**
     * Bottle formats, mostly active with one in review.
     *
     * @return array<string, Format>
     */
    private function seedFormats(): array
    {
        $rows = [
            'half' => ['Half Bottle', '375ml', 375, LifecycleState::Active],
            'bottle' => ['Bottle', '750ml', 750, LifecycleState::Active],
            'magnum' => ['Magnum', '1.5L', 1_500, LifecycleState::Active],
            'double-magnum' => ['Double Magnum', '3L', 3_000, LifecycleState::Active],
            'imperial' => ['Imperial', '6L', 6_000, LifecycleState::Reviewed],
        ];

        $formats = [];

        foreach ($rows as $slug => [$name, $sizeLabel, $volumeMl, $state]) {
            $formats[$slug] = Format::create([
                'name' => $name,
                'size_label' => $sizeLabel,
                'volume_ml' => $volumeMl,
                'lifecycle_state' => $state,
            ]);
        }

        return $formats;
    }

    /**
     * Case configurations spanning loose, OWC and carton packaging.
     *
     * @return array<string, CaseConfiguration>
     */
    private function seedCaseConfigurations(): array
    {
        $rows = [
            'loose' => ['Loose', 1, 'loose', LifecycleState::Active],
            'owc6' => ['Original Wooden Case (6)', 6, 'owc', LifecycleState::Active],
            'owc12' => ['Original Wooden Case (12)', 12, 'owc', LifecycleState::Active],
            'carton6' => ['Carton (6)', 6, 'carton', LifecycleState::Active],
            'carton3' => ['Carton (3)', 3, 'carton', LifecycleState::Reviewed],
        ];

        $cases = [];

        foreach ($rows as $slug => [$name, $units, $packaging, $state]) {
            $cases[$slug] = CaseConfiguration::create([
                'name' => $name,
                'units_per_case' => $units,
                'packaging_type' => $packaging,
                'lifecycle_state' => $state,
            ]);
        }

        return $cases;
    }

    /**
     * Product Masters wired to producer ids, each with its 1:1 wine attribute set
     * (appellation/region + a short EN+IT winery story). Active under projected
     * producers; the Penfolds Master sits in review and the Leflaive Master in draft.
     *
     * @param  array<string, Producer>  $producers
     * @return array<string, ProductMaster>
     */
    private function seedProductMasters(array $producers): array
    {
        // slug => [producerSlug, name, appellation, region, state, storyEn, storyIt]
        $rows = [
            'rc' => ['drc', 'Romanée-Conti Grand Cru', 'Romanée-Conti', 'Côte de Nuits', LifecycleState::Active, 'The 1.8-hectare monopole at the summit of Burgundy.', 'Il monopolio di 1,8 ettari al vertice della Borgogna.'],
            'latache' => ['drc', 'La Tâche Grand Cru', 'La Tâche', 'Côte de Nuits', LifecycleState::Active, 'DRC monopole renowned for its power and perfume.', 'Monopolio DRC celebre per potenza e profumo.'],
            'margaux' => ['margaux', 'Château Margaux Grand Vin', 'Margaux', 'Médoc', LifecycleState::Active, 'The First Growth Grand Vin of Château Margaux.', 'Il Grand Vin Premier Cru di Château Margaux.'],
            'krug-gc' => ['krug', 'Krug Grande Cuvée', 'Champagne', 'Champagne', LifecycleState::Active, 'A multi-vintage blend rebuilt every year from reserve wines.', 'Un assemblaggio multi-millesimato ricostruito ogni anno dai vini di riserva.'],
            'barbaresco' => ['gaja', 'Gaja Barbaresco', 'Barbaresco', 'Piedmont', LifecycleState::Active, 'The estate Barbaresco from Gaja\'s Langhe vineyards.', 'Il Barbaresco aziendale dai vigneti Gaja delle Langhe.'],
            'sassicaia' => ['sanguido', 'Sassicaia', 'Bolgheri Sassicaia', 'Tuscany', LifecycleState::Active, 'The original Super Tuscan, Cabernet-led from Bolgheri.', 'Il Super Tuscan originale, a base Cabernet da Bolgheri.'],
            'unico' => ['vegasicilia', 'Vega Sicilia Único', 'Ribera del Duero', 'Castilla y León', LifecycleState::Active, 'Tempranillo-led flagship aged a decade before release.', 'Ammiraglia a base Tempranillo affinata un decennio prima dell\'uscita.'],
            'grange' => ['penfolds', 'Penfolds Grange', 'Barossa Valley', 'South Australia', LifecycleState::Reviewed, 'Australia\'s most collected Shiraz, in catalog review.', 'Lo Shiraz più collezionato d\'Australia, in revisione a catalogo.'],
            'montrachet' => ['leflaive', 'Leflaive Montrachet Grand Vin', 'Montrachet', 'Côte de Beaune', LifecycleState::Draft, 'Grand Cru white Burgundy, draft pending producer onboarding.', 'Grand Cru bianco di Borgogna, in bozza in attesa dell\'onboarding del produttore.'],
        ];

        $masters = [];

        foreach ($rows as $slug => [$producerSlug, $name, $appellation, $region, $state, $storyEn, $storyIt]) {
            $master = ProductMaster::create([
                'name' => $name,
                'product_type' => ProductType::Wine,
                'producer_id' => $producers[$producerSlug]->id,
                'lifecycle_state' => $state,
            ]);

            $master->wineAttributes()->create([
                'appellation' => $appellation,
                'region' => $region,
                'winery_story' => TranslatableText::of(['en' => $storyEn, 'it' => $storyIt]),
            ]);

            $masters[$slug] = $master;
        }

        return $masters;
    }

    /**
     * Vintages (and Krug's non-vintage editions) under each Master, with the 1:1
     * variant wine attributes. Mostly active; the Penfolds/Leflaive branch stays in
     * review/draft to match its parent.
     *
     * @param  array<string, ProductMaster>  $masters
     * @return array<string, ProductVariant>
     */
    private function seedProductVariants(array $masters): array
    {
        // slug => [masterSlug, identifier, vintageYear|null, nonVintage, state, notesEn]
        $rows = [
            'rc-2018' => ['rc', '2018', 2018, false, LifecycleState::Active, 'Floral, ethereal, endless on the palate.'],
            'rc-2019' => ['rc', '2019', 2019, false, LifecycleState::Active, 'Dense and structured, built for the cellar.'],
            'rc-2020' => ['rc', '2020', 2020, false, LifecycleState::Reviewed, 'Concentrated and saline, in review.'],
            'latache-2018' => ['latache', '2018', 2018, false, LifecycleState::Active, 'Spiced dark fruit with velvet tannins.'],
            'latache-2019' => ['latache', '2019', 2019, false, LifecycleState::Active, 'Powerful and brooding, deeply perfumed.'],
            'margaux-2015' => ['margaux', '2015', 2015, false, LifecycleState::Active, 'A great Margaux vintage, silky and complete.'],
            'margaux-2016' => ['margaux', '2016', 2016, false, LifecycleState::Active, 'Precise and classic, fine-grained tannins.'],
            'margaux-2018' => ['margaux', '2018', 2018, false, LifecycleState::Active, 'Opulent yet poised, long ageing ahead.'],
            'krug-171' => ['krug-gc', '171ème Édition', null, true, LifecycleState::Active, 'Based on the 2015 harvest with deep reserves.'],
            'krug-172' => ['krug-gc', '172ème Édition', null, true, LifecycleState::Active, 'Toasty, citrus-driven, finely mousse.'],
            'barbaresco-2017' => ['barbaresco', '2017', 2017, false, LifecycleState::Active, 'Warm vintage, ripe red cherry and rose.'],
            'barbaresco-2019' => ['barbaresco', '2019', 2019, false, LifecycleState::Active, 'Classic structure, tar and dried roses.'],
            'sassicaia-2019' => ['sassicaia', '2019', 2019, false, LifecycleState::Active, 'Cassis and Mediterranean herbs, refined.'],
            'sassicaia-2020' => ['sassicaia', '2020', 2020, false, LifecycleState::Active, 'Elegant and fresh, graphite edge.'],
            'unico-2012' => ['unico', '2012', 2012, false, LifecycleState::Active, 'Sweet spice and leather, fully resolved.'],
            'grange-2018' => ['grange', '2018', 2018, false, LifecycleState::Reviewed, 'Blockbuster Shiraz, in catalog review.'],
            'montrachet-2020' => ['montrachet', '2020', 2020, false, LifecycleState::Draft, 'Draft entry pending producer onboarding.'],
        ];

        $variants = [];

        foreach ($rows as $slug => [$masterSlug, $identifier, $vintageYear, $nonVintage, $state, $notesEn]) {
            $variant = ProductVariant::create([
                'product_master_id' => $masters[$masterSlug]->id,
                'variant_identifier' => $identifier,
                'lifecycle_state' => $state,
            ]);

            $variant->wineAttributes()->create([
                'vintage_year' => $vintageYear,
                'non_vintage' => $nonVintage,
                'tasting_notes' => TranslatableText::of(['en' => $notesEn]),
            ]);

            $variants[$slug] = $variant;
        }

        return $variants;
    }

    /**
     * Product References = Variant × Format (the immutable identity pair). Active
     * references sit on active variants and active formats; the review/draft branch
     * stays out of the active set.
     *
     * @param  array<string, ProductVariant>  $variants
     * @param  array<string, Format>  $formats
     * @return array<string, ProductReference>
     */
    private function seedProductReferences(array $variants, array $formats): array
    {
        // slug => [variantSlug, formatSlug, state]
        $rows = [
            'rc-2018-bottle' => ['rc-2018', 'bottle', LifecycleState::Active],
            'rc-2018-magnum' => ['rc-2018', 'magnum', LifecycleState::Active],
            'rc-2019-bottle' => ['rc-2019', 'bottle', LifecycleState::Active],
            'latache-2018-bottle' => ['latache-2018', 'bottle', LifecycleState::Active],
            'latache-2019-bottle' => ['latache-2019', 'bottle', LifecycleState::Active],
            'margaux-2015-bottle' => ['margaux-2015', 'bottle', LifecycleState::Active],
            'margaux-2015-magnum' => ['margaux-2015', 'magnum', LifecycleState::Active],
            'margaux-2016-bottle' => ['margaux-2016', 'bottle', LifecycleState::Active],
            'margaux-2018-bottle' => ['margaux-2018', 'bottle', LifecycleState::Active],
            'margaux-2018-dmagnum' => ['margaux-2018', 'double-magnum', LifecycleState::Active],
            'krug-171-bottle' => ['krug-171', 'bottle', LifecycleState::Active],
            'krug-172-bottle' => ['krug-172', 'bottle', LifecycleState::Active],
            'barbaresco-2017-bottle' => ['barbaresco-2017', 'bottle', LifecycleState::Active],
            'barbaresco-2019-bottle' => ['barbaresco-2019', 'bottle', LifecycleState::Active],
            'sassicaia-2019-bottle' => ['sassicaia-2019', 'bottle', LifecycleState::Active],
            'sassicaia-2019-magnum' => ['sassicaia-2019', 'magnum', LifecycleState::Active],
            'sassicaia-2020-bottle' => ['sassicaia-2020', 'bottle', LifecycleState::Active],
            'unico-2012-bottle' => ['unico-2012', 'bottle', LifecycleState::Active],
            'unico-2012-half' => ['unico-2012', 'half', LifecycleState::Active],
            'grange-2018-bottle' => ['grange-2018', 'bottle', LifecycleState::Reviewed],
        ];

        $references = [];

        foreach ($rows as $slug => [$variantSlug, $formatSlug, $state]) {
            $references[$slug] = ProductReference::create([
                'product_variant_id' => $variants[$variantSlug]->id,
                'format_id' => $formats[$formatSlug]->id,
                'lifecycle_state' => $state,
            ]);
        }

        return $references;
    }

    /**
     * Sellable SKUs = Product Reference × Case Configuration, with a human commercial
     * name and (for some) marketing copy. The same PR can sell under several case
     * configurations.
     *
     * @param  array<string, ProductReference>  $references
     * @param  array<string, CaseConfiguration>  $cases
     */
    private function seedSellableSkus(array $references, array $cases): void
    {
        // [referenceSlug, caseSlug, commercialName, marketingCopy|null, state]
        $rows = [
            ['rc-2018-bottle', 'owc6', 'Romanée-Conti Grand Cru 2018 — OWC 6', 'The pinnacle of Burgundy in its original wooden case of six.', LifecycleState::Active],
            ['rc-2018-bottle', 'loose', 'Romanée-Conti Grand Cru 2018 — Single Bottle', null, LifecycleState::Active],
            ['rc-2018-magnum', 'loose', 'Romanée-Conti Grand Cru 2018 — Magnum', 'A single magnum for the long cellar.', LifecycleState::Active],
            ['rc-2019-bottle', 'owc6', 'Romanée-Conti Grand Cru 2019 — OWC 6', null, LifecycleState::Active],
            ['latache-2018-bottle', 'loose', 'La Tâche Grand Cru 2018 — Single Bottle', null, LifecycleState::Active],
            ['latache-2019-bottle', 'owc6', 'La Tâche Grand Cru 2019 — OWC 6', null, LifecycleState::Active],
            ['margaux-2015-bottle', 'owc12', 'Château Margaux 2015 — OWC 12', 'A full original case of a benchmark Margaux vintage.', LifecycleState::Active],
            ['margaux-2016-bottle', 'owc6', 'Château Margaux 2016 — OWC 6', null, LifecycleState::Active],
            ['margaux-2018-bottle', 'owc6', 'Château Margaux 2018 — OWC 6', null, LifecycleState::Active],
            ['margaux-2018-dmagnum', 'loose', 'Château Margaux 2018 — Double Magnum', 'A statement format for collectors.', LifecycleState::Active],
            ['krug-171-bottle', 'owc6', 'Krug Grande Cuvée 171ème Édition — OWC 6', 'Multi-vintage prestige Champagne by the case.', LifecycleState::Active],
            ['krug-172-bottle', 'loose', 'Krug Grande Cuvée 172ème Édition — Single Bottle', null, LifecycleState::Active],
            ['barbaresco-2017-bottle', 'carton6', 'Gaja Barbaresco 2017 — Carton 6', null, LifecycleState::Active],
            ['barbaresco-2019-bottle', 'owc6', 'Gaja Barbaresco 2019 — OWC 6', null, LifecycleState::Active],
            ['sassicaia-2019-bottle', 'owc6', 'Sassicaia 2019 — OWC 6', 'The original Super Tuscan in its wooden six-pack.', LifecycleState::Active],
            ['sassicaia-2019-magnum', 'loose', 'Sassicaia 2019 — Magnum', null, LifecycleState::Active],
            ['sassicaia-2020-bottle', 'owc6', 'Sassicaia 2020 — OWC 6', null, LifecycleState::Active],
            ['unico-2012-bottle', 'owc6', 'Vega Sicilia Único 2012 — OWC 6', null, LifecycleState::Active],
            ['unico-2012-half', 'loose', 'Vega Sicilia Único 2012 — Half Bottle', null, LifecycleState::Active],
            ['grange-2018-bottle', 'carton6', 'Penfolds Grange 2018 — Carton 6', 'Pending catalog review.', LifecycleState::Reviewed],
        ];

        foreach ($rows as [$referenceSlug, $caseSlug, $commercialName, $marketingCopy, $state]) {
            SellableSku::create([
                'product_reference_id' => $references[$referenceSlug]->id,
                'case_configuration_id' => $cases[$caseSlug]->id,
                'commercial_name' => $commercialName,
                'marketing_copy' => $marketingCopy,
                'lifecycle_state' => $state,
            ]);
        }
    }

    /**
     * Composite (bundle) SKUs — ordered sets of ≥2 Product References. One vertical,
     * one cross-vintage trio, and a two-bottle duo left in review.
     *
     * @param  array<string, ProductReference>  $references
     */
    private function seedCompositeSkus(array $references): void
    {
        // [state, [referenceSlug ordered...]]
        $rows = [
            [LifecycleState::Active, ['rc-2018-bottle', 'latache-2018-bottle', 'rc-2019-bottle']],
            [LifecycleState::Active, ['margaux-2015-bottle', 'margaux-2016-bottle', 'margaux-2018-bottle']],
            [LifecycleState::Reviewed, ['sassicaia-2019-bottle', 'sassicaia-2020-bottle']],
        ];

        foreach ($rows as [$state, $constituentSlugs]) {
            $composite = CompositeSku::create([
                'lifecycle_state' => $state,
            ]);

            $attach = [];
            foreach ($constituentSlugs as $position => $slug) {
                $attach[$references[$slug]->id] = ['position' => $position + 1];
            }

            $composite->constituents()->attach($attach);
        }
    }

    /**
     * The SoD / rejection walkthrough fixture (RM-07): ONE Product Master built through the REAL Catalog
     * domain actions, so it carries genuine creator/reviewer lineage — the Catalog `ApprovalGovernance` floor
     * reads the creator from the entity's `ProductMasterCreated` event and the reviewer from its submit audit
     * row. Created as `creator@newco.test`, submitted as `reviewer@newco.test`, left in `reviewed`; only a
     * DISTINCT third operator (`approver@newco.test`) can then activate it — the self-approval block Paolo's
     * walkthrough proves. A directly-seeded `reviewed` row would carry no lineage, so any actor could activate
     * it and the SoD floor would prove nothing.
     *
     * Placed under an active-projected producer (DRC) with an identity distinct from the direct-seeded Masters,
     * so the create-time BR-Identity-1 dedup passes and a live activation clears the producer gate. A seeder
     * has no authenticated operator guard, so actor provenance is supplied via {@see ActorContext::runAs()}.
     *
     * @param  array<string, Producer>  $producers
     */
    private function seedSodReviewScenario(array $producers): void
    {
        $actor = app(ActorContext::class);
        $producerId = $producers['drc']->id;

        // firstOrFail (not value('id')): reads the typed `int` primary key and guarantees the chained
        // OperatorDemoSeeder actually provisioned the persona — a missing login fails loud, never actor_id 0.
        $creatorId = Operator::query()->where('email', 'creator@newco.test')->firstOrFail()->id;
        $reviewerId = Operator::query()->where('email', 'reviewer@newco.test')->firstOrFail()->id;

        // Create as the creator (records ProductMasterCreated with the creator's actor_id), then submit as a
        // DISTINCT reviewer (records the draft → reviewed audit row with the reviewer's actor_id).
        $master = $actor->runAs(ActorRole::NewcoOps, $creatorId, fn () => app(CreateProductMaster::class)->handle(
            name: self::SOD_FIXTURE_MASTER_NAME,
            producerId: $producerId,
            appellation: 'Échézeaux',
            region: 'Côte de Nuits',
            wineryStory: TranslatableText::of([
                'en' => 'Grand Cru neighbour of the DRC monopoles — the reviewable fixture for the approval walkthrough.',
                'it' => 'Grand Cru confinante con i monopoli DRC — la scheda in revisione per il walkthrough di approvazione.',
            ]),
        ));

        $actor->runAs(ActorRole::NewcoOps, $reviewerId, fn () => app(SubmitProductMasterForReview::class)->handle($master));
    }

    /**
     * The Producer activation separation-of-duties walkthrough fixture (RM-08, parties-producer-approval-sod):
     * ONE Producer built through the REAL {@see CreateProducer} action, so it carries genuine creator lineage —
     * the Parties separation-of-duties floor (`ProducerApprovalGovernance`) reads the creator from the entity's
     * `ProducerCreated` event. Created as `creator@newco.test` and left `draft` + KYC-cleared (`verified`); only a
     * DISTINCT operator (`approver@newco.test`) can then activate it — the self-approval block the walkthrough proves.
     *
     * The Producer FSM is linear (`draft → active → retired`) with NO reviewer leg, so this is the 2-step
     * Creator → Approver depth — unlike the Catalog Master's 3-step review flow ({@see seedSodReviewScenario}).
     * A directly-seeded `draft` row (e.g. the Leflaive demo producer, seeded via plain `create()`) carries no
     * lineage, so any operator could activate it and the SoD floor would prove nothing — the fixture MUST go
     * through the real action.
     *
     * A seeder has no authenticated operator guard, so actor provenance is supplied via {@see ActorContext::runAs()}.
     * KYC is then set directly to `verified` (audit-only, § 4.4 — {@see CreateProducer} takes no KYC param and
     * leaves it NULL, which also clears; `verified` reads legibly in the demo), so the SOLE block on the creator's
     * self-activation is the SoD floor, not the KYC gate.
     */
    private function seedProducerApprovalScenario(): void
    {
        $actor = app(ActorContext::class);

        // firstOrFail (not value('id')): reads the typed `int` primary key and guarantees the chained
        // OperatorDemoSeeder actually provisioned the persona — a missing login fails loud, never actor_id 0.
        $creatorId = Operator::query()->where('email', 'creator@newco.test')->firstOrFail()->id;

        // Create as the creator (records ProducerCreated with the creator's actor_id — the lineage the floor reads
        // back); the Producer is born `draft`. Only a DISTINCT operator (approver@newco.test) can then activate it.
        $producer = $actor->runAs(ActorRole::NewcoOps, $creatorId, fn () => app(CreateProducer::class)->handle(
            name: self::SOD_FIXTURE_PRODUCER_NAME,
            region: 'Côte de Nuits',
            country: 'France',
            appellation: 'Vosne-Romanée Grand Cru',
            description: TranslatableText::of([
                'en' => 'Legendary Vosne-Romanée domaine — the draft producer for the activation approval walkthrough.',
                'it' => 'Leggendario dominio di Vosne-Romanée — il produttore in bozza per il walkthrough di approvazione.',
            ]),
        ));

        // KYC-cleared so the separation-of-duties floor is the SOLE block on the creator's self-activation (a
        // direct, audit-only set, § 4.4 — the console requireKyc/verifyKyc path is proven in ProducerConsoleChainTest).
        $producer->update(['kyc_status' => KycStatus::Verified]);
    }

    /**
     * Print a row-count summary (the seeder is always driven by the `db:seed`
     * command, which injects `$this->command`).
     */
    private function summarize(): void
    {
        $this->command->info('Demo data seeded:');
        $this->command->table(
            ['Table', 'Rows'],
            [
                ['Operators (logins)', (string) Operator::query()->count()],
                ['Producers', (string) Producer::query()->count()],
                ['Clubs', (string) Club::query()->count()],
                ['Producer agreements', (string) ProducerAgreement::query()->count()],
                ['Suppliers', (string) Supplier::query()->count()],
                ['Customers', (string) Customer::query()->count()],
                ['Accounts', (string) Account::query()->count()],
                ['Profiles (memberships)', (string) Profile::query()->count()],
                ['Club credits', (string) ClubCredit::query()->count()],
                ['Holds', (string) Hold::query()->count()],
                ['Producer states', (string) ProducerState::query()->count()],
                ['Formats', (string) Format::query()->count()],
                ['Case configurations', (string) CaseConfiguration::query()->count()],
                ['Product masters', (string) ProductMaster::query()->count()],
                ['Product variants', (string) ProductVariant::query()->count()],
                ['Product references', (string) ProductReference::query()->count()],
                ['Sellable SKUs', (string) SellableSku::query()->count()],
                ['Composite SKUs', (string) CompositeSku::query()->count()],
            ],
        );
    }
}
