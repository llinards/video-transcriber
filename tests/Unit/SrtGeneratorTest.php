<?php

use App\Services\SrtGenerator;
use Laravel\Ai\Responses\Data\TranscriptionSegment;

beforeEach(function () {
    $this->generator = new SrtGenerator;
});

it('generates empty string for empty segments', function () {
    $segments = collect();

    expect($this->generator->generate($segments))->toBe('');
});

it('generates valid SRT content from a single segment', function () {
    $segments = collect([
        new TranscriptionSegment(
            text: 'Sveiki, tas ir tests.',
            speaker: 'Speaker 1',
            startSeconds: 0.0,
            endSeconds: 5.5,
        ),
    ]);

    $expected = "1\n00:00:00,000 --> 00:00:05,500\nSveiki, tas ir tests.\n";

    expect($this->generator->generate($segments))->toBe($expected);
});

it('generates valid SRT content from multiple segments', function () {
    $segments = collect([
        new TranscriptionSegment(
            text: 'Pirmais teikums.',
            speaker: 'Speaker 1',
            startSeconds: 0.0,
            endSeconds: 3.2,
        ),
        new TranscriptionSegment(
            text: 'Otrais teikums.',
            speaker: 'Speaker 1',
            startSeconds: 3.5,
            endSeconds: 7.8,
        ),
        new TranscriptionSegment(
            text: 'Trešais teikums.',
            speaker: 'Speaker 2',
            startSeconds: 8.0,
            endSeconds: 12.0,
        ),
    ]);

    $result = $this->generator->generate($segments);

    expect($result)
        ->toContain("1\n00:00:00,000 --> 00:00:03,200\nPirmais teikums.")
        ->toContain("2\n00:00:03,500 --> 00:00:07,800\nOtrais teikums.")
        ->toContain("3\n00:00:08,000 --> 00:00:12,000\nTrešais teikums.");
});

it('formats timecodes correctly for long durations', function () {
    expect($this->generator->formatTimecode(0.0))->toBe('00:00:00,000')
        ->and($this->generator->formatTimecode(1.5))->toBe('00:00:01,500')
        ->and($this->generator->formatTimecode(61.0))->toBe('00:01:01,000')
        ->and($this->generator->formatTimecode(3661.123))->toBe('01:01:01,123')
        ->and($this->generator->formatTimecode(7200.999))->toBe('02:00:00,999');
});

it('trims whitespace from segment text', function () {
    $segments = collect([
        new TranscriptionSegment(
            text: '  Teksts ar atstarpēm.  ',
            speaker: 'Speaker 1',
            startSeconds: 0.0,
            endSeconds: 3.0,
        ),
    ]);

    $result = $this->generator->generate($segments);

    expect($result)->toContain('Teksts ar atstarpēm.');
});
