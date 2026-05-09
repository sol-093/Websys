<?php

declare(strict_types=1);

return [
    'branding' => [
        'app_name' => 'INVOLVE',
        'favicon' => 'uploads/assets/involvemoblight.png',
        'logo_light' => 'uploads/assets/involvelogo dark.png',
        'logo_dark' => 'uploads/assets/involvelogo light.png',
        'mail_from_name' => 'INVOLVE',
    ],
    'uploads' => [
        'max_receipt_bytes' => 5 * 1024 * 1024,
        'max_image_bytes' => 3 * 1024 * 1024,
        'max_generic_bytes' => 8 * 1024 * 1024,
        'image_max_width' => 4096,
        'image_max_height' => 4096,
        'allowed_receipt_mimes' => [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'pdf' => ['application/pdf'],
        ],
    ],
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
    'pdf' => [
        'transaction_template' => 'uploads/assets/pdftemplate.png',
    ],
    'security' => [
        'csp' => "default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com data:; connect-src 'self' https://accounts.google.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
        'permissions_policy' => 'camera=(), microphone=(), geolocation=()',
        'rate_limits' => [
            'search' => ['attempts' => 30, 'window' => 60],
            'export' => ['attempts' => 10, 'window' => 300],
            'login' => ['attempts' => 5, 'window' => 300],
            'register' => ['attempts' => 5, 'window' => 300],
            'password_reset' => ['attempts' => 3, 'window' => 3600],
            'verification_resend' => ['attempts' => 3, 'window' => 3600],
        ],
    ],
    'features' => [
        'query_profiler' => false,
        'api' => true,
        'file_cache' => true,
    ],
];
