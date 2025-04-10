<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sentence extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'sentence',
        'paired_number',
        'status'
    ];
    
     public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function audioFiles()
    {
        return $this->morphMany(AudioFile::class, 'audioable');
    }
    
}
