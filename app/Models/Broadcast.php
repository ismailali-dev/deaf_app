<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Broadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'duration',
        'age_group_from',
        'age_group_to',
        'status',
        'end_time',
        'broadcast_for_all',
        'type',
        'allowed_user_ids',
       
    ];
    
    
    protected $appends = ['role_id'];

public function getRoleIdAttribute()
{
    return $this->user->role_id ?? null;
}

public function user()
{
    return $this->belongsTo(User::class);
}
    
    //protected $appends = ['user_avatar', 'user_name', 'name', 'user_email','user_age'];
    

    
    
   
    
    public function getDurationAttribute($value)
    {
        return $value ?? '';
    }
    
    public function getAgeGroupFromAttribute($value)
    {
        return $value ?? '';
    }
    
    public function getAgeGroupToAttribute($value)
    {
        return $value ?? '';
    }
    
    
    // public function getUserAvatarAttribute()
    // {
    //     return $this->user ? $this->user->avatar : null;
    // }
    
    // public function getUsernameAttribute()
    // {
    //     return $this->user ? $this->user->username : null;
    // }
    
    // public function getNameAttribute()
    // {
    //     return $this->user ? $this->user->name : null;
    // }
    
    // public function getUserEmailAttribute()
    // {
    //     return $this->user ? $this->user->email : null;
    // }
    
    // public function getUserAgeAttribute()
    // {
        
    //     return $this->user->date_of_birth ? Carbon::parse($this->user->date_of_birth)->age : null;
        
        
    // }
    
    

    
    
}
