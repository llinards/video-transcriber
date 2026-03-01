<?php

namespace Database\Factories;

use App\Enums\TranscriptionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transcription>
 */
class TranscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_filename' => fake()->word().'.mp4',
            'video_path' => 'videos/'.fake()->uuid().'.mp4',
            'language' => 'lv',
            'status' => TranscriptionStatus::Pending,
        ];
    }

    /**
     * Indicate the transcription has completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TranscriptionStatus::Completed,
            'audio_path' => 'audio/'.fake()->uuid().'.mp3',
            'srt_content' => "1\n00:00:00,000 --> 00:00:05,000\nSveiki, tas ir tests.\n",
        ]);
    }

    /**
     * Indicate the transcription has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TranscriptionStatus::Failed,
            'error_message' => 'Transcription failed: test error',
        ]);
    }
}
