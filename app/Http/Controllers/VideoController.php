<?php

namespace App\Http\Controllers;

use App\Models\VideoProject;
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

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-N8N-Secret' => config('services.n8n.secret', ''),
            ])
            ->connectTimeout(5)
            ->timeout(120)
            ->post($webhookUrl, [
                'project_id'   => $project->id,
                'theme'        => $project->theme,
                'style'        => $data['style'] ?? 'cartoon',
                'callback_url' => route('n8n.callback'),
                'error_url'    => route('n8n.error'),
                'step_url'     => route('n8n.step'),
            ]);

            if ($response->failed()) {
                throw new \RuntimeException("HTTP {$response->status()}");
            }

            $project->update([
                'status'           => 'processing',
                'n8n_execution_id' => $response->json('executionId'),
            ]);

        } catch (\Exception $e) {
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

        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($theme));
        $slug = trim($slug, '_');
        $slug = substr($slug, 0, 40);

        return response()->file($tmpFile, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="video_' . $id . '_' . $slug . '.zip"',
        ])->deleteFileAfterSend();
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
            'narratrice'    => 'EXAVITQu4vr4xnSDxMaL',
            'narrateur'     => 'ErXwobaYiN019PkySvjV',
            'enfant_fille'  => 'jBpfuIE2acCO8z3wKNLl',
            'enfant_garcon' => 'yoZ06aMxZJJ28mfd3POQ',
        ];

        $allowedVoices = array_keys($voiceMap);
        $voiceType = in_array($scene['voice'] ?? '', $allowedVoices) ? $scene['voice'] : 'narratrice';
        $voiceId = $voiceMap[$voiceType];

        $cacheDir  = storage_path('app/tts');
        $cacheFile = "{$cacheDir}/tts_{$id}_{$sceneNumber}_{$voiceType}.mp3";

        if (file_exists($cacheFile)) {
            return response()->file($cacheFile, [
                'Content-Type'  => 'audio/mpeg',
                'Cache-Control' => 'public, max-age=86400',
            ]);
        }

        $apiKey = config('services.elevenlabs.api_key');
        if (empty($apiKey)) {
            abort(503, 'Service de synthese vocale indisponible.');
        }

        try {
            $response = Http::withHeaders([
                'xi-api-key'   => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'audio/mpeg',
            ])
            ->timeout(30)
            ->retry(2, 300)
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
                Log::error('ElevenLabs TTS failed', ['status' => $response->status()]);
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
            Log::error('ElevenLabs TTS exception', ['error' => $e->getMessage()]);
            abort(502, 'Service de synthese vocale indisponible.');
        }
    }
}
