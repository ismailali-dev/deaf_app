<?php

namespace App\Jobs;

use App\Models\AudioFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class ExtractAudioFeatures implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    public function __construct(public int $audioFileId) {}

    public function handle(): void
    {
        $audioFile = AudioFile::find($this->audioFileId);

        if (!$audioFile || $audioFile->features !== null) {
            return;
        }

        $fullPath = public_path('storage/' . $audioFile->file_path);
        if (!is_file($fullPath)) {
            throw new RuntimeException("Audio file not found: {$audioFile->file_path}");
        }

        $baseCommand = config('python.feature_script');
        if (!$baseCommand) {
            throw new RuntimeException('PYTHON_FEATURE_SCRIPT is not configured.');
        }

        $output = shell_exec($baseCommand . ' ' . escapeshellarg($fullPath));
        $result = json_decode($output ?? '', true);

        if (!is_array($result) || ($result['status'] ?? null) !== 'success' || !isset($result['features'])) {
            throw new RuntimeException($result['message'] ?? 'Feature extraction failed.');
        }

        $audioFile->update(['features' => $result['features']]);
    }
}
