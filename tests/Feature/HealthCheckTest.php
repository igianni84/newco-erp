<?php

use function Pest\Laravel\get;

it('responds healthy on /up', function () {
    get('/up')->assertStatus(200);
});

it('does not blanket-respond on unknown paths', function () {
    get('/up/nope')->assertNotFound();
});
