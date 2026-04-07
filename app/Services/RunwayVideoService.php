<?php

namespace App\Services;

use App\Models\VideoProject;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunwayVideoService
{
    public function enabled(): bool
    {
        return !empty((string) config('services.runway.api_key', ''));
    }

    public function createTaskForScene(array $scene, int $seed): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $promptImage = (string) ($scene['image_url'] ?? '');
        if ($promptImage === '' || ! str_starts_with($promptImage, 'https://')) {
            return null;
        }

        $promptText = trim((string) ($scene['visual_description'] ?? ''));
        if ($promptText === '') {
            $promptText = trim((string) ($scene['narration'] ?? ''));
        }

        $duration = (int) ($scene['duration_seconds'] ?? (int) config('services.runway.duration', 5));
        $duration = max(2, min(10, $duration));

        $payload = [
            'model'       => (string) config('services.runway.model', 'gen4_turbo'),
            'promptImage' => $promptImage,
            'promptText'  => mb_substr($promptText, 0, 1000),
            'ratio'       => (string) config('services.runway.ratio', '1280:720'),
            'duration'    => $duration,
            'seed'        => $seed,
        ];

        $response = $this->client()
            ->asJson()
            ->post('/v1/image_to_video', $payload);

        if (! $response->successful()) {
            Log::error('Runway create task failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        $taskId = (string) $response->json('id', '');
        return $taskId !== '' ? $taskId : null;
    }

    public function syncProject(VideoProject $project): void
    {
        if (! $this->enabled() || ! $project->isProcessing()) {
            return;
        }

        $scenes = $project->scenes_json ?? [];
        if (! is_array($scenes) || count($scenes) === 0) {
            return;
        }

        $hasRunwayTasks = false;
        $hasFailure = false;
        $failureMessage = null;
        $changed = false;

        foreach ($scenes as &$scene) {
            $taskId = (string) ($scene['runway_task_id'] ?? '');
            if ($taskId === '') {
                continue;
            }

            $hasRunwayTasks = true;

            if (! empty($scene['video_url'])) {
                if (($scene['video_status'] ?? null) !== 'done') {
                    $scene['video_status'] = 'done';
                    $changed = true;
                }
                continue;
            }

            $task = $this->retrieveTask($taskId);
            if ($task === null) {
                continue;
            }

            $status = strtoupper((string) ($task['status'] ?? 'PENDING'));

            if ($status === 'SUCCEEDED') {
                $output = $task['output'] ?? [];
                $videoUrl = is_array($output) ? (string) ($output[0] ?? '') : '';
                if ($videoUrl !== '') {
                    $scene['video_url'] = $videoUrl;
                    $scene['video_status'] = 'done';
                    unset($scene['runway_task_id']);
                    $changed = true;
                }
                continue;
            }

            if ($status === 'FAILED' || $status === 'CANCELLED') {
                $scene['video_status'] = 'error';
                $scene['video_error'] = (string) ($task['failure'] ?? 'Generation video impossible.');
                $hasFailure = true;
                $failureMessage = (string) ($scene['video_error'] ?? 'Generation video impossible.');
                $changed = true;
                continue;
            }

            $mappedStatus = strtolower($status);
            if (($scene['video_status'] ?? null) !== $mappedStatus) {
                $scene['video_status'] = $mappedStatus;
                $changed = true;
            }
        }
        unset($scene);

        if (! $hasRunwayTasks) {
            return;
        }

        if ($hasFailure) {
            $project->update([
                'status'        => 'error',
                'error_message' => $failureMessage ?: 'La generation des scenes video a echoue.',
                'scenes_json'   => $scenes,
            ]);
            return;
        }

        $allReady = true;
        foreach ($scenes as $scene) {
            if (! empty($scene['runway_task_id']) || empty($scene['video_url'])) {
                $allReady = false;
                break;
            }
        }

        if ($allReady) {
            $project->update([
                'status'        => 'done',
                'current_step'  => 5,
                'video_url'     => $project->video_url ?: 'scene-playlist',
                'scenes_json'   => $scenes,
                'error_message' => null,
            ]);
            return;
        }

        if ($changed) {
            $project->update([
                'current_step' => max((int) $project->current_step, 3),
                'scenes_json'  => $scenes,
            ]);
        }
    }

    private function retrieveTask(string $taskId): ?array
    {
        $response = $this->client()->get('/v1/tasks/' . $taskId);

        if (! $response->successful()) {
            Log::warning('Runway retrieve task failed', [
                'task_id' => $taskId,
                'status'  => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();
        return is_array($data) ? $data : null;
    }

    private function client()
    {
        $baseUrl = rtrim((string) config('services.runway.base_url', 'https://api.dev.runwayml.com'), '/');

        return Http::withHeaders([
            'Authorization'    => 'Bearer ' . (string) config('services.runway.api_key'),
            'X-Runway-Version' => (string) config('services.runway.version', '2024-11-06'),
            'Accept'           => 'application/json',
        ])
        ->baseUrl($baseUrl)
        ->connectTimeout(10)
        ->timeout(45);
    }
}
