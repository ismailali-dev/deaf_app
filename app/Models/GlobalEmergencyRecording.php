<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Aws\Polly\PollyClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GlobalEmergencyRecording extends Model
{
    use HasFactory;

    protected $table = 'global_emergency_recordings';

    protected $fillable = [
        'type', 'sentence', 'voice_path_male', 'voice_path_female'
    ];

    public $timestamps = true;

    protected static function boot()
    {
        parent::boot();
    
        static::saving(function ($model) {
            try {
                // Check if sentence or type is modified
                if ($model->isDirty(['sentence', 'type'])) {
                    $model->generateVoiceFiles();
                }
            } catch (AwsException $e) {
                \Log::error('AWS Polly error: ' . $e->getMessage());
            }
        });
    }
    
    private function generateVoiceFiles()
    {
        $pollyClient = new PollyClient([
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);
    
        $voiceIdMale = env('AWS_MALE_VOICE_ID', 'Matthew');
        $voiceIdFemale = env('AWS_FEMALE_VOICE_ID', 'Joanna');
    
        $sentenceText = $this->sentence;
        $type = Str::slug($this->type);
    
        // Generate and save Male voice
        $maleFileName = "global_voicemail_{$type}_male.mp3";
        $maleRelativePath = "global-emergency-recordings/{$maleFileName}";
        $this->voice_path_male = $this->synthesizeVoice($pollyClient, $sentenceText, $voiceIdMale, $maleRelativePath);
    
        // Generate and save Female voice
        $femaleFileName = "global_voicemail_{$type}_female.mp3";
        $femaleRelativePath = "global-emergency-recordings/{$femaleFileName}";
        $this->voice_path_female = $this->synthesizeVoice($pollyClient, $sentenceText, $voiceIdFemale, $femaleRelativePath);
    }

    private function synthesizeVoice($pollyClient, $text, $voiceId, $relativePath)
    {
        $fullPath = storage_path("app/public/{$relativePath}");

        $result = $pollyClient->synthesizeSpeech([
            'Text' => $text,
            'OutputFormat' => 'mp3',
            'VoiceId' => $voiceId,
        ]);

        $audioStream = $result->get('AudioStream');

        // Ensure directory exists
        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        // Save the audio content
        file_put_contents($fullPath, $audioStream);

        // Store in Laravel storage for access via asset helper
        Storage::disk('public')->put($relativePath, file_get_contents($fullPath));

        return $relativePath;
    }
    
    

    
}

