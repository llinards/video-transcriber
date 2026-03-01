<?php

use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Models\Transcription;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Transcription as AiTranscription;

it('updates status to failed when ffmpeg extraction fails', function () {
    Storage::fake('local');
    Storage::disk('local')->put('videos/test.mp4', 'fake video content');

    Process::fake([
        '*ffmpeg*' => Process::result(
            output: '',
            errorOutput: 'ffmpeg error: invalid input',
            exitCode: 1,
        ),
    ]);

    $transcription = Transcription::factory()->create([
        'video_path' => 'videos/test.mp4',
    ]);

    (new ProcessTranscription($transcription))->handle(app(\App\Services\SrtGenerator::class));

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Failed)
        ->and($transcription->error_message)->toContain('FFmpeg audio extraction failed');
});

it('processes a transcription end to end with faked AI', function () {
    Storage::fake('local');
    Storage::disk('local')->put('videos/test.mp4', 'fake video content');
    Storage::disk('local')->makeDirectory('audio');
    Storage::disk('local')->put('audio/test.mp3', 'fake audio content');

    Process::fake([
        '*ffmpeg*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    AiTranscription::fake(function () {
        return 'Sveiki, šodien mēs runāsim par jaunām tēmām.';
    });

    $transcription = Transcription::factory()->create([
        'video_path' => 'videos/test.mp4',
    ]);

    (new ProcessTranscription($transcription))->handle(app(\App\Services\SrtGenerator::class));

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->audio_path)->not->toBeNull()
        ->and($transcription->srt_content)->not->toBeNull();

    AiTranscription::assertGenerated(function ($prompt) {
        return $prompt->language === 'lv';
    });
});
