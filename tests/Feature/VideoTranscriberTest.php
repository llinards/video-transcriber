<?php

use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Models\Transcription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('renders the upload form on the home page', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Video Transcriber');
});

it('validates that a video file is required', function () {
    Livewire::test('video-transcriber')
        ->call('transcribe')
        ->assertHasErrors(['video' => 'required']);
});

it('validates the video file type', function () {
    Storage::fake('local');

    Livewire::test('video-transcriber')
        ->set('video', UploadedFile::fake()->create('document.pdf', 1024))
        ->call('transcribe')
        ->assertHasErrors(['video']);
});

it('uploads a video and dispatches the processing job', function () {
    Storage::fake('local');
    Queue::fake();

    Livewire::test('video-transcriber')
        ->set('video', UploadedFile::fake()->create('test-video.mp4', 5000, 'video/mp4'))
        ->call('transcribe')
        ->assertHasNoErrors()
        ->assertSet('video', null);

    expect(Transcription::count())->toBe(1);

    $transcription = Transcription::first();
    expect($transcription->original_filename)->toBe('test-video.mp4')
        ->and($transcription->status)->toBe(TranscriptionStatus::Pending);

    Queue::assertPushed(ProcessTranscription::class);
});

it('shows processing state when transcription is in progress', function () {
    $transcription = Transcription::factory()->create([
        'status' => TranscriptionStatus::ExtractingAudio,
    ]);

    Livewire::test('video-transcriber')
        ->set('transcriptionId', $transcription->id)
        ->assertSee('Processing your video')
        ->assertSee('Extracting audio from video');
});

it('shows completed state with SRT content', function () {
    $transcription = Transcription::factory()->completed()->create();

    Livewire::test('video-transcriber')
        ->set('transcriptionId', $transcription->id)
        ->assertSee('Transcription Complete')
        ->assertSee('Download SRT');
});

it('shows failed state with error message', function () {
    $transcription = Transcription::factory()->failed()->create();

    Livewire::test('video-transcriber')
        ->set('transcriptionId', $transcription->id)
        ->assertSee('Transcription Failed')
        ->assertSee('Transcription failed: test error')
        ->assertSee('Try Again');
});

it('can start over after completion', function () {
    $transcription = Transcription::factory()->completed()->create();

    Livewire::test('video-transcriber')
        ->set('transcriptionId', $transcription->id)
        ->call('startOver')
        ->assertSet('transcriptionId', null)
        ->assertSet('video', null);
});

it('stores the selected language when transcribing', function () {
    Storage::fake('local');
    Queue::fake();

    Livewire::test('video-transcriber')
        ->set('video', UploadedFile::fake()->create('test-video.mp4', 5000, 'video/mp4'))
        ->set('language', 'en')
        ->call('transcribe')
        ->assertHasNoErrors();

    $transcription = Transcription::first();
    expect($transcription->language)->toBe('en');
});

it('defaults language to latvian', function () {
    Storage::fake('local');
    Queue::fake();

    Livewire::test('video-transcriber')
        ->set('video', UploadedFile::fake()->create('test-video.mp4', 5000, 'video/mp4'))
        ->call('transcribe')
        ->assertHasNoErrors();

    $transcription = Transcription::first();
    expect($transcription->language)->toBe('lv');
});

it('validates that language must be a supported value', function () {
    Storage::fake('local');

    Livewire::test('video-transcriber')
        ->set('video', UploadedFile::fake()->create('test-video.mp4', 5000, 'video/mp4'))
        ->set('language', 'xx')
        ->call('transcribe')
        ->assertHasErrors(['language' => 'in']);
});

it('stores the export language when provided', function () {
    Storage::fake('local');
    Queue::fake();

    Livewire::test('video-transcriber')
        ->set('video', UploadedFile::fake()->create('test-video.mp4', 5000, 'video/mp4'))
        ->set('language', 'lv')
        ->set('exportLanguage', 'en')
        ->call('transcribe')
        ->assertHasNoErrors();

    $transcription = Transcription::first();
    expect($transcription->export_language)->toBe('en');
});

it('stores null export language when empty', function () {
    Storage::fake('local');
    Queue::fake();

    Livewire::test('video-transcriber')
        ->set('video', UploadedFile::fake()->create('test-video.mp4', 5000, 'video/mp4'))
        ->set('exportLanguage', '')
        ->call('transcribe')
        ->assertHasNoErrors();

    $transcription = Transcription::first();
    expect($transcription->export_language)->toBeNull();
});

it('validates that export language must be a supported value', function () {
    Storage::fake('local');

    Livewire::test('video-transcriber')
        ->set('video', UploadedFile::fake()->create('test-video.mp4', 5000, 'video/mp4'))
        ->set('exportLanguage', 'xx')
        ->call('transcribe')
        ->assertHasErrors(['exportLanguage' => 'in']);
});

it('can download the SRT file', function () {
    $transcription = Transcription::factory()->completed()->create([
        'original_filename' => 'my-video.mp4',
        'srt_content' => "1\n00:00:00,000 --> 00:00:05,000\nTest content.\n",
    ]);

    Livewire::test('video-transcriber')
        ->set('transcriptionId', $transcription->id)
        ->call('downloadSrt')
        ->assertFileDownloaded('my-video.srt');
});
