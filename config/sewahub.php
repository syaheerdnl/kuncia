<?php

return [
    // Flat fee added once when an invoice becomes overdue (RM).
    'late_fee' => (float) env('SEWAHUB_LATE_FEE', 20),
];
