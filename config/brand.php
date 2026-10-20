<?php

// Single source for the product brand. Change here if the name changes after trademark checks.
return [
    'name' => env('BRAND_NAME', 'ReemoPOS'),
    'short_name' => env('BRAND_SHORT_NAME', 'Reemo'),
    'tagline' => 'ขายได้ทุกที่ แม้เน็ตหลุด',
    'primary_color' => env('BRAND_PRIMARY_COLOR', '#7c3aed'),
    'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@reemopos.com'),
    'domain' => env('BRAND_DOMAIN', 'reemopos.com'),
    'trial_days' => 14,
    'trial_plan' => 'pro',
];
