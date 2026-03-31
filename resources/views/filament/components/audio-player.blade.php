<div class="space-y-2">
    @if($getRecord()->recording_url)
        <audio controls class="w-full rounded-lg" preload="metadata">
            <source src="{{ $getRecord()->recording_url }}" type="audio/mpeg">
            Your browser does not support the audio element.
        </audio>
        @if($getRecord()->recording_duration_sec)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Duration: {{ gmdate('i:s', $getRecord()->recording_duration_sec) }}
            </p>
        @endif
    @else
        <p class="text-sm text-gray-400 italic">No recording available</p>
    @endif
</div>
