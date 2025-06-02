<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use App\Traits\Searchable;
use TCG\Voyager\Models\Role;
use Multicaret\Acquaintances\Traits\Friendable;
use Multicaret\Acquaintances\Traits\CanFollow;
use Multicaret\Acquaintances\Traits\CanBeFollowed;
use Multicaret\Acquaintances\Traits\CanLike;
use Multicaret\Acquaintances\Traits\CanBeLiked;
use Multicaret\Acquaintances\Traits\CanRate;
use Multicaret\Acquaintances\Traits\CanBeRated;
use Multicaret\Acquaintances\Models\Friendship;
use Carbon\Carbon;

class User extends \TCG\Voyager\Models\User implements JWTSubject
{
    
    use HasApiTokens, HasFactory, Notifiable,Searchable;
    use Friendable;
    use CanFollow, CanBeFollowed;
    use CanLike, CanBeLiked;
    use CanRate, CanBeRated;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
        'phone',
        'address',
        'date_of_birth',
        'gender',
        'avatar',
        'profile_status',
        'google_id',
        'latitude',
        'longitude',
        'current_room_id',
        'email_verified_at',  // Add this field to the fillable array
        'medical_conditions', 
        'race', 
        'armed'
    ];
    
    
    protected $appends = ['age'];


    public function getMedicalConditionsAttribute($value)
    {
        return explode(',', $value);
    }
    
    
    public function setMedicalConditionsAttribute($value)
    {
        $this->attributes['medical_conditions'] = is_array($value) ? implode(',', $value) : $value;
    }
    
    
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    // protected $appends = [
    //     'role_name', // Append the role_name attribute
    // ];
    
    public $searchable = ['username','name','email'];
    
    public function getAgeAttribute()
    {
        return $this->date_of_birth ? Carbon::parse($this->date_of_birth)->age : null;
    }
    
     public static function searchable()
    {
        return ['username','name'];
    }
    
    
    public function listenerSetting()
    {
        return $this->hasOne(ListenerSetting::class);
    }

    
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }
    
     public function getAvatarAttribute($value)
    {
         
        if (isset($value)) {
            
            return $value = url('public/storage/' . $value);
            
        } else {
            
           return $value = url('public/storage/default_avatar.png'); 
        }
       
    }
    public function getStatusAttribute($value)
    {
         
            return (int)$value;
         
       
    }
     public function getRoleIdAttribute($value)
    {
         
            return (int)$value;
         
       
    }
    
    
    
    
     /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
    
    // public function getRoleNameAttribute()
    // {
    //     return $this->role ? $this->role->name : 'Unknown';
    // }
    
   public function userDetail()
    {
        return $this->morphOne(UserDetail::class, 'detailable');
    }

    public function devices()
    {
        return $this->morphMany(\App\Models\Device::class, 'deviceable');
    }
    
    
    public function sentFriendships()
    {
        return $this->hasMany(Friendship::class, 'sender_id');
    }

    public function receivedFriendships()
    {
        return $this->hasMany(Friendship::class, 'recipient_id');
    }

    public function receivedFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'recipient_id');
    }
    
    public function sentFriendRequests()
    {
        return $this->hasMany(Friendship::class, 'sender_id');
    }
    
    // If you want a combined relation for both sent and received friendships
    public function friendships()
    {
        return $this->hasMany(Friendship::class, 'sender_id')
                    ->orWhere('recipient_id', $this->id);
    }
    
    public function acceptFriendRequest(User $sender)
    {
        $friendship = Friendship::whereSender($sender)
            ->whereRecipient($this)
            ->where('status', 'pending')
            ->firstOrFail();

        $friendship->update(['status' => 'accepted']);
    }

    // Method to deny a friend request
    public function denyFriendRequest(User $sender)
    {
        $friendship = Friendship::whereSender($sender)
            ->whereRecipient($this)
            ->where('status', 'pending')
            ->firstOrFail();

        $friendship->update(['status' => 'denied']);
    }
    
    public function hasFriendRequestFrom(User $sender)
    {
        return Friendship::whereSender($sender)
                         ->whereRecipient($this)
                         ->where('status', 'pending')
                         ->exists();
    }
    
    
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }
    
    public function sosRecordings()
    {
        return $this->hasMany(SosRecording::class);
    }
    
   
    
    

}
