<?php

return [
    'enabled' => (bool) env('WORDPRESS_ENABLED', false),
    'url' => env('WORDPRESS_URL'),
    'username' => env('WORDPRESS_USERNAME'),
    'application_password' => env('WORDPRESS_APPLICATION_PASSWORD'),
    'timeout' => (int) env('WORDPRESS_TIMEOUT', 30),
    'upload_images' => (bool) env('WORDPRESS_UPLOAD_IMAGES', true),
    'sync_taxonomies' => (bool) env('WORDPRESS_SYNC_TAXONOMIES', true),
];
