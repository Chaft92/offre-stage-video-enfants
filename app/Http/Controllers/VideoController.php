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
                'callback_url' => route('n8n.callback', absolute: true),
                'error_url'    => route('n8n.error', absolute: true),
                'step_url'     => route('n8n.step', absolute: true),
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

    public function download(int $id): Response
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
        $script = "SCRIPT COMPLET\n{$sep}\nThème : {$theme}\n{$sep}\n\n";
        foreach ($scenes as $s) {
            $n    = str_pad((string) ($s['scene_number'] ?? '?'), 2, '0', STR_PAD_LEFT);
            $dur  = $s['duration_seconds'] ?? 15;
            $vis  = $s['visual_description'] ?? '';
            $narr = $s['narration'] ?? '';
            $script .= "SCENE {$n} ({$dur}s)\n[Visuel]    {$vis}\n[Narration] {$narr}\n\n";
        }
        $zip->addFromString('script_12_scenes.txt', $script);

        foreach ($scenes as $s) {
            $n    = str_pad((string) ($s['scene_number'] ?? 0), 2, '0', STR_PAD_LEFT);
            $narr = $s['narration'] ?? '';
            $zip->addFromString("scenes/scene_{$n}_narration.txt", $narr);
        }

        $zip->addFromString('video_complete.url', "[InternetShortcut]\nURL={$videoUrl}\n");
        $zip->addFromString('video_sans_voix.url', "[InternetShortcut]\nURL={$videoUrl}\n");
        $zip->addFromString('voix_off_complete.url', "[InternetShortcut]\nURL=(non disponible en mode demo)\n");

        $readme = implode("\n", [
            'PACK COMPLET — AI Kids Video Generator',
            'Fait par Julien YILDIZ — rendu test de stage',
            str_repeat('=', 48),
            '',
            'Contenu de cette archive :',
            '  histoire_complete.txt      : L\'histoire narrative générée',
            '  script_12_scenes.txt       : Script complet (visuel + narration par scène)',
            '  scenes/scene_XX_narration  : Narration de chaque scène individuellement',
            '  video_complete.url         : Lien vers la vidéo finale assemblée',
            '  video_sans_voix.url        : Lien vers la vidéo sans voix off',
            '  voix_off_complete.url      : Lien vers la piste audio de narration',
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
}
