<?php

use App\Models\User;

it('redirects guests from the home page to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

it('redirects signed-in users to the desktop', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
