<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['sender_id', 'receiver_id', 'message','attachment'];
    protected $casts = [
        'receiver_id' => 'integer',  // Ensure receiver_id is always cast to integer
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    
    public function getAttachmentAttribute($value)
    {
        // Check if the value exists and generate the full URL using the 'asset' helper
        return $value ? asset('public/storage/' . $value) : null;
    }
    
    public function toArray()
    {
        $array = parent::toArray(); 

        // Ensure message and attachment are included even if null
        $array['message'] = $this->message ?? null;
        $array['attachment'] = $this->attachment ?? null;

        return $array;
    }
}
