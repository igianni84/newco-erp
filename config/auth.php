<?php

use App\Modules\OperatorPanel\Models\Operator;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'operator'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'operators'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        // The operator login principal authenticates here (operator-auth-foundation, design D2) and this is
        // the application default guard (operator-auth-foundation 6.1) — the sole authenticatable at launch;
        // the customer/producer guards are deferred to the Module S / TanStack gate.
        //
        // NOTE: Laravel deep-merges the framework's base config/auth.php UNDER this file for the `guards`,
        // `providers` and `passwords` keys (Foundation\Bootstrap\LoadConfiguration::mergeableOptions), so the
        // framework's default `web` guard + `users` provider/broker still appear in the merged config and
        // cannot be removed from here. They are inert — the default guard is `operator` and no code resolves
        // `web` — but a future guard slice should expect them when introspecting config('auth.*').
        'operator' => [
            'driver' => 'session',
            'provider' => 'operators',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        // Resolves the Operator principal for the `operator` guard (operator-auth-foundation, design D2).
        // The sole provider (operator-auth-foundation 6.1); `AUTH_MODEL` defaults to the Operator model.
        'operators' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', Operator::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        // Operator password-reset broker (operator-auth-foundation, design D2). The default broker
        // (operator-auth-foundation 6.1). Uses the generic `password_reset_tokens` table (retained at
        // launch — a single authenticatable; design D1).
        'operators' => [
            'provider' => 'operators',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
