<?php

return [
    'name' => env('SCOUT_NAME', 'Ifthithaah Scout Group'),
    'short_name' => env('SCOUT_SHORT_NAME', 'Scout Management System'),
    'vapid_public_key' => env('VAPID_PUBLIC_KEY'),
    'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
    'organisation' => env('SCOUT_ORG', 'Ifthithaah Scout Group'),
    'certificate_prefix' => env('SCOUT_CERT_PREFIX', 'FLHSG'),
    'timezone' => env('SCOUT_TIMEZONE', 'Indian/Maldives'),
    'currency' => env('SCOUT_CURRENCY', 'MVR'),
    'currency_symbol' => env('SCOUT_CURRENCY_SYMBOL', 'MVR'),
    'default_class_fee' => env('SCOUT_DEFAULT_CLASS_FEE', '50.00'),
    'proof_max_kb' => (int) env('SCOUT_PROOF_MAX_KB', 10240),
    'shop_image_max_kb' => (int) env('SCOUT_SHOP_IMAGE_MAX_KB', 5120),

    /*
     * Development-only first admin, used by the demo seeder in local/testing.
     */
    'admin' => [
        'national_id' => env('SCOUT_ADMIN_NATIONAL_ID', 'A000001'),
        'pin' => env('SCOUT_ADMIN_PIN'),
        'name' => env('SCOUT_ADMIN_NAME', 'System Administrator'),
    ],
];
