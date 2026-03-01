<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
            <header class="mb-10 text-center">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Video Transcriber</h1>
                <p class="mt-2 text-gray-500">Upload a video and get an SRT transcription file</p>
            </header>

            <main>
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
