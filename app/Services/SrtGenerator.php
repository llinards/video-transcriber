<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\TranscriptionSegment;

class SrtGenerator
{
    /**
     * Generate SRT content from transcription segments.
     *
     * @param  Collection<int, TranscriptionSegment>  $segments
     */
    public function generate(Collection $segments): string
    {
        if ($segments->isEmpty()) {
            return '';
        }

        return $segments
            ->values()
            ->map(function (TranscriptionSegment $segment, int $index) {
                $sequenceNumber = $index + 1;
                $startTimecode = $this->formatTimecode($segment->startSeconds);
                $endTimecode = $this->formatTimecode($segment->endSeconds);
                $text = trim($segment->text);

                return "{$sequenceNumber}\n{$startTimecode} --> {$endTimecode}\n{$text}";
            })
            ->implode("\n\n")."\n";
    }

    /**
     * Format seconds into SRT timecode format (HH:MM:SS,mmm).
     */
    public function formatTimecode(float $seconds): string
    {
        $hours = intdiv((int) $seconds, 3600);
        $minutes = intdiv((int) $seconds % 3600, 60);
        $secs = (int) $seconds % 60;
        $milliseconds = (int) round(($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $milliseconds);
    }
}
