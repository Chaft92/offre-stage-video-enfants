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
        'model'    => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),
        'voices'   => [
            'narratrice'    => env('ELEVENLABS_VOICE_NARRATRICE_ID', env('ELEVENLABS_VOICE_ID', 'EXAVITQu4vr4xnSDxMaL')),
            'narrateur'     => env('ELEVENLABS_VOICE_NARRATEUR_ID', 'ErXwobaYiN019PkySvjV'),
            'enfant_fille'  => env('ELEVENLABS_VOICE_ENFANT_FILLE_ID', 'jBpfuIE2acCO8z3wKNLl'),
            'enfant_garcon' => env('ELEVENLABS_VOICE_ENFANT_GARCON_ID', 'yoZ06aMxZJJ28mfd3POQ'),
        ],
        'settings' => [
            'stability'         => (float) env('ELEVENLABS_STABILITY', 0.38),
            'similarity_boost'  => (float) env('ELEVENLABS_SIMILARITY_BOOST', 0.82),
            'style'             => (float) env('ELEVENLABS_STYLE', 0.45),
            'use_speaker_boost' => filter_var(env('ELEVENLABS_SPEAKER_BOOST', true), FILTER_VALIDATE_BOOL),
        ],
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model'   => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    ],

    'pollinations_video' => [
        'enabled'      => filter_var(env('POLLINATIONS_VIDEO_ENABLED', true), FILTER_VALIDATE_BOOL),
        'api_key'      => env('POLLINATIONS_API_KEY'),
        'model'        => env('POLLINATIONS_VIDEO_MODEL', 'ltx-2'),
        'duration'     => (int) env('POLLINATIONS_VIDEO_DURATION', 5),
        'aspect_ratio' => env('POLLINATIONS_VIDEO_ASPECT_RATIO', ''),
    ],

];
