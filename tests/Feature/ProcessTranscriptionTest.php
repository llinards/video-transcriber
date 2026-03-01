<?php

use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Models\Transcription;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\AnonymousAgent;
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

it('passes the transcription language to the AI SDK', function () {
    Storage::fake('local');
    Storage::disk('local')->put('videos/test.mp4', 'fake video content');
    Storage::disk('local')->makeDirectory('audio');
    Storage::disk('local')->put('audio/test.mp3', 'fake audio content');

    Process::fake([
        '*ffmpeg*' => Process::result(output: '', errorOutput: '', exitCode: 0),
    ]);

    AiTranscription::fake(function () {
        return 'Hello, today we will talk about new topics.';
    });

    $transcription = Transcription::factory()->create([
        'video_path' => 'videos/test.mp4',
        'language' => 'en',
    ]);

    (new ProcessTranscription($transcription))->handle(app(\App\Services\SrtGenerator::class));

    AiTranscription::assertGenerated(function ($prompt) {
        return $prompt->language === 'en';
    });
});

it('does not translate when export language is null', function () {
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
        'export_language' => null,
    ]);

    (new ProcessTranscription($transcription))->handle(app(\App\Services\SrtGenerator::class));

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->srt_content)->not->toBeNull();

    AnonymousAgent::assertNeverPrompted();
});

it('does not translate when export language matches spoken language', function () {
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
        'language' => 'lv',
        'export_language' => 'lv',
    ]);

    (new ProcessTranscription($transcription))->handle(app(\App\Services\SrtGenerator::class));

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed);

    AnonymousAgent::assertNeverPrompted();
});

it('translates SRT content when export language differs from spoken language', function () {
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

    AnonymousAgent::fake(["1\n00:00:00,000 --> 00:00:05,000\nHello, today we will talk about new topics.\n"]);

    $transcription = Transcription::factory()->create([
        'video_path' => 'videos/test.mp4',
        'language' => 'lv',
        'export_language' => 'en',
    ]);

    (new ProcessTranscription($transcription))->handle(app(\App\Services\SrtGenerator::class));

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->srt_content)->toContain('Hello, today we will talk about new topics.');

    AnonymousAgent::assertPrompted(function ($prompt) {
        return $prompt->contains('en');
    });
});
