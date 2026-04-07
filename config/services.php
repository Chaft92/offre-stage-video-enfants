<?php

return [

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL'),
        'secret'      => env('N8N_WEBHOOK_SECRET'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'elevenlabs' => [
        'api_key'  => env('ELEVENLABS_API_KEY'),
        'voice_id' => env('ELEVENLABS_VOICE_ID', 'EXAVITQu4vr4xnSDxMaL'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model'   => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    ],

    'runway' => [
        'api_key'  => env('RUNWAYML_API_SECRET'),
        'base_url' => env('RUNWAYML_BASE_URL', 'https://api.dev.runwayml.com'),
        'version'  => env('RUNWAYML_API_VERSION', '2024-11-06'),
        'model'    => env('RUNWAYML_VIDEO_MODEL', 'gen4_turbo'),
        'ratio'    => env('RUNWAYML_VIDEO_RATIO', '1280:720'),
        'duration' => (int) env('RUNWAYML_VIDEO_DURATION', 5),
    ],

];
