<?php

namespace App\Http\Resources\Deaf;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SentenceResourceList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        // Base URL for the public storage
        $baseUrl = Storage::url('');

        // Prepare the audio files with default values
        $audioFiles = [
            'audio_1' => null,
            'audio_2' => null,
            'audio_3' => null,
        ];

        // Assign audio file paths to specific keys with the base URL
        foreach ($this->audioFiles->take(3) as $index => $audioFile) {
            $key = 'audio_' . ($index + 1);
            // Prepend the base URL to the file path
            $audioFiles[$key] = url("public".Storage::url($audioFile->file_path));
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'sentence' => $this->sentence,
            'paired_number' => $this->paired_number,
            // Merge audio file paths into the response array
            'audio_1' => $audioFiles['audio_1'],
            'audio_2' => $audioFiles['audio_2'],
            'audio_3' => $audioFiles['audio_3'],
        ];
    }
}
