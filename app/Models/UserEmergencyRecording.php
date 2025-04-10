<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserEmergencyRecording extends Model
{
    use HasFactory;

    // Table name
    protected $table = 'user_emergency_recordings';

    // Fillable fields
    protected $fillable = [
        'user_id',        // User who uploaded the recording
        'type',           // fire, health, crime, report/witness
        'sentence',       // User-defined sentence
        'voice_path',     // Path to the user-uploaded voice file
    ];

    // Relationship with User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function getVoicePathAttribute($value)
    {
        // Check if the value exists and generate the full URL using the 'asset' helper
        return $value ? asset('public/storage/' . $value) : null;
    }
}
