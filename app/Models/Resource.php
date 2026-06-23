<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Searchable;

class Resource extends Model
{
    
    use SoftDeletes,Searchable;
    
    public $searchable = ['title','country','address'];
    protected $fillable = [
        'title',
        'country', 
        'contact_number',
        'email',
        'address',
        'about',
        'source_link',
        'status',
    ];
    
    public static function searchable()
    {
        return ['title','country','address'];
    }

  public function getSourceLinkAttribute($value)
    {
        if (empty($value)) {
            return $value;
        }

        $trimmed = trim($value);

        // If already has http:// or https:// leave it as is
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        // Add https:// prefix
        return 'https://' . $trimmed;
    }


    //   public function getAboutAttribute($value)
    // {
    //     if (empty($value)) {
    //         return $value;
    //     }

    //     $trimmed = trim($value);

    //     // Check if already wrapped in <p> tag
    //     if (str_starts_with($trimmed, '<p>') && str_ends_with($trimmed, '</p>')) {
    //         return $trimmed;
    //     }

    //     // Wrap each line in <p> tags
    //     $lines = array_filter(explode("\n", $trimmed));
        
    //     // if (empty($lines)) {
    //     //     return '<p>' . $trimmed . '</p>';
    //     // }

    //     return implode('', array_map(function ($line) {
    //         $line = trim($line);
    //         if (empty($line)) return '';
    //         // Skip if line already has <p> tag
    //         if (str_starts_with($line, '<p>')) return $line;
    //         return '<p>' . $line . '</p>';
    //     }, $lines));
    // }
    
    
    
        public function getCreatedAtAttribute($value)
    {
        return \Carbon\Carbon::parse($value)->format('M d Y');
    }


}
