<?php

namespace App\Jobs;

use App\Enums\TranscriptionStatus;
use App\Models\Transcription;
use App\Services\SrtGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Transcription as AiTranscription;
use Throwable;

use function Laravel\Ai\agent;

class ProcessTranscription implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600;

    public function __construct(public Transcription $transcription) {}

    /**
     * Execute the job.
     */
    public function handle(SrtGenerator $srtGenerator): void
    {
        try {
            $this->extractAudio();
            $srtContent = $this->transcribe($srtGenerator);
            $srtContent = $this->translate($srtContent);
        } catch (Throwable $e) {
            Log::error('Transcription processing failed', [
                'transcription_id' => $this->transcription->id,
                'error' => $e->getMessage(),
            ]);

            $this->transcription->update([
                'status' => TranscriptionStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract audio from the uploaded video file using FFmpeg.
     */
    private function extractAudio(): void
    {
        $this->transcription->update(['status' => TranscriptionStatus::ExtractingAudio]);

        $videoFullPath = Storage::disk('local')->path($this->transcription->video_path);
        $audioFilename = pathinfo($this->transcription->video_path, PATHINFO_FILENAME).'.mp3';
        $audioPath = 'audio/'.$audioFilename;
        $audioFullPath = Storage::disk('local')->path($audioPath);

        Storage::disk('local')->makeDirectory('audio');

        $result = Process::timeout(300)->run([
            'ffmpeg', '-i', $videoFullPath,
            '-vn',
            '-acodec', 'libmp3lame',
            '-ab', '64k',
            '-ar', '16000',
            '-ac', '1',
            '-y',
            $audioFullPath,
        ]);

        if ($result->failed()) {
            throw new \RuntimeException('FFmpeg audio extraction failed: '.$result->errorOutput());
        }

        $this->transcription->update(['audio_path' => $audioPath]);
    }

    /**
     * Transcribe the extracted audio using the Laravel AI SDK.
     */
    private function transcribe(SrtGenerator $srtGenerator): string
    {
        $this->transcription->update(['status' => TranscriptionStatus::Transcribing]);

        $transcript = AiTranscription::fromStorage($this->transcription->audio_path)
            ->language($this->transcription->language)
            ->diarize()
            ->timeout(300)
            ->generate();

        return $srtGenerator->generate($transcript->segments);
    }

    /**
     * Translate the SRT content to the export language if needed.
     */
    private function translate(string $srtContent): string
    {
        $exportLanguage = $this->transcription->export_language;

        if (! $exportLanguage || $exportLanguage === $this->transcription->language) {
            $this->transcription->update([
                'status' => TranscriptionStatus::Completed,
                'srt_content' => $srtContent,
            ]);

            return $srtContent;
        }

        $this->transcription->update(['status' => TranscriptionStatus::Translating]);

        $response = agent(
            instructions: 'You are an expert subtitle translator. Translate the following SRT content to the requested language. Preserve the exact SRT format including sequence numbers and timestamps. Only translate the text lines. Return only the translated SRT content with no additional commentary.',
        )->prompt("Translate the following SRT subtitles to {$exportLanguage}:\n\n{$srtContent}");

        $translatedSrt = $response->text;

        $this->transcription->update([
            'status' => TranscriptionStatus::Completed,
            'srt_content' => $translatedSrt,
        ]);

        return $translatedSrt;
    }
}
