<?php

return [
    // Flat fee added once when an invoice becomes overdue (RM).
    // KUNCIA_LATE_FEE is the new name; SEWAHUB_LATE_FEE still works so an existing server .env keeps its value.
    'late_fee' => (float) env('KUNCIA_LATE_FEE', env('SEWAHUB_LATE_FEE', 20)),

    // Public sign-up. On the family server this is false: accounts are created with `php artisan kuncia:create-landlord`.
    'registration' => (bool) env('KUNCIA_REGISTRATION', true),

    // Guest sandbox for recruiters: its own sample data, reset nightly. Never touches other landlords.
    'guest' => [
        'enabled' => (bool) env('KUNCIA_GUEST', false),
        'email' => env('KUNCIA_GUEST_EMAIL', 'guest@kuncia.test'),
        'password' => env('KUNCIA_GUEST_PASSWORD', 'guest1234'),
        'tenant_email' => 'guest.tenant@kuncia.test',
        'tech_email' => 'guest.tech@kuncia.test',
    ],
];
