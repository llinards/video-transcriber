<?php

use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Models\Transcription;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Validate('required|file|mimes:mp4,mov,avi,mkv,webm|max:512000')]
    public $video;

    public ?int $transcriptionId = null;

    /**
     * Store the uploaded video and dispatch the processing job.
     */
    public function transcribe(): void
    {
        $this->validate();

        $path = $this->video->store('videos', 'local');

        $transcription = Transcription::create([
            'original_filename' => $this->video->getClientOriginalName(),
            'video_path' => $path,
            'status' => TranscriptionStatus::Pending,
        ]);

        ProcessTranscription::dispatch($transcription);

        $this->transcriptionId = $transcription->id;
        $this->reset('video');
    }

    /**
     * Download the generated SRT file.
     */
    public function downloadSrt(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $transcription = Transcription::findOrFail($this->transcriptionId);

        $filename = pathinfo($transcription->original_filename, PATHINFO_FILENAME).'.srt';

        return response()->streamDownload(function () use ($transcription) {
            echo $transcription->srt_content;
        }, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Reset the component to allow another transcription.
     */
    public function startOver(): void
    {
        $this->reset(['video', 'transcriptionId']);
    }

    /**
     * Get the current transcription model (if any).
     */
    public function getTranscriptionProperty(): ?Transcription
    {
        if (! $this->transcriptionId) {
            return null;
        }

        return Transcription::find($this->transcriptionId);
    }
};
?>

<div>
    @if (! $this->transcription)
        {{-- Upload Form --}}
        <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
            <form wire:submit="transcribe">
                <div
                    x-data="{ uploading: false, progress: 0 }"
                    x-on:livewire-upload-start="uploading = true"
                    x-on:livewire-upload-finish="uploading = false"
                    x-on:livewire-upload-cancel="uploading = false"
                    x-on:livewire-upload-error="uploading = false"
                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                >
                    <label for="video" class="mb-2 block text-sm font-medium text-gray-700">
                        Video File
                    </label>

                    <div class="flex items-center justify-center rounded-lg border-2 border-dashed border-gray-300 px-6 py-10 transition hover:border-gray-400">
                        <div class="text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <div class="mt-4">
                                <label for="video" class="cursor-pointer rounded-md font-semibold text-indigo-600 hover:text-indigo-500">
                                    Choose a video file
                                    <input id="video" type="file" wire:model="video" accept=".mp4,.mov,.avi,.mkv,.webm" class="sr-only" />
                                </label>
                            </div>

                            <p class="mt-1 text-xs text-gray-500">
                                MP4, MOV, AVI, MKV, or WebM up to 500MB
                            </p>
                        </div>
                    </div>

                    {{-- Upload Progress --}}
                    <div x-show="uploading" x-cloak class="mt-4">
                        <div class="flex items-center gap-3">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                                <div class="h-2 rounded-full bg-indigo-600 transition-all duration-300" x-bind:style="'width: ' + progress + '%'"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-600" x-text="progress + '%'"></span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Uploading...</p>
                    </div>

                    {{-- Selected File Name --}}
                    @if ($video)
                        <div class="mt-4 flex items-center gap-2 rounded-lg bg-gray-50 px-4 py-3">
                            <svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm text-gray-700">{{ $video->getClientOriginalName() }}</span>
                        </div>
                    @endif
                </div>

                @error('video')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    class="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
                    wire:loading.attr="disabled"
                    wire:target="video"
                    @if (! $video) disabled @endif
                >
                    <span wire:loading.remove wire:target="transcribe">Transcribe Video</span>
                    <span wire:loading wire:target="transcribe">Starting...</span>
                </button>
            </form>
        </div>
    @elseif ($this->transcription->isProcessing())
        {{-- Processing State --}}
        <div wire:poll.2s class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
            <div class="text-center">
                <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50">
                    <svg class="h-8 w-8 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <h2 class="text-lg font-semibold text-gray-900">Processing your video</h2>
                <p class="mt-2 text-gray-500">{{ $this->transcription->status->label() }}</p>

                {{-- Status Steps --}}
                <div class="mx-auto mt-8 max-w-xs text-left">
                    @php
                        $steps = [
                            ['status' => TranscriptionStatus::Pending, 'label' => 'Queued for processing', 'order' => 0],
                            ['status' => TranscriptionStatus::ExtractingAudio, 'label' => 'Extracting audio from video', 'order' => 1],
                            ['status' => TranscriptionStatus::Transcribing, 'label' => 'Transcribing audio to text', 'order' => 2],
                        ];
                        $currentStatus = $this->transcription->status;
                        $statusValues = [
                            TranscriptionStatus::Pending->value => 0,
                            TranscriptionStatus::ExtractingAudio->value => 1,
                            TranscriptionStatus::Transcribing->value => 2,
                        ];
                        $currentOrder = $statusValues[$currentStatus->value] ?? 0;
                    @endphp

                    <ol class="space-y-4">
                        @foreach ($steps as $step)
                            @php
                                $stepOrder = $step['order'];
                                $isComplete = $stepOrder < $currentOrder;
                                $isCurrent = $stepOrder === $currentOrder;
                            @endphp
                            <li class="flex items-center gap-3">
                                @if ($isComplete)
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100">
                                        <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </span>
                                @elseif ($isCurrent)
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100">
                                        <span class="h-2 w-2 animate-pulse rounded-full bg-indigo-600"></span>
                                    </span>
                                @else
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100">
                                        <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                                    </span>
                                @endif

                                <span class="{{ $isCurrent ? 'font-medium text-gray-900' : ($isComplete ? 'text-gray-500' : 'text-gray-400') }}">
                                    {{ $step['label'] }}
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <p class="mt-6 text-xs text-gray-400">This may take a few minutes depending on video length.</p>
            </div>
        </div>
    @elseif ($this->transcription->isCompleted())
        {{-- Completed State --}}
        <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-50">
                    <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">Transcription Complete</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $this->transcription->original_filename }}</p>
            </div>

            {{-- SRT Preview --}}
            <div class="mb-6">
                <label class="mb-2 block text-sm font-medium text-gray-700">SRT Content</label>
                <textarea
                    readonly
                    rows="12"
                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 font-mono text-sm text-gray-700"
                >{{ $this->transcription->srt_content }}</textarea>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3">
                <button
                    wire:click="downloadSrt"
                    class="flex-1 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                >
                    Download SRT
                </button>
                <button
                    wire:click="startOver"
                    class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
                >
                    Transcribe Another
                </button>
            </div>
        </div>
    @elseif ($this->transcription->isFailed())
        {{-- Failed State --}}
        <div class="rounded-xl border border-red-200 bg-white p-8 shadow-sm">
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-50">
                    <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">Transcription Failed</h2>
                <p class="mt-2 text-sm text-red-600">{{ $this->transcription->error_message }}</p>
            </div>

            <button
                wire:click="startOver"
                class="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
            >
                Try Again
            </button>
        </div>
    @endif
</div>
