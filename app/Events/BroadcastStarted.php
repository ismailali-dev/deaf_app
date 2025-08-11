<?php

namespace App\Events;

use App\Models\Broadcast;
use App\Http\Resources\BroadcastResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class BroadcastStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcast;
    public $nearbyBroadcasts;
    public $allowedUserIds;
    public $isPublic;

    public function __construct($broadcast, $nearbyBroadcasts, $allowedUserIds = [], $isPublic = false)
    {
        $this->broadcast = new BroadcastResource($broadcast, $allowedUserIds);
        $this->nearbyBroadcasts = BroadcastResource::collection(
            $nearbyBroadcasts->map(fn($b) => new BroadcastResource($b, $allowedUserIds))
        );
        $this->allowedUserIds = $allowedUserIds;
        $this->isPublic = $isPublic;
    }

    public function broadcastOn()
    {
        if ($this->isPublic) {
            return new Channel('broadcasts');
        }

        $channels =  collect($this->allowedUserIds)
        ->map(fn($id) => new PrivateChannel('broadcasts.' . $id))
        ->values()
        ->all(); // Return array of PrivateChannels
        
       
        
        return $channels;
    }

    public function broadcastAs()
    {
        return 'broadcast.started';
    }
}
