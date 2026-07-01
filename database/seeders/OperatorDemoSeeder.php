<?php

namespace Database\Seeders;

use App\Modules\OperatorPanel\Models\Operator;
use Illuminate\Database\Seeder;

/**
 * Seeds the DEMO operator logins that make the separation-of-duties (SoD) and rejection walkthroughs
 * demoable in the Filament console (RM-07; docs/validation/README.md env-readiness ask #2).
 *
 * Three DISTINCT logins, because the SoD floor keys on distinct actor IDENTITY, not role possession
 * (Catalog `ApprovalGovernance`, design D6): the single env-driven bootstrap operator can never approve
 * its own entity, so a Creator → Reviewer → Approver flow needs three separate principals. Each persona
 * carries ONE authority-tier role for a legible walkthrough; role possession is not yet a capability gate
 * (bare roles, no role→capability map — {@see RoleSeeder}, design D4), so identity alone enforces SoD today.
 *
 * NOT part of the bootstrap {@see DatabaseSeeder}: these are hardcoded-password demo logins and must never
 * reach production. They are reached through {@see DemoSeeder} (which chains RoleSeeder → OperatorDemoSeeder
 * and refuses to run in production), or explicitly via:
 *
 *   php artisan db:seed --class=Database\\Seeders\\OperatorDemoSeeder   (RoleSeeder must have run first)
 */
class OperatorDemoSeeder extends Seeder
{
    /**
     * The demo operator personas: email => [display name, single authority-tier role].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const OPERATORS = [
        'creator@newco.test' => ['Demo · Creator', 'Creator'],
        'reviewer@newco.test' => ['Demo · Reviewer', 'Reviewer'],
        'approver@newco.test' => ['Demo · Approver', 'Approver'],
    ];

    /**
     * The shared demo password. A KNOWN, hardcoded credential is intentional — these logins exist only to
     * make the walkthroughs demoable and never run in production ({@see DemoSeeder} aborts there). The
     * Operator model's `password` cast ('hashed') hashes this plaintext on write.
     */
    public const PASSWORD = 'password';

    /**
     * Seed the demo operator logins and grant each its single authority-tier role on the `operator` guard.
     *
     * Runs AFTER RoleSeeder (DemoSeeder chains them in that order) so `syncRoles` resolves against existing
     * rows. Idempotent: keyed on email, re-running updates the same operator and — via `syncRoles` — restores
     * exactly its one intended role with no duplicate `model_has_roles` grant.
     */
    public function run(): void
    {
        foreach (self::OPERATORS as $email => [$name, $role]) {
            $operator = Operator::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::PASSWORD],
            );

            // syncRoles (not assignRole): idempotent AND authoritative — restores exactly the one intended
            // role each run. Names resolve on the `operator` guard (the only guard whose provider is Operator).
            $operator->syncRoles([$role]);
        }
    }
}
