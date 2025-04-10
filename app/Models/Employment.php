<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Searchable;

class Employment extends Model
{
    
    use SoftDeletes,Searchable;
    
    protected $fillable = [
        'title',
        'address',
        'short_description',
        'long_description',
        'status',
        'employe_type',
        'company_name',
        'offer_salary',
        'application_link',
    ];
    
      protected $casts = [
        'status' => 'integer',
        'employe_type' => 'integer',
        'offer_salary' => 'integer',
    ];
    
    
    public $searchable = ['title','company_name','address'];
    
    public static function searchable()
    {
        return ['title','company_name','address'];
    }
    

}
