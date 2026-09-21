<?php

return [
    'disk' => env('PRESS_RELEASES_DISK', 'press-releases-s3'),
    'max_raw_message_bytes' => (int) env('PRESS_RELEASES_MAX_EMAIL_BYTES', 25 * 1024 * 1024),
    'max_attachment_bytes' => (int) env('PRESS_RELEASES_MAX_ATTACHMENT_BYTES', 15 * 1024 * 1024),
    'max_attachments' => (int) env('PRESS_RELEASES_MAX_ATTACHMENTS', 20),
    'allowed_mime_types' => [
        'application/pdf' => ['extension' => 'pdf', 'type' => 'document'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['extension' => 'docx', 'type' => 'document'],
        'image/jpeg' => ['extension' => 'jpg', 'type' => 'image'],
        'image/png' => ['extension' => 'png', 'type' => 'image'],
        'image/webp' => ['extension' => 'webp', 'type' => 'image'],
    ],
];
