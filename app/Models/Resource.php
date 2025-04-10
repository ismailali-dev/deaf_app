<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Searchable;

class Resource extends Model
{
    
    use SoftDeletes,Searchable;
    
    public $searchable = ['title','country','address'];
    
    public static function searchable()
    {
        return ['title','country','address'];
    }

}
