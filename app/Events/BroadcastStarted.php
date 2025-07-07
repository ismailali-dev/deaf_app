<?php

namespace App\Events;

use App\Models\Broadcast;
use App\Http\Resources\BroadcastResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class BroadcastStarted implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $broadcast;
    public $nearbyBroadcasts;
    public $friends;

    /**
     * Create a new event instance.
     */


    public function __construct($broadcast, $nearbyBroadcasts, $friends)
    {
        $this->friends = $friends; // Save the friends list

        // Pass friends to the resources
        $this->broadcast = new BroadcastResource($broadcast, $friends);
        $this->nearbyBroadcasts = BroadcastResource::collection(
            $nearbyBroadcasts->map(function ($b) use ($friends) {
                return new BroadcastResource($b, $friends);
            })
        );
        
    }
    
    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return new Channel('broadcasts');
    }

    /**
     * Get the event name the event should broadcast as.
     */
    public function broadcastAs()
    {
        return 'broadcast.started';
    }
}
