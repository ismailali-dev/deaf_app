<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioFile extends Model
{
    use HasFactory;
      
    protected $fillable = [
        'audioable_id',
        'audioable_type',
        'file_path',
        'features'
    ];
    
    protected $casts = [
        'features' => 'json',  // Laravel will convert to and from JSON automatically
    ];
    
    public function audioable()
    {
        return $this->morphTo();
    }
}
