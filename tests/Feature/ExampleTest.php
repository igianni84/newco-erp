<?php

use function Pest\Laravel\get;

test('the application returns a successful response', function () {
    get('/')->assertStatus(200);
});
