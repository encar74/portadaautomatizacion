<?php

return [
    'disk' => env('PRESS_RELEASES_DISK', 'press-releases-s3'),
    'queue' => env('PRESS_RELEASES_QUEUE', 'default'),
    'max_raw_message_bytes' => (int) env('PRESS_RELEASES_MAX_EMAIL_BYTES', 25 * 1024 * 1024),
    'max_attachment_bytes' => (int) env('PRESS_RELEASES_MAX_ATTACHMENT_BYTES', 15 * 1024 * 1024),
    'max_attachments' => (int) env('PRESS_RELEASES_MAX_ATTACHMENTS', 20),
    'max_extracted_text_chars' => (int) env('PRESS_RELEASES_MAX_EXTRACTED_TEXT_CHARS', 200000),
    'max_source_text_chars' => (int) env('PRESS_RELEASES_MAX_SOURCE_TEXT_CHARS', 500000),
    'allowed_mime_types' => [
        'application/pdf' => ['extension' => 'pdf', 'type' => 'document'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['extension' => 'docx', 'type' => 'document'],
        'image/jpeg' => ['extension' => 'jpg', 'type' => 'image'],
        'image/png' => ['extension' => 'png', 'type' => 'image'],
        'image/webp' => ['extension' => 'webp', 'type' => 'image'],
    ],
];
