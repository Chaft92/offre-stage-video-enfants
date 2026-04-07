<?php

namespace App\Services;

class PollinationsVideoService
{
    /**
     * Check if an API key is configured.
     */
    public function hasApiKey(): bool
    {
        return ! empty((string) config('services.pollinations_video.api_key', ''));
    }

    /**
     * Check if video generation is enabled (requires API key + feature toggle).
     */
    public function videoEnabled(): bool
    {
        if (! filter_var(config('services.pollinations_video.enabled', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return $this->hasApiKey();
    }

    /**
     * Build the real Pollinations video URL (contains API key — server-side only).
     */
    public function buildRealVideoUrl(string $prompt, int $seed): ?string
    {
        if (! $this->videoEnabled()) {
            return null;
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return null;
        }

        $prompt = mb_substr($prompt, 0, 800);
        $model = (string) config('services.pollinations_video.model', 'ltx-2');
        $apiKey = (string) config('services.pollinations_video.api_key', '');

        $encodedPrompt = rawurlencode($prompt);

        $params = [
            'model' => $model,
            'seed'  => $seed,
            'key'   => $apiKey,
        ];

        $duration = (int) config('services.pollinations_video.duration', 5);
        if ($duration > 0) {
            $params['duration'] = $duration;
        }

        $aspectRatio = (string) config('services.pollinations_video.aspect_ratio', '');
        if ($aspectRatio !== '') {
            $params['aspectRatio'] = $aspectRatio;
        }

        $query = http_build_query($params);

        return "https://gen.pollinations.ai/video/{$encodedPrompt}?{$query}";
    }

    /**
     * Build the real Pollinations image URL (contains API key — server-side only).
     */
    public function buildRealImageUrl(string $prompt, int $seed, int $width = 1280, int $height = 720): string
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            $prompt = 'colorful animated scene';
        }

        $prompt = mb_substr($prompt, 0, 500);
        $encodedPrompt = rawurlencode($prompt);

        $params = [
            'width'  => $width,
            'height' => $height,
            'nologo' => 'true',
            'seed'   => $seed,
        ];

        $apiKey = (string) config('services.pollinations_video.api_key', '');
        if ($apiKey !== '') {
            $params['key'] = $apiKey;
        }

        $query = http_build_query($params);

        return "https://gen.pollinations.ai/image/{$encodedPrompt}?{$query}";
    }

    /**
     * Extract the visual prompt from a scene.
     */
    public function extractPrompt(array $scene): string
    {
        $prompt = trim((string) ($scene['visual_description'] ?? ''));
        if ($prompt === '') {
            $prompt = trim((string) ($scene['narration'] ?? ''));
        }
        return $prompt;
    }
}
