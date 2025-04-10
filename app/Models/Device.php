<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use SoftDeletes;

   protected $fillable = [
        'device_token',
        'device_type',
        'device_info',
        'deviceable_id',
        'deviceable_type',
    ];
    protected $casts = [
        'device_info' => 'array'
    ];

    public function deviceable()
    {
        return $this->morphTo();
    }

}
