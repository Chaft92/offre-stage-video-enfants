<?php

namespace App\Http\Controllers;

use App\Models\VideoProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class N8NCallbackController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id'                       => ['required', 'integer', 'exists:video_projects,id'],
            'video_url'                        => ['required', 'string', 'max:1000'],
            'story_text'                       => ['required', 'string', 'max:50000'],
            'scenes_json'                      => ['required', 'array', 'min:1', 'max:20'],
            'scenes_json.*.scene_number'       => ['required', 'integer', 'min:1'],
            'scenes_json.*.narration'          => ['required', 'string'],
            'scenes_json.*.visual_description' => ['required', 'string'],
            'scenes_json.*.duration_seconds'   => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $project = VideoProject::findOrFail($data['project_id']);

        if ($project->isDone()) {
            return response()->json(['success' => true, 'message' => 'Projet déjà complété.']);
        }

        $project->update([
            'status'        => 'done',
            'current_step'  => 5,
            'video_url'     => $data['video_url'],
            'story_text'    => $data['story_text'],
            'scenes_json'   => $data['scenes_json'],
            'error_message' => null,
        ]);

        Log::info("Projet #{$project->id} terminé avec succès.", ['theme' => $project->theme]);

        return response()->json(['success' => true]);
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
            'scenes_b64'  => ['required', 'string'],
            'output_path' => ['required', 'string', 'max:500'],
            'story'       => ['nullable', 'string', 'max:50000'],
            'all_scenes'  => ['nullable', 'array'],
        ]);

        $project = VideoProject::findOrFail($data['project_id']);

        // Validate base64 and decode scenes for basic sanity
        $decoded = base64_decode($data['scenes_b64'], strict: true);
        if ($decoded === false) {
            return response()->json(['error' => 'Invalid scenes_b64 encoding.'], 422);
        }

        $scriptPath = base_path('scripts/assemble_video.py');
        $outputPath = $data['output_path'];

        // Only allow output paths inside storage/
        $storagePath = storage_path();
        $resolved    = realpath(dirname($outputPath));
        if ($resolved === false || !str_starts_with($resolved, $storagePath)) {
            return response()->json(['error' => 'Invalid output path.'], 422);
        }

        $result = Process::timeout(300)->run([
            'python3', $scriptPath,
            '--scenes-b64', $data['scenes_b64'],
            '--output', $outputPath,
        ]);

        if ($result->failed()) {
            $err = trim($result->errorOutput() ?: $result->output());
            Log::error("Assemble failed for project #{$project->id}", ['stderr' => $err]);
            return response()->json(['error' => 'FFmpeg assembly failed: ' . $err], 500);
        }

        $videoUrl = '/storage/videos/' . $project->id . '.mp4';

        Log::info("Assemble OK for project #{$project->id}", ['video_url' => $videoUrl]);

        return response()->json(['success' => true, 'video_url' => $videoUrl]);
    }
