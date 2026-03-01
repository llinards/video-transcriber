<?php

namespace App\Models;

use App\Enums\TranscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transcription extends Model
{
    /** @use HasFactory<\Database\Factories\TranscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'video_path',
        'audio_path',
        'status',
        'srt_content',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => TranscriptionStatus::class,
        ];
    }

    /**
     * Determine if the transcription is still processing.
     */
    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }

    /**
     * Determine if the transcription has completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === TranscriptionStatus::Completed;
    }

    /**
     * Determine if the transcription has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === TranscriptionStatus::Failed;
    }
}
