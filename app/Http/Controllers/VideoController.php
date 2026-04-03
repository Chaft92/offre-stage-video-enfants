<?php

namespace App\Http\Controllers;

use App\Models\VideoProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        ]);

        $project = VideoProject::create([
            'theme'  => trim($data['theme']),
            'status' => 'pending',
        ]);

        $webhookUrl = config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            $project->markFailed('N8N_WEBHOOK_URL non configuré.');
            return response()->json([
                'success'    => false,
                'project_id' => $project->id,
                'message'    => 'Le pipeline n\'est pas configuré. Contactez l\'administrateur.',
            ], 503);
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-N8N-Secret' => config('services.n8n.secret', ''),
            ])
            ->timeout(15)
            ->retry(2, 500)
            ->post($webhookUrl, [
                'project_id'   => $project->id,
                'theme'        => $project->theme,
                'callback_url' => route('n8n.callback'),
                'error_url'    => route('n8n.error'),
                'step_url'     => route('n8n.step'),
            ]);

            if ($response->failed()) {
                throw new \RuntimeException(
                    "N8N a retourné HTTP {$response->status()} : {$response->body()}"
                );
            }

            $project->update([
                'status'           => 'processing',
                'n8n_execution_id' => $response->json('executionId'),
            ]);

        } catch (\Exception $e) {
            Log::error('Échec déclenchement N8N', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
            ]);
            $project->markFailed('Impossible de démarrer le pipeline : ' . $e->getMessage());

            return response()->json([
                'success'    => false,
                'project_id' => $project->id,
                'message'    => 'Impossible de contacter le pipeline. Réessayez dans quelques instants.',
                'debug'      => $e->getMessage(),
                'webhook'    => config('services.n8n.webhook_url'),
            ], 502);
        }

        return response()->json([
            'success'    => true,
            'project_id' => $project->id,
            'status'     => $project->status,
        ], 201);
    }

    public function status(int $id): JsonResponse
    {
        $project = VideoProject::select([
            'id', 'status', 'current_step', 'theme', 'video_url', 'error_message',
        ])->findOrFail($id);

        return response()->json($project);
    }

    public function show(int $id)
    {
        $project = VideoProject::findOrFail($id);

        if ($project->isProcessing()) {
            return redirect()->route('video.index')
                ->with('info', 'La vidéo n\'est pas encore prête. Vous allez être redirigé automatiquement.');
        }

        if ($project->isFailed()) {
            return redirect()->route('video.index')
                ->with('error', 'La génération a échoué : ' . $project->error_message);
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
        $theme    = $project->theme        ?? 'video';

        $tmpFile = tempnam(sys_get_temp_dir(), 'video_zip_');

        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::OVERWRITE);

        $zip->addFromString('histoire_complete.txt', $story);

        $sep    = str_repeat('=', 64);
        $sceneCount = count($scenes);
        $script = "SCRIPT COMPLET\n{$sep}\nThème : {$theme}\n{$sep}\n\n";
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
            'PACK COMPLET — AI Kids Video Generator',
            'Fait par Julien YILDIZ — rendu test de stage',
            str_repeat('=', 48),
            '',
            "Thème : {$theme}",
            "Nombre de scènes : {$sceneCount}",
            '',
            'Contenu de cette archive :',
            '  histoire_complete.txt             : L\'histoire narrative générée par IA',
            "  script_{$sceneCount}_scenes.txt   : Script complet (visuel + narration par scène)",
            '  scenes/scene_XX_narration.txt     : Narration de chaque scène individuellement',
            $hasVideo ? '  video_complete.url                : Lien vers la vidéo finale' : '',
        ]);
        $zip->addFromString('README.txt', $readme);

        $zip->close();

        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($theme));
        $slug = trim($slug, '_');
        $slug = substr($slug, 0, 40);
        $filename = "video_{$id}_{$slug}.zip";

        return response()->file($tmpFile, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ])->deleteFileAfterSend();
    }

    /**
     * Poll Replicate for video status and update scenes when ready.
     */
    public function checkVideos(int $id): JsonResponse
    {
        $project = VideoProject::findOrFail($id);
        $scenes = $project->scenes_json ?? [];
        $updated = false;
        $ready = 0;
        $failed = 0;
        $total = count($scenes);

        $apiToken = config('services.replicate.api_key');

        foreach ($scenes as $i => &$scene) {
            $videoUrl = $scene['video_url'] ?? '';

            // Already has a valid video URL
            if ($videoUrl && filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                $ready++;
                continue;
            }

            // Already marked as failed
            if ($videoUrl === 'failed') {
                $failed++;
                continue;
            }

            // No prediction to poll
            $predUrl = $scene['prediction_url'] ?? '';
            if (empty($predUrl) || empty($apiToken)) {
                continue;
            }

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                ])->timeout(10)->get($predUrl);

                if ($response->successful()) {
                    $pred = $response->json();

                    if (($pred['status'] ?? '') === 'succeeded' && !empty($pred['output'])) {
                        $output = $pred['output'];
                        $scene['video_url'] = is_array($output) ? $output[0] : $output;
                        $updated = true;
                        $ready++;
                    } elseif (in_array($pred['status'] ?? '', ['failed', 'canceled'])) {
                        $scene['video_url'] = 'failed';
                        $updated = true;
                        $failed++;
                    }
                    // else still processing — leave as is
                }
            } catch (\Exception $e) {
                Log::warning("checkVideos #{$id} scene {$i}: {$e->getMessage()}");
            }
        }

        if ($updated) {
            // Update main video_url with the first available video
            $mainVideoUrl = 'pending';
            foreach ($scenes as $sc) {
                $v = $sc['video_url'] ?? '';
                if ($v && filter_var($v, FILTER_VALIDATE_URL)) {
                    $mainVideoUrl = $v;
                    break;
                }
            }

            $updateData = ['scenes_json' => $scenes, 'video_url' => $mainVideoUrl];
            if ($ready >= $total || ($ready + $failed) >= $total) {
                $updateData['current_step'] = 5;
            }
            $project->update($updateData);
        }

        return response()->json([
            'ready'    => $ready,
            'failed'   => $failed,
            'total'    => $total,
            'all_done' => ($ready + $failed) >= $total,
            'scenes'   => $scenes,
        ]);
    }

    /**
     * ElevenLabs TTS proxy — generates audio for a given scene's narration.
     * Caches in storage/app/tts/ to avoid re-calling the API on page refresh.
     */
    public function tts(int $id, int $sceneNumber)
    {
        $project = VideoProject::findOrFail($id);
        abort_unless($project->isDone(), 404, 'Projet non disponible.');

        $scenes = $project->scenes_json ?? [];
        $scene  = collect($scenes)->firstWhere('scene_number', $sceneNumber);

        if (!$scene || empty($scene['narration'])) {
            abort(404, 'Scène introuvable.');
        }

        $cacheDir  = storage_path('app/tts');
        $cacheFile = "{$cacheDir}/tts_{$id}_{$sceneNumber}.mp3";

        if (file_exists($cacheFile)) {
            return response()->file($cacheFile, [
                'Content-Type'  => 'audio/mpeg',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $apiKey  = config('services.elevenlabs.api_key');
        $voiceId = config('services.elevenlabs.voice_id', 'EXAVITQu4vr4xnSDxMaL');

        if (empty($apiKey)) {
            abort(503, 'ElevenLabs API key not configured.');
        }

        try {
            $response = Http::withHeaders([
                'xi-api-key'   => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'audio/mpeg',
            ])
            ->timeout(30)
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                'text'     => $scene['narration'],
                'model_id' => 'eleven_multilingual_v2',
                'voice_settings' => [
                    'stability'        => 0.5,
                    'similarity_boost' => 0.75,
                    'style'            => 0.3,
                ],
            ]);

            if ($response->failed()) {
                Log::error('ElevenLabs TTS failed', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
                abort(502, 'ElevenLabs API error.');
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
            Log::error('ElevenLabs TTS exception', ['error' => $e->getMessage()]);
            abort(502, 'ElevenLabs service unavailable.');
        }
    }
}
