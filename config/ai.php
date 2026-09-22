<?php

return [
    'provider' => env('AI_PROVIDER', 'openai'),
    'generation_enabled' => (bool) env('AI_GENERATION_ENABLED', false),
    'models' => [
        'classification' => env('AI_MODEL_CLASSIFICATION'),
        'generation' => env('AI_MODEL_GENERATION'),
        'validation' => env('AI_MODEL_VALIDATION'),
    ],
    'prompt_version' => env('AI_PROMPT_VERSION', 'v2'),
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4000),
    'max_source_chars' => (int) env('AI_MAX_SOURCE_CHARS', 120000),
    'timeout' => (int) env('AI_TIMEOUT', 120),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],
];
