<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Operator Account
    |--------------------------------------------------------------------------
    |
    | Credentials for the operator account created by OperatorSeeder. They
    | are supplied via environment variables (documented in .env.example)
    | and are never committed. All three values are required to seed.
    |
    */

    'name' => env('OPERATOR_NAME'),

    'email' => env('OPERATOR_EMAIL'),

    'password' => env('OPERATOR_PASSWORD'),

];
