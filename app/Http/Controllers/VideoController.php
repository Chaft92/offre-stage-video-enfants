<?php

namespace App\Http\Controllers;

use App\Models\VideoProject;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class VideoController extends Controller
{
    public function index()
    {
        return view('video.index');
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'min:3', 'max:255'],
            'style' => ['sometimes', 'string', 'in:cartoon,watercolor,pixel,anime'],
        ]);

        $project = VideoProject::create([
            'theme'  => trim($data['theme']),
            'status' => 'pending',
        ]);

        $webhookUrl = config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            $project->markFailed('Pipeline non configure.');
            return response()->json([
                'success'    => false,
                'project_id' => $project->id,
                'message'    => 'Le pipeline n\'est pas configure.',
            ], 503);
        }

        $project->update([
            'status'        => 'processing',
            'current_step'  => 1,
            'error_message' => null,
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-N8N-Secret' => config('services.n8n.secret', ''),
            ])
            ->retry(2, 400)
            ->connectTimeout(10)
            ->timeout(45)
            ->post($webhookUrl, [
                'project_id'   => $project->id,
                'theme'        => $project->theme,
                'style'        => $data['style'] ?? 'cartoon',
                'callback_url' => route('n8n.callback'),
                'error_url'    => route('n8n.error'),
                'step_url'     => route('n8n.step'),
            ]);

            $executionId = null;
            try {
                $executionId = $response->json('executionId');
            } catch (\Throwable) {
            }

            if (! $response->successful()) {
                $status = $response->status();

                if ($status >= 500 || $status === 408) {
                    Log::warning('Declenchement N8N reponse non-success mais potentiellement lance', [
                        'project_id' => $project->id,
                        'status'     => $status,
                        'body'       => mb_substr((string) $response->body(), 0, 500),
                    ]);

                    return response()->json([
                        'success'    => true,
                        'project_id' => $project->id,
                        'status'     => 'processing',
                    ], 202);
                }

                throw new \RuntimeException('HTTP ' . $status);
            }

            $project->update([
                'status'           => 'processing',
                'current_step'     => 1,
                'n8n_execution_id' => is_string($executionId) && $executionId !== '' ? $executionId : null,
                'error_message'    => null,
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Timeout/connexion declenchement N8N, passage en suivi asynchrone', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success'    => true,
                'project_id' => $project->id,
                'status'     => 'processing',
            ], 202);
        } catch (\Throwable $e) {
            Log::error('Echec declenchement N8N', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
            ]);

            $project->markFailed('Impossible de demarrer le pipeline.');

            return response()->json([
                'success'    => false,
                'project_id' => $project->id,
                'message'    => 'Impossible de contacter le pipeline. Reessayez dans quelques instants.',
            ], 502);
        }

        return response()->json([
            'success'    => true,
            'project_id' => $project->id,
            'status'     => 'processing',
        ], 201);
    }

    public function status(int $id): JsonResponse
    {
        $project = VideoProject::findOrFail($id);

        if ($project->isProcessing()) {
            $isEarlyStep = (int) ($project->current_step ?? 0) <= 1;
            $hasScenes = is_array($project->scenes_json ?? null) && count($project->scenes_json) > 0;
            $isStalled = $isEarlyStep
                && ! $hasScenes
                && $project->created_at
                && now()->diffInSeconds($project->created_at) > 240;

            if ($isStalled) {
                $project->markFailed('Le pipeline ne repond pas. Verifiez la configuration du webhook n8n.');
                $project->refresh();
            }
        }

        $scenes = $project->scenes_json ?? [];
        $videoReadyCount = collect($scenes)->filter(function ($scene) {
            return ! empty($scene['video_url']);
        })->count();

        $payload = [
            'id'              => $project->id,
            'status'          => $project->status,
            'current_step'    => $project->current_step,
            'theme'           => $project->theme,
            'video_url'       => $project->video_url,
            'error_message'   => $project->error_message,
            'scene_count'     => count($scenes),
            'video_ready'     => $videoReadyCount,
        ];

        return response()->json($payload);
    }

    public function show(int $id)
    {
        $project = VideoProject::findOrFail($id);

        if ($project->isProcessing()) {
            return redirect()->route('video.index')
                ->with('info', 'La video n\'est pas encore prete.');
        }

        if ($project->isFailed()) {
            return redirect()->route('video.index')
                ->with('error', 'La generation a echoue : ' . $project->error_message);
        }

        return view('video.result', compact('project'));
    }

    public function download(int $id)
    {
        $project = VideoProject::findOrFail($id);
        abort_unless($project->isDone(), 404, 'Projet non disponible.');

        $scenes   = $project->scenes_json ?? [];
        $story    = $project->story_text  ?? '';
        $videoUrl = $project->video_url   ?? '';
        $theme    = $project->theme       ?? 'video';

        if (!class_exists('ZipArchive')) {
            $sep = str_repeat('=', 64);
            $sceneCount = count($scenes);
            $content = "PACK COMPLET - AI Kids Video Generator\nFait par Julien YILDIZ\n{$sep}\n\nTheme : {$theme}\n\n{$sep}\nHISTOIRE\n{$sep}\n{$story}\n\n{$sep}\nSCRIPT\n{$sep}\n\n";
            foreach ($scenes as $s) {
                $n    = str_pad((string) ($s['scene_number'] ?? '?'), 2, '0', STR_PAD_LEFT);
                $dur  = $s['duration_seconds'] ?? 15;
                $vis  = $s['visual_description'] ?? '';
                $narr = $s['narration'] ?? '';
                $content .= "SCENE {$n} ({$dur}s)\n[Visuel]    {$vis}\n[Narration] {$narr}\n\n";
            }
            $slug = substr(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($theme)), '_'), 0, 40);
            return response($content, 200, [
                'Content-Type'        => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="video_' . $id . '_' . $slug . '.txt"',
            ]);
        }

        try {
            $tmpFile = tempnam(sys_get_temp_dir(), 'video_zip_');
            $zip = new ZipArchive();
            $zip->open($tmpFile, ZipArchive::OVERWRITE);

            $zip->addFromString('histoire_complete.txt', $story);

            $sep = str_repeat('=', 64);
            $sceneCount = count($scenes);
            $script = "SCRIPT COMPLET\n{$sep}\nTheme : {$theme}\n{$sep}\n\n";
            foreach ($scenes as $s) {
                $n    = str_pad((string) ($s['scene_number'] ?? '?'), 2, '0', STR_PAD_LEFT);
                $dur  = $s['duration_seconds'] ?? 15;
                $vis  = $s['visual_description'] ?? '';
                $narr = $s['narration'] ?? '';
                $script .= "SCENE {$n} ({$dur}s)\n[Visuel]    {$vis}\n[Narration] {$narr}\n\n";
            }
            $zip->addFromString("script_{$sceneCount}_scenes.txt", $script);

            foreach ($scenes as $s) {
                $n    = str_pad((string) ($s['scene_number'] ?? 0), 2, '0', STR_PAD_LEFT);
                $narr = $s['narration'] ?? '';
                $zip->addFromString("scenes/scene_{$n}_narration.txt", $narr);
            }

            $hasVideo = $videoUrl
                && !str_contains($videoUrl, 'placeholder')
                && !str_contains($videoUrl, 'demo-mode')
                && filter_var($videoUrl, FILTER_VALIDATE_URL);

            if ($hasVideo) {
                $zip->addFromString('video_complete.url', "[InternetShortcut]\nURL={$videoUrl}\n");
            }

            $readme = implode("\n", [
                'PACK COMPLET - AI Kids Video Generator',
                'Fait par Julien YILDIZ',
                str_repeat('=', 48),
                '',
                "Theme : {$theme}",
                "Nombre de scenes : {$sceneCount}",
                '',
                'Contenu :',
                '  histoire_complete.txt             : Histoire narrative generee par IA',
                "  script_{$sceneCount}_scenes.txt   : Script complet (visuel + narration)",
                '  scenes/scene_XX_narration.txt     : Narration individuelle par scene',
                $hasVideo ? '  video_complete.url                : Lien vers la video finale' : '',
            ]);
            $zip->addFromString('README.txt', $readme);
            $zip->close();

            $slug = substr(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($theme)), '_'), 0, 40);

            return response()->file($tmpFile, [
                'Content-Type'        => 'application/zip',
                'Content-Disposition' => 'attachment; filename="video_' . $id . '_' . $slug . '.zip"',
            ])->deleteFileAfterSend();

        } catch (\Exception $e) {
            Log::error('Download zip failed', ['error' => $e->getMessage()]);
            abort(500, 'Erreur lors de la creation du fichier.');
        }
    }

    public function sceneImage(int $id, int $sceneNumber)
    {
        $project = VideoProject::select(['id', 'status', 'scenes_json'])->findOrFail($id);
        abort_unless($project->isDone(), 404, 'Projet non disponible.');

        $scenes = $project->scenes_json ?? [];
        $scene  = collect($scenes)->firstWhere('scene_number', $sceneNumber);

        if (!$scene) {
            abort(404, 'Scene introuvable.');
        }

        $pollinationsVideo = app(\App\Services\PollinationsVideoService::class);

        $prompt = trim((string) ($scene['image_prompt'] ?? ''));
        if ($prompt === '') {
            $prompt = $pollinationsVideo->extractPrompt($scene);
        }
        if ($prompt === '') {
            $prompt = 'colorful animated scene';
        }

        $seed = $project->id * 100 + $sceneNumber;
        $realUrl = $pollinationsVideo->buildRealImageUrl($prompt, $seed);

        return redirect()->away($realUrl);
    }

    public function clip(int $id, int $sceneNumber)
    {
        $project = VideoProject::select(['id', 'status', 'scenes_json'])->findOrFail($id);
        abort_unless($project->isDone(), 404, 'Projet non disponible.');

        $scenes = $project->scenes_json ?? [];
        $scene  = collect($scenes)->firstWhere('scene_number', $sceneNumber);

        if (!$scene) {
            abort(404, 'Scene introuvable.');
        }

        $pollinationsVideo = app(\App\Services\PollinationsVideoService::class);
        if (!$pollinationsVideo->videoEnabled()) {
            abort(503, 'Service video indisponible.');
        }

        $prompt = $pollinationsVideo->extractPrompt($scene);
        if ($prompt === '') {
            abort(404, 'Aucune description pour cette scene.');
        }

        $sceneSeed = $project->id * 1000 + $sceneNumber;
        $realUrl = $pollinationsVideo->buildRealVideoUrl($prompt, $sceneSeed);

        if ($realUrl === null) {
            abort(502, 'Impossible de construire l\'URL video.');
        }

        return redirect()->away($realUrl);
    }

    public function tts(int $id, int $sceneNumber)
    {
        $project = VideoProject::select(['id', 'status', 'scenes_json'])->findOrFail($id);
        abort_unless($project->isDone(), 404, 'Projet non disponible.');

        $scenes = $project->scenes_json ?? [];
        $scene  = collect($scenes)->firstWhere('scene_number', $sceneNumber);

        if (!$scene || empty($scene['narration'])) {
            abort(404, 'Scene introuvable.');
        }

        $voiceMap = [
            'narratrice'    => 'nova',
            'narrateur'     => 'onyx',
            'enfant_fille'  => 'shimmer',
            'enfant_garcon' => 'echo',
        ];

        $voiceType = in_array($scene['voice'] ?? '', array_keys($voiceMap)) ? $scene['voice'] : 'narratrice';
        $voice = $voiceMap[$voiceType];

        $cacheDir  = storage_path('app/tts');
        $cacheFile = "{$cacheDir}/tts_{$id}_{$sceneNumber}_{$voiceType}.mp3";

        if (file_exists($cacheFile)) {
            return response()->file($cacheFile, [
                'Content-Type'  => 'audio/mpeg',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $apiKey = (string) config('services.pollinations_video.api_key', '');
        if ($apiKey === '') {
            abort(503, 'Service de synthese vocale indisponible.');
        }

        try {
            $text = mb_substr($scene['narration'], 0, 4000);
            $encodedText = rawurlencode($text);

            $response = Http::timeout(30)
                ->retry(2, 500)
                ->get("https://gen.pollinations.ai/audio/{$encodedText}", [
                    'voice' => $voice,
                    'key'   => $apiKey,
                ]);

            if ($response->failed()) {
                Log::error('Pollinations TTS failed', ['status' => $response->status()]);
                abort(502, 'Erreur synthese vocale.');
            }

            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            file_put_contents($cacheFile, $response->body());

            return response($response->body(), 200, [
                'Content-Type'  => 'audio/mpeg',
                'Cache-Control' => 'public, max-age=86400',
            ]);

        } catch (\Exception $e) {
            Log::error('Pollinations TTS exception', ['error' => $e->getMessage()]);
            abort(502, 'Service de synthese vocale indisponible.');
        }
    }
}
