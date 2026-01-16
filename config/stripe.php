<?php

return [
    'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
];
