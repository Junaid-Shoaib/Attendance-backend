<?php

return [
    'mail' => [
        'domain' => env('MAIL_DOMAIN'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],
];
