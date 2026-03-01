<?php

namespace App\Enums;

enum TranscriptionStatus: string
{
    case Pending = 'pending';
    case ExtractingAudio = 'extracting_audio';
    case Transcribing = 'transcribing';
    case Translating = 'translating';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::ExtractingAudio => 'Extracting audio...',
            self::Transcribing => 'Transcribing...',
            self::Translating => 'Translating subtitles...',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Determine if the transcription is still processing.
     */
    public function isProcessing(): bool
    {
        return in_array($this, [self::Pending, self::ExtractingAudio, self::Transcribing, self::Translating]);
    }
}
