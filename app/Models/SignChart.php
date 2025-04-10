<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignChart extends Model
{
    use HasFactory;
    
    
    
    public function getIconAttribute($value)
    {
        // Check if the value exists and generate the full URL using the 'asset' helper
        return $value ? asset('public/storage/' . $value) : null;
    }
}
