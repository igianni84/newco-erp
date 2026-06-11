<?php

use App\Models\User;
use Database\Seeders\OperatorSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'operator.name' => 'NewCo Operator',
        'operator.email' => 'operator@newco.test',
        'operator.password' => 'secret-operator-password',
    ]);
});

it('redirects unauthenticated visitors to the panel login', function () {
    get('/admin')->assertRedirect('/admin/login');
});

it('lets the seeded operator authenticate and reach the dashboard', function () {
    seed(OperatorSeeder::class);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'operator@newco.test',
            'password' => 'secret-operator-password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin');

    assertAuthenticated();

    get('/admin')->assertOk();
});

it('lets an authenticated operator account reach the dashboard', function () {
    actingAs(User::factory()->create());

    get('/admin')->assertOk();
});

it('rejects invalid credentials at the panel login', function () {
    seed(OperatorSeeder::class);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'operator@newco.test',
            'password' => 'not-the-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    assertGuest();
});
