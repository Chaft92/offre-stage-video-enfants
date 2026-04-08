<?php

namespace App\Http\Controllers;

use App\Models\VideoProject;
use App\Services\PollinationsVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8NCallbackController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'                       => ['required', 'integer', 'exists:video_projects,id'],
            'video_url'                        => ['required', 'string', 'max:1000'],
            'story_text'                       => ['required', 'string', 'max:50000'],
            'moral'                            => ['nullable', 'string', 'max:1000'],
            'characters'                       => ['nullable', 'string', 'max:2000'],
            'setting'                          => ['nullable', 'string', 'max:2000'],
            'scenes_json'                      => ['required', 'array', 'min:1', 'max:25'],
            'scenes_json.*.scene_number'       => ['required', 'integer', 'min:1'],
            'scenes_json.*.narration'          => ['required', 'string'],
            'scenes_json.*.visual_description' => ['required', 'string'],
            'scenes_json.*.duration_seconds'   => ['required', 'integer', 'min:1', 'max:60'],
            'scenes_json.*.video_url'          => ['nullable', 'string', 'max:1000'],
            'scenes_json.*.voice'              => ['nullable', 'string', 'in:narratrice,narrateur,enfant_fille,enfant_garcon'],
            'scenes_json.*.image_prompt'       => ['nullable', 'string', 'max:1000'],
            'scenes_json.*.part'               => ['nullable', 'string', 'in:introduction,development,conclusion'],
        ]);

        $project = VideoProject::findOrFail($data['project_id']);

        $scenes = array_values($data['scenes_json']);
        usort($scenes, function ($a, $b) {
            return (int) ($a['scene_number'] ?? 0) <=> (int) ($b['scene_number'] ?? 0);
        });

        $scenes = $this->normalizeSceneCount($scenes, 8);
        $scenes = $this->enforceSceneQuality($scenes);

        if (! $this->hasValidSceneContract($scenes)) {
            $project->markFailed('Scenes incompletes recues depuis le pipeline.');
            return response()->json(['success' => false, 'message' => 'Invalid scenes payload.'], 422);
        }

        if ($project->isDone()) {
            $existingCount = count($project->scenes_json ?? []);
            if ($existingCount >= count($scenes)) {
                return response()->json(['success' => true, 'message' => 'Projet deja complete.']);
            }
        }

        $pollinationsVideo = app(PollinationsVideoService::class);
        $hasKey = $pollinationsVideo->hasApiKey();

        $characters = trim((string) ($data['characters'] ?? ''));
        $setting = trim((string) ($data['setting'] ?? ''));

        foreach ($scenes as $i => &$scene) {
            $scene['voice'] = $this->voiceForSceneIndex($i, (string) ($scene['part'] ?? 'development'), (string) ($scene['voice'] ?? ''));

            $sceneNum = (int) ($scene['scene_number'] ?? $i + 1);
            $imagePrompt = $this->buildImagePrompt($scene, $i, $characters, $setting);
            $seed = $project->id * 100 + $sceneNum;

            if ($hasKey) {
                $scene['image_url'] = "/video/{$project->id}/scene-image/{$sceneNum}";
            } else {
                $encodedPrompt = rawurlencode($imagePrompt);
                $scene['image_url'] = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width=1280&height=720&nologo=true&seed={$seed}";
            }

            $scene['fallback_image_url'] = "https://picsum.photos/seed/akv-{$project->id}-{$seed}/1280/720";
            $scene['image_prompt'] = $imagePrompt;
        }
        unset($scene);

        if ($pollinationsVideo->videoEnabled()) {
            $videoCount = 0;

            foreach ($scenes as $i => &$scene) {
                $prompt = $pollinationsVideo->extractPrompt($scene);

                if ($prompt !== '') {
                    $sceneNum = (int) ($scene['scene_number'] ?? $i + 1);
                    $scene['video_url'] = "/video/{$project->id}/clip/{$sceneNum}";
                    $scene['video_status'] = 'done';
                    $videoCount++;
                }
            }
            unset($scene);

            $this->preGenerateTTS($project->id, $scenes);

            $project->update([
                'status'        => 'done',
                'current_step'  => 5,
                'video_url'     => $videoCount > 0 ? 'scene-playlist' : 'slideshow',
                'story_text'    => $data['story_text'],
                'moral'         => $data['moral'] ?? null,
                'scenes_json'   => $scenes,
                'error_message' => null,
            ]);

            Log::info("Projet #{$project->id} termine - {$videoCount} videos Pollinations, " . count($scenes) . " scenes.", [
                'theme' => $project->theme,
            ]);

            return response()->json(['success' => true]);
        }

        $videoUrl = trim((string) ($data['video_url'] ?? ''));

        $project->update([
            'status'        => 'done',
            'current_step'  => 5,
            'video_url'     => $videoUrl !== '' ? $videoUrl : 'slideshow',
            'story_text'    => $data['story_text'],
            'moral'         => $data['moral'] ?? null,
            'scenes_json'   => $scenes,
            'error_message' => null,
        ]);

        Log::info("Projet #{$project->id} termine - " . count($scenes) . " scenes.", [
            'theme' => $project->theme,
        ]);

        return response()->json(['success' => true]);
    }

    public function error(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'    => ['required', 'integer', 'exists:video_projects,id'],
            'error_message' => ['required', 'string', 'max:2000'],
        ]);

        $project = VideoProject::findOrFail($data['project_id']);

        if ($project->isDone()) {
            return response()->json(['success' => true, 'message' => 'Projet deja complete.']);
        }
        $project->markFailed($data['error_message']);

        Log::error("Projet #{$project->id} en erreur.", ['error' => $data['error_message']]);

        return response()->json(['success' => true]);
    }

    public function step(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:video_projects,id'],
            'step'       => ['required', 'integer', 'min:0', 'max:5'],
        ]);

        VideoProject::where('id', $data['project_id'])
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status'       => 'processing',
                'current_step' => $data['step'],
            ]);

        return response()->json(['success' => true]);
    }

    private function hasValidSceneContract(array $scenes): bool
    {
        $count = count($scenes);
        if ($count !== 8) {
            return false;
        }

        $numbers = [];
        foreach ($scenes as $scene) {
            $number = (int) ($scene['scene_number'] ?? 0);
            if ($number < 1 || isset($numbers[$number])) {
                return false;
            }
            $numbers[$number] = true;
        }

        ksort($numbers);
        $expected = 1;
        foreach (array_keys($numbers) as $number) {
            if ($number !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    private function normalizeSceneCount(array $scenes, int $targetCount): array
    {
        $scenes = array_values($scenes);

        if (count($scenes) > $targetCount) {
            $scenes = array_slice($scenes, 0, $targetCount);
        }

        while (count($scenes) < $targetCount) {
            $index = count($scenes);
            $part = $index < 2 ? 'introduction' : ($index >= $targetCount - 2 ? 'conclusion' : 'development');
            $scenes[] = [
                'scene_number' => $index + 1,
                'part' => $part,
                'narration' => 'Les amis de l heroine echangent ensemble, apprennent a exprimer leurs emotions et construisent pas a pas une solution concrete. La scene reste lumineuse, claire et rassurante, avec un ton pedagogique et bienveillant pour les enfants.',
                'visual_description' => 'Cinematic family friendly animated scene with expressive faces, rich environment details, gentle light transitions and clear storytelling action.',
                'duration_seconds' => 20,
            ];
        }

        foreach ($scenes as $i => &$scene) {
            $scene['scene_number'] = $i + 1;
        }
        unset($scene);

        return $scenes;
    }

    private function enforceSceneQuality(array $scenes): array
    {
        foreach ($scenes as $i => &$scene) {
            $part = (string) ($scene['part'] ?? 'development');
            if (! in_array($part, ['introduction', 'development', 'conclusion'], true)) {
                $part = $i < 2 ? 'introduction' : ($i >= 6 ? 'conclusion' : 'development');
            }

            $narration = trim((string) ($scene['narration'] ?? ''));
            if (mb_strlen($narration) < 120) {
                $narration .= ' On decrit les emotions, les actions et les consequences de facon claire, avec des details concrets pour aider l enfant a comprendre et a retenir la lecon.';
            }

            $visual = trim((string) ($scene['visual_description'] ?? ''));
            if (mb_strlen($visual) < 80) {
                $visual = 'Highly detailed cinematic animated frame, expressive characters, emotional clarity, layered background, controlled depth of field, coherent props, realistic lighting, clean composition, storytelling focus.';
            }

            $scene['part'] = $part;
            $scene['narration'] = mb_substr($narration, 0, 900);
            $scene['visual_description'] = mb_substr($visual, 0, 1200);
            $scene['duration_seconds'] = max(15, min(23, (int) ($scene['duration_seconds'] ?? 20)));
        }
        unset($scene);

        return $scenes;
    }

    private function voiceForSceneIndex(int $index, string $part, string $groqVoice = ''): string
    {
        $validVoices = ['narratrice', 'narrateur', 'enfant_fille', 'enfant_garcon'];

        return 'narratrice';
    }

    private function buildImagePrompt(array $scene, int $index, string $characters = '', string $setting = ''): string
    {
        $base = trim((string) ($scene['image_prompt'] ?? ''));
        if ($base === '') {
            $base = trim((string) ($scene['visual_description'] ?? ''));
        }

        $base = preg_replace('/\s+/', ' ', $base ?? '') ?: '';
        $base = mb_substr($base, 0, 300);

        $charTag = '';
        if ($characters !== '') {
            $charTag .= mb_substr($characters, 0, 80);
        }
        if ($setting !== '') {
            $charTag .= ($charTag !== '' ? ', ' : '') . mb_substr($setting, 0, 60);
        }

        $prompt = $base;
        if ($charTag !== '') {
            $prompt .= ', ' . $charTag;
        }
        $prompt .= ', cinematic animated movie still, coherent character design, soft lighting, scene ' . ($index + 1);

        return mb_substr(trim($prompt), 0, 500);
    }

    private function preGenerateTTS(int $projectId, array $scenes): void
    {
        $apiKey = (string) config('services.pollinations_video.api_key', '');
        if ($apiKey === '') {
            return;
        }

        $cacheDir = storage_path('app/tts');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        foreach ($scenes as $scene) {
            $sceneNum = (int) ($scene['scene_number'] ?? 0);
            $narration = trim((string) ($scene['narration'] ?? ''));
            if ($sceneNum < 1 || $narration === '') {
                continue;
            }

            $cacheFile = "{$cacheDir}/tts_{$projectId}_{$sceneNum}_narratrice.mp3";
            if (file_exists($cacheFile)) {
                continue;
            }

            try {
                $text = mb_substr($narration, 0, 4000);
                $encodedText = rawurlencode($text);

                $response = Http::timeout(20)
                    ->retry(1, 1000)
                    ->get("https://gen.pollinations.ai/audio/{$encodedText}", [
                        'voice' => 'nova',
                        'key'   => $apiKey,
                    ]);

                if ($response->successful() && strlen($response->body()) > 1000) {
                    file_put_contents($cacheFile, $response->body());
                    Log::info("TTS pre-generated for project #{$projectId} scene #{$sceneNum}");
                }
            } catch (\Throwable $e) {
                Log::warning("TTS pre-generation failed for project #{$projectId} scene #{$sceneNum}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
