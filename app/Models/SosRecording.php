<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SosRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 
        'emergency_type',
        'sos_type', 
        'file_type', 
        'file_path',
        'audio_file_path', 
        'video_file_path'
    ];

    /**
     * Define the relationship between SosRecording and User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function getAudioFilePathAttribute($value)
    {
        // Check if the value exists and generate the full URL using the 'asset' helper
        return $value ? asset('public/storage/' . $value) : null;
    }


    public function getVideoFilePathAttribute($value)
    {
        // Check if the value exists and generate the full URL using the 'asset' helper
        return $value ? asset('public/storage/' . $value) : null;
    }


    public function getFilePathAttribute($value)
    {
        // Check if the value exists and generate the full URL using the 'asset' helper
        return $value ? asset('public/storage/' . $value) : null;
    }


}