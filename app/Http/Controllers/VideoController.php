<?php

namespace App\Http\Controllers;

use App\Models\VideoProject;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
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
        set_time_limit(600); // Allow up to 10 min for downloading all media

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
            $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'video_pack_' . $project->id . '_' . uniqid();
            if (!is_dir($workDir)) {
                mkdir($workDir, 0755, true);
            }

            $zip = new ZipArchive();
            $zip->open($tmpFile, ZipArchive::OVERWRITE);

            $zip->addFromString('histoire_complete.txt', $story);

            $sep = str_repeat('=', 64);
            $sceneCount = count($scenes);
            $script = "SCRIPT COMPLET\n{$sep}\nTheme : {$theme}\n{$sep}\n\n";
            $decoupage = "DECOUPAGE DES SCENES\n{$sep}\nTheme : {$theme}\n{$sep}\n\n";
            foreach ($scenes as $s) {
                $n    = str_pad((string) ($s['scene_number'] ?? '?'), 2, '0', STR_PAD_LEFT);
                $dur  = $s['duration_seconds'] ?? 15;
                $vis  = $s['visual_description'] ?? '';
                $narr = $s['narration'] ?? '';
                $script .= "SCENE {$n} ({$dur}s)\n[Visuel]    {$vis}\n[Narration] {$narr}\n\n";
                $decoupage .= "SCENE {$n}\n- Duree cible : {$dur}s\n- Visuel      : {$vis}\n- Narration   : {$narr}\n\n";
            }
            $zip->addFromString("script_{$sceneCount}_scenes.txt", $script);
            $zip->addFromString('decoupage_scenes.txt', $decoupage);

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

            // Download images, videos, and narrator audio for each scene.
            $pollinationsVideo = app(\App\Services\PollinationsVideoService::class);
            $sceneMedia = [];
            foreach ($scenes as $s) {
                $sceneNum = (int) ($s['scene_number'] ?? 0);
                $n = str_pad((string) $sceneNum, 2, '0', STR_PAD_LEFT);
                $seed = $id * 100 + $sceneNum;

                $sceneMedia[$sceneNum] = [
                    'duration' => max(3, (int) ($s['duration_seconds'] ?? 15)),
                    'image' => null,
                    'video' => null,
                    'audio' => null,
                ];

                // Download image
                $imagePrompt = trim((string) ($s['image_prompt'] ?? ''));
                if ($imagePrompt === '') {
                    $imagePrompt = $pollinationsVideo->extractPrompt($s);
                }
                if ($imagePrompt !== '') {
                    $imageUrl = $pollinationsVideo->buildRealImageUrl($imagePrompt, $seed);
                    try {
                        $imgResponse = Http::timeout(30)->get($imageUrl);
                        if ($imgResponse->successful()) {
                            $ext = str_contains($imgResponse->header('Content-Type') ?? '', 'png') ? 'png' : 'jpg';
                            $imgBody = $imgResponse->body();
                            $zip->addFromString("images/scene_{$n}.{$ext}", $imgBody);

                            $localImage = $workDir . DIRECTORY_SEPARATOR . "scene_{$n}.{$ext}";
                            file_put_contents($localImage, $imgBody);
                            $sceneMedia[$sceneNum]['image'] = $localImage;
                        }
                    } catch (\Throwable $e) {
                        Log::warning("ZIP: failed to download image for scene {$sceneNum}", ['error' => $e->getMessage()]);
                    }
                }

                // Download video
                if (!empty($s['video_url']) && $pollinationsVideo->videoEnabled()) {
                    $videoPrompt = trim((string) ($s['image_prompt'] ?? ''));
                    if ($videoPrompt === '') {
                        $videoPrompt = $pollinationsVideo->extractPrompt($s);
                    }
                    if ($videoPrompt !== '') {
                        $videoSeed = $id * 1000 + $sceneNum;
                        $realVideoUrl = $pollinationsVideo->buildRealVideoUrl($videoPrompt, $videoSeed);
                        if ($realVideoUrl) {
                            try {
                                $vidResponse = Http::timeout(120)->get($realVideoUrl);
                                if ($vidResponse->successful()) {
                                    $videoBody = $vidResponse->body();
                                    $zip->addFromString("videos/scene_{$n}.mp4", $videoBody);

                                    $localVideo = $workDir . DIRECTORY_SEPARATOR . "scene_{$n}.mp4";
                                    file_put_contents($localVideo, $videoBody);
                                    $sceneMedia[$sceneNum]['video'] = $localVideo;
                                }
                            } catch (\Throwable $e) {
                                Log::warning("ZIP: failed to download video for scene {$sceneNum}", ['error' => $e->getMessage()]);
                            }
                        }
                    }
                }

                $audioPath = $this->getNarrationAudioForScene($project->id, $sceneNum, (string) ($s['narration'] ?? ''));
                if ($audioPath && is_file($audioPath)) {
                    $audioBody = @file_get_contents($audioPath);
                    if ($audioBody !== false) {
                        $zip->addFromString("audio/scene_{$n}.mp3", $audioBody);
                        $sceneMedia[$sceneNum]['audio'] = $audioPath;
                    }
                }
            }

            // Build a final montage video (~3 minutes) with narration when ffmpeg is available.
            $finalVideo = $this->buildFinalMontage($project->id, $scenes, $sceneMedia, $workDir);
            if ($finalVideo && is_file($finalVideo)) {
                $videoBody = @file_get_contents($finalVideo);
                if ($videoBody !== false) {
                    $zip->addFromString('video_finale_3min.mp4', $videoBody);
                }
            } else {
                $zip->addFromString('video_finale_indisponible.txt', implode("\n", [
                    'La video finale n\'a pas pu etre exportee automatiquement.',
                    'Cause probable: ffmpeg indisponible sur l\'environnement de deploiement.',
                    'Le pack contient tout de meme les clips, les images et les audios narrateur.',
                ]));
            }

            $zip->addFromString('explication_workflow.txt', $this->buildWorkflowExplanation());

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
                '  decoupage_scenes.txt              : Decoupage scene par scene',
                '  scenes/scene_XX_narration.txt     : Narration individuelle par scene',
                '  images/scene_XX.jpg               : Images generees par IA',
                '  videos/scene_XX.mp4               : Videos generees par IA',
                '  audio/scene_XX.mp3                : Audio narrateur par scene',
                '  video_finale_3min.mp4             : Video finale montee (~3 min) si export OK',
                '  explication_workflow.txt          : Explication complete du workflow',
                $hasVideo ? '  video_complete.url                : Lien vers la video finale' : '',
            ]);
            $zip->addFromString('README.txt', $readme);
            $zip->close();

            $this->cleanupDirectory($workDir);

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

    private function getNarrationAudioForScene(int $projectId, int $sceneNumber, string $narration): ?string
    {
        $cacheDir = storage_path('app/tts');
        $cacheFile = "{$cacheDir}/tts_{$projectId}_{$sceneNumber}_narratrice.mp3";

        if (is_file($cacheFile)) {
            return $cacheFile;
        }

        $apiKey = (string) config('services.pollinations_video.api_key', '');
        if ($apiKey === '' || trim($narration) === '') {
            return null;
        }

        try {
            $text = mb_substr($narration, 0, 4000);
            $encodedText = rawurlencode($text);
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->get("https://gen.pollinations.ai/audio/{$encodedText}", [
                    'voice' => 'nova',
                    'key'   => $apiKey,
                ]);

            if ($response->failed()) {
                return null;
            }

            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            file_put_contents($cacheFile, $response->body());
            return $cacheFile;
        } catch (\Throwable $e) {
            Log::warning('TTS export ZIP failed for scene', [
                'project_id' => $projectId,
                'scene' => $sceneNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function buildFinalMontage(int $projectId, array $scenes, array $sceneMedia, string $workDir): ?string
    {
        if (empty($scenes) || !$this->ffmpegAvailable()) {
            return null;
        }

        $targetDuration = 180.0;
        $baseTotal = 0.0;
        foreach ($scenes as $scene) {
            $baseTotal += max(3, (float) ($scene['duration_seconds'] ?? 15));
        }
        if ($baseTotal <= 0) {
            $baseTotal = count($scenes) * 15.0;
        }
        $scale = $targetDuration / $baseTotal;

        $segments = [];
        foreach ($scenes as $scene) {
            $sceneNum = (int) ($scene['scene_number'] ?? 0);
            if ($sceneNum <= 0 || !isset($sceneMedia[$sceneNum])) {
                continue;
            }

            $n = str_pad((string) $sceneNum, 2, '0', STR_PAD_LEFT);
            $segmentOut = $workDir . DIRECTORY_SEPARATOR . "segment_{$n}.mp4";
            $scaledDuration = max(3.0, round(max(3, (float) ($scene['duration_seconds'] ?? 15)) * $scale, 2));
            $media = $sceneMedia[$sceneNum];

            $inputs = [];
            $mapVideo = '0:v:0';
            $mapAudio = null;

            if (!empty($media['video']) && is_file($media['video'])) {
                $inputs = [
                    '-stream_loop', '-1', '-i', $media['video'],
                ];
            } elseif (!empty($media['image']) && is_file($media['image'])) {
                $inputs = [
                    '-loop', '1', '-i', $media['image'],
                ];
            } else {
                continue;
            }

            if (!empty($media['audio']) && is_file($media['audio'])) {
                $inputs = array_merge($inputs, ['-i', $media['audio']]);
                $mapAudio = '1:a:0';
            } else {
                $inputs = array_merge($inputs, ['-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=44100']);
                $mapAudio = '1:a:0';
            }

            $cmd = array_merge([
                'ffmpeg', '-y',
            ], $inputs, [
                '-t', (string) $scaledDuration,
                '-map', $mapVideo,
                '-map', $mapAudio,
                '-c:v', 'libx264',
                '-preset', 'veryfast',
                '-crf', '23',
                '-pix_fmt', 'yuv420p',
                '-r', '24',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-ar', '44100',
                '-ac', '2',
                $segmentOut,
            ]);

            if (!$this->runProcess($cmd, 240)) {
                continue;
            }

            if (is_file($segmentOut)) {
                $segments[] = $segmentOut;
            }
        }

        if (count($segments) === 0) {
            return null;
        }

        $listPath = $workDir . DIRECTORY_SEPARATOR . 'segments.txt';
        $lines = [];
        foreach ($segments as $segment) {
            $safe = str_replace('\\', '/', $segment);
            $safe = str_replace("'", "'\\''", $safe);
            $lines[] = "file '{$safe}'";
        }
        file_put_contents($listPath, implode("\n", $lines));

        $finalPath = $workDir . DIRECTORY_SEPARATOR . 'video_finale_3min.mp4';
        $concatCmd = [
            'ffmpeg', '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $listPath,
            '-t', '180',
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-crf', '23',
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '128k',
            $finalPath,
        ];

        if (!$this->runProcess($concatCmd, 360)) {
            return null;
        }

        return is_file($finalPath) ? $finalPath : null;
    }

    private function ffmpegAvailable(): bool
    {
        return $this->runProcess(['ffmpeg', '-version'], 20);
    }

    private function runProcess(array $command, int $timeout): bool
    {
        try {
            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable $e) {
            Log::warning('Process execution failed', [
                'cmd' => $command,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function cleanupDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->cleanupDirectory($full);
            } else {
                @unlink($full);
            }
        }

        @rmdir($path);
    }

    private function buildWorkflowExplanation(): string
    {
        return implode("\n", [
            'EXPLICATION DU WORKFLOW',
            str_repeat('=', 48),
            '',
            '1) Lancement du projet',
            '   - L utilisateur saisit un theme.',
            '   - Laravel cree un projet puis appelle le webhook n8n.',
            '',
            '2) Generation du contenu narratif',
            '   - n8n appelle le LLM (Groq) pour produire:',
            '     * histoire complete',
            '     * decoupage en scenes',
            '     * narration scene par scene.',
            '',
            '3) Generation visuelle',
            '   - Pour chaque scene, un prompt image est construit.',
            '   - Pollinations genere les images puis les clips video.',
            '',
            '4) Generation audio',
            '   - Chaque narration est convertie en audio (voix narratrice nova).',
            '   - Les mp3 sont caches cote serveur.',
            '',
            '5) Callback et affichage',
            '   - n8n envoie les resultats a Laravel (callback).',
            '   - La page resultat precharge images/audio puis lit le film.',
            '',
            '6) Export livrables',
            '   - Le bouton telechargement cree un ZIP contenant:',
            '     * histoire',
            '     * script et decoupage des scenes',
            '     * clips video',
            '     * audio narrateur',
            '     * video finale montee (~3 min) si ffmpeg est disponible.',
        ]);
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

        // Use image_prompt (scene-specific + character tag) for video too, so each scene gets a unique video
        $prompt = trim((string) ($scene['image_prompt'] ?? ''));
        if ($prompt === '') {
            $prompt = $pollinationsVideo->extractPrompt($scene);
        }
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

        // Force narratrice for consistent voice across all scenes
        $voice = $voiceMap['narratrice'];

        $cacheDir  = storage_path('app/tts');
        $cacheFile = "{$cacheDir}/tts_{$id}_{$sceneNumber}_narratrice.mp3";

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
