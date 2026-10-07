<?php

test('home redirects to the dashboard', function () {
    $this->get(route('home'))->assertRedirect('/dashboard');
});

test('guests end up on the login page', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});
