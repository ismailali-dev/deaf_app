<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Word extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'word',
        'paired_number',
        'status'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function audioFiles(): MorphMany
    {
        return $this->morphMany(AudioFile::class, 'audioable');
    }
    
    public function getPairedNumberAttribute($value)
    {
         
            return (int)$value;
         
       
    }
    
}