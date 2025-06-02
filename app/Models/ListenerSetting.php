<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListenerSetting extends Model
{
    protected $fillable = [
        'user_id',
        'autosend',
        'notification',
        'mute',
    ];
    
    protected $casts = [
        'autosend' => 'boolean',
        'notification' => 'boolean',
        'mute' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}