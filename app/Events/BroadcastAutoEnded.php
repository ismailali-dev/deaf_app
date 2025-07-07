<?php 

namespace App\Events;

use App\Models\Broadcast;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Http\Resources\BroadcastResource;

class BroadcastAutoEnded implements ShouldBroadcastNow
{
    use Dispatchable,InteractsWithSockets, SerializesModels;

    public $broadcast;


    /**
     * Create a new event instance.
     *
     * @param Broadcast $broadcast
     * @param Collection $nearbyBroadcasts
     * @return void
     */
    public function __construct(Broadcast $broadcast)
    {
        $this->broadcast = new BroadcastResource($broadcast); // Use the resource for broadcast
        
        
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel
    */
    public function broadcastOn()
    {
        // Use a public channel for the broadcast event
        return new Channel('broadcasts');
    }
    
    

     public function broadcastAs()
    {
        return 'broadcast.autoended';
    }

    /**
     * Get the broadcasted data.
     *
     * @return array
     */
    
}