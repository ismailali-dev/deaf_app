<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Common\ProfileResource;


class FriendshipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
       
        // Check if $this->sender exists
        if ($this->sender) {
            // If sender exists, return its profile resource
            return (new ProfileResource($this->sender))->resolve();
        }
    
        // If $this->sender does not exist, return an empty array
        return [];
    }
}
