<?php

namespace App\Http\Controllers;

use App\Models\VideoProject;
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
            'scenes_json'                      => ['required', 'array', 'min:1', 'max:25'],
            'scenes_json.*.scene_number'       => ['required', 'integer', 'min:1'],
            'scenes_json.*.narration'          => ['required', 'string'],
            'scenes_json.*.visual_description' => ['required', 'string'],
            'scenes_json.*.duration_seconds'   => ['required', 'integer', 'min:1', 'max:60'],
            'scenes_json.*.video_url'          => ['nullable', 'string', 'max:1000'],
        ]);

        $project = VideoProject::findOrFail($data['project_id']);

        if ($project->isDone()) {
            return response()->json(['success' => true, 'message' => 'Projet déjà complété.']);
        }

        // Save story and scenes first
        $scenes = $data['scenes_json'];
        $project->update([
            'status'        => 'done',
            'current_step'  => 3,
            'video_url'     => $data['video_url'],
            'story_text'    => $data['story_text'],
            'scenes_json'   => $scenes,
            'error_message' => null,
        ]);

        Log::info("Projet #{$project->id} — histoire reçue, lancement Replicate.", ['theme' => $project->theme]);

        // Create Replicate predictions (may take ~15s for 12 scenes)
        set_time_limit(120);
        $this->createReplicatePredictions($project);

        return response()->json(['success' => true]);
    }

    /**
     * Create Replicate video predictions for all scenes without a video_url.
     */
    private function createReplicatePredictions(VideoProject $project): void
    {
        $apiToken = config('services.replicate.api_key');
        if (empty($apiToken)) {
            Log::warning("Projet #{$project->id} — REPLICATE_API_TOKEN manquant, skip vidéos.");
            return;
        }

        $scenes = $project->scenes_json ?? [];
        $created = 0;

        foreach ($scenes as $i => &$scene) {
            // Skip scenes that already have a video
            if (!empty($scene['video_url']) && filter_var($scene['video_url'], FILTER_VALIDATE_URL)) {
                continue;
            }

            $prompt = ($scene['visual_description'] ?? 'A colorful cartoon scene')
                . '. Cartoon style animation for children, bright colors, child-friendly, smooth animation, high quality, 5 seconds.';

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type'  => 'application/json',
                ])->timeout(15)->post('https://api.replicate.com/v1/models/minimax/video-01/predictions', [
                    'input' => [
                        'prompt'           => $prompt,
                        'prompt_optimizer' => true,
                    ],
                ]);

                if ($response->successful()) {
                    $pred = $response->json();
                    $scenes[$i]['prediction_id']  = $pred['id'] ?? null;
                    $scenes[$i]['prediction_url'] = $pred['urls']['get'] ?? null;
                    $scenes[$i]['video_url']      = ''; // will be filled when polling
                    $created++;
                    Log::info("Projet #{$project->id} scène {$i} — prediction créée: {$pred['id']}");
                } else {
                    Log::error("Projet #{$project->id} scène {$i} — Replicate error: {$response->status()} {$response->body()}");
                }
            } catch (\Exception $e) {
                Log::error("Projet #{$project->id} scène {$i} — Replicate exception: {$e->getMessage()}");
            }
        }

        // Save updated scenes with prediction IDs
        $project->update([
            'scenes_json'  => $scenes,
            'current_step' => 4,
        ]);

        Log::info("Projet #{$project->id} — {$created} prédictions Replicate créées.");
    }

    public function error(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'    => ['required', 'integer', 'exists:video_projects,id'],
            'error_message' => ['required', 'string', 'max:2000'],
        ]);

        $project = VideoProject::findOrFail($data['project_id']);
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

    public function assemble(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'  => ['required', 'integer', 'exists:video_projects,id'],
            'scenes_b64'  => ['nullable', 'string'],
            'output_path' => ['nullable', 'string', 'max:500'],
            'story'       => ['nullable', 'string', 'max:50000'],
            'all_scenes'  => ['nullable', 'array'],
        ]);

        $project = VideoProject::findOrFail($data['project_id']);

        // In demo mode, just mark project as done with the available data
        $scenes = [];
        if (!empty($data['scenes_b64'])) {
            $decoded = base64_decode($data['scenes_b64'], strict: true);
            if ($decoded !== false) {
                $scenes = json_decode($decoded, true) ?: [];
            }
        } elseif (!empty($data['all_scenes'])) {
            $scenes = $data['all_scenes'];
        }

        $videoUrl = '';
        foreach ($scenes as $s) {
            if (!empty($s['video_url'])) {
                $videoUrl = $s['video_url'];
                break;
            }
        }

        $project->update([
            'status'       => 'done',
            'current_step' => 5,
            'video_url'    => $videoUrl ?: 'demo-mode',
            'story_text'   => $data['story'] ?? '',
            'scenes_json'  => $scenes,
        ]);

        Log::info("Assemble (demo) OK for project #{$project->id}", ['video_url' => $videoUrl]);

        return response()->json(['success' => true, 'video_url' => $videoUrl]);
    }
}
