<?php

namespace App\Modules\OperatorPanel\Models;

use Carbon\CarbonInterface;
use Database\Factories\OperatorFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

/**
 * Operator — the operator login principal owned by the OperatorPanel module (operator-auth-foundation,
 * design D1/D2/D3; decisions/2026-06-15-identity-auth.md). For an operator the login principal IS the
 * acting identity: an Operator references no Module K party row. It replaces the bootstrap `User` model,
 * which is removed at cleanup task 6.1 — built ALONGSIDE it (cutover discipline D1), the
 * two-authenticatable state is transient scaffolding, never the end state.
 *
 * Authenticates on the dedicated `operator` session guard (task 2.3) and reaches the Filament `/admin`
 * panel via {@see FilamentUser::canAccessPanel()}, which returns true for ANY Operator at launch: only
 * operators authenticate on this guard, so "is an Operator" IS the access rule. The authority-tier
 * refinement (role → panel/resource access) is deferred (proposal slice-boundary table,
 * feedback_prd_rr_approval / design D2).
 *
 * RBAC mechanism: {@see HasRoles} (design D4), operator-scoped via roles seeded with `guard_name = operator`
 * (task 5.1). This change wires the mechanism only; the role → capability policy is deferred.
 *
 * Opt-in TOTP 2FA (design D3, founder decision 2026-06-15): implements {@see HasAppAuthentication} /
 * {@see HasAppAuthenticationRecovery} over the `app_authentication_secret` (`encrypted`) and
 * `app_authentication_recovery_codes` (`encrypted:array`) columns — stored as ciphertext at rest, so the
 * accessors hand back the decrypted secret / code array. Both columns are nullable: 2FA is per-operator
 * opt-in (an un-enrolled operator carries NULLs); enrolment enforcement is deferred to the Architectural
 * Security Review gate.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonInterface|null $email_verified_at
 * @property string $password
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 * @property string|null $remember_token
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class Operator extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<OperatorFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The factory is namespaced outside the `App\Models` convention (the model lives under the OperatorPanel
     * module), so the model names it explicitly — name-based resolution would mis-target it — and the
     * explicit return type lets static analysis infer the model for `Operator::factory()->create()`.
     */
    protected static function newFactory(): OperatorFactory
    {
        return OperatorFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Only operators authenticate on the `operator` guard, so being an Operator IS the panel-access
        // rule at launch. The authority-tier refinement (role → access) is deferred (design D2).
        return true;
    }

    // --- Filament MFA: HasAppAuthentication (opt-in TOTP, design D3) ---

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    // --- Filament MFA: HasAppAuthenticationRecovery (recovery codes, design D3) ---

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  ?array<string>  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
