<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'theme',
        'status',
        'current_step',
        'story_text',
        'moral',
        'scenes_json',
        'video_url',
        'n8n_execution_id',
        'error_message',
    ];

    protected $casts = [
        'scenes_json'  => 'array',
        'current_step' => 'integer',
    ];

    public function markFailed(string $message): void
    {
        $this->update([
            'status'        => 'error',
            'error_message' => $message,
        ]);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isFailed(): bool
    {
        return $this->status === 'error';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function stepLabel(): string
    {
        return match ($this->current_step) {
            1       => "Génération de l'histoire (IA)",
            2       => 'Création des scènes illustrées',
            3       => 'Génération des images (Pollinations)',
            4       => 'Synthèse vocale (ElevenLabs)',
            5       => 'Film animé prêt !',
            default => 'En attente de démarrage',
        };
    }

    public function getSceneCount(): int
    {
        return count($this->scenes_json ?? []);
    }

    public function getTotalDuration(): int
    {
        return (int) collect($this->scenes_json ?? [])->sum('duration_seconds');
    }
}
