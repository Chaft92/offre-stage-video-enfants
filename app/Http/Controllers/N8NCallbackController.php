<?php

namespace App\Http\Controllers;

use App\Models\VideoProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'scenes_json'                      => ['required', 'array', 'min:1', 'max:25'],
            'scenes_json.*.scene_number'       => ['required', 'integer', 'min:1'],
            'scenes_json.*.narration'          => ['required', 'string'],
            'scenes_json.*.visual_description' => ['required', 'string'],
            'scenes_json.*.duration_seconds'   => ['required', 'integer', 'min:1', 'max:60'],
            'scenes_json.*.video_url'          => ['nullable', 'string', 'max:1000'],
        ]);

        $project = VideoProject::findOrFail($data['project_id']);

        $scenes = array_values($data['scenes_json']);
        usort($scenes, function ($a, $b) {
            return (int) ($a['scene_number'] ?? 0) <=> (int) ($b['scene_number'] ?? 0);
        });

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

        foreach ($scenes as $i => &$scene) {
            $visualPrompt = $scene['visual_description'] ?? 'A colorful cartoon scene for children';
            $encodedPrompt = rawurlencode($visualPrompt);
            $seed = $project->id * 100 + ($scene['scene_number'] ?? $i + 1);
            $scene['image_url'] = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width=1280&height=720&nologo=true&seed={$seed}";
        }
        unset($scene);

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
        if ($count < 8 || $count > 20) {
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
}
