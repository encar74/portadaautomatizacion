<?php

return [
    'provider' => env('MAILBOX_PROVIDER'),

    'gmail' => [
        'client_id' => env('GMAIL_CLIENT_ID'),
        'client_secret' => env('GMAIL_CLIENT_SECRET'),
        'refresh_token' => env('GMAIL_REFRESH_TOKEN'),
        'user_id' => env('GMAIL_USER_ID', 'me'),
        'query' => env('GMAIL_QUERY', 'in:inbox -label:portada-importado'),
        'imported_label' => env('GMAIL_IMPORTED_LABEL', 'portada-importado'),
        'max_results' => (int) env('GMAIL_MAX_RESULTS', 50),
    ],
];
