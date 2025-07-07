<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use Carbon\Carbon;

class BroadcastResource extends JsonResource
{
    private $friends;

    public function __construct($resource, $friends = [])
    {
        parent::__construct($resource);
        $this->friends = $friends; // Assign the friends list
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        
        $remainingTime = '00:00:00';
        
        if ($this->type === 'specific') {
            // Parse the end_time from the database
            $endTime = Carbon::parse($this->end_time)->setTimezone(config('app.timezone'));
        
            // Get the current time
            $currentTime = Carbon::now();
        
            // Calculate the difference between the end time and current time
            $difference = $endTime->diff($currentTime);
        
            // Check if the broadcast has ended
            if ($endTime->lessThanOrEqualTo($currentTime)) {
                $remainingTime = '00:00:00'; // Set to 0 hours, 0 minutes, 0 seconds
            } else {
                // Extract hours, minutes, and seconds from the difference
                $remainingHours = $difference->h;
                $remainingMinutes = $difference->i;
                $remainingSeconds = $difference->s;
        
                // Format the remaining time as HH:MM:SS
                $remainingTime = str_pad($remainingHours, 2, '0', STR_PAD_LEFT) . ':' .
                                 str_pad($remainingMinutes, 2, '0', STR_PAD_LEFT) . ':' .
                                 str_pad($remainingSeconds, 2, '0', STR_PAD_LEFT);
            }
        }


        
        
        return [
            'id' => $this->id,
            'role_id'=>$this->role_id,
            'user_id' => $this->user_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'duration' => $this->duration,
            'age_group_from' => $this->age_group_from,
            'age_group_to' => $this->age_group_to,
            'status' => $this->status,
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,

            // Adding user-related fields
            'user_avatar' => $this->user->avatar ?? null,
            'user_name' => $this->user->username ?? '',
            'name' => $this->user->name ?? '',
            'user_email' => $this->user->email ?? '',
            'user_age' => $this->user->date_of_birth ? \Carbon\Carbon::parse($this->user->date_of_birth)->age : null,

            // Add isFriend flag
            'isFriend' => in_array($this->user_id, $this->friends),
            'remaining_time' => $remainingTime,
        ];
    }
}

