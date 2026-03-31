<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupTtsAudio extends Command
{
    protected $signature = 'echjokes:cleanup-audio {--hours=1 : Delete files older than this many hours}';
    protected $description = 'Delete old TTS audio files from storage';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours)->timestamp;
        $deleted = 0;

        $files = Storage::disk('local')->files('audio');

        foreach ($files as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            if ($lastModified < $cutoff) {
                Storage::disk('local')->delete($file);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} old audio files.");

        return self::SUCCESS;
    }
}
