<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

// use Illuminate\Queue\SerializesModels;

class RoomCreated implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public $user;
    public $roomId;

    public function __construct(User $user, $roomId)
    {
        $this->user = $user;
        $this->roomId = $roomId;
        
       
    }

    public function broadcastOn()
    {
       
        
        return [
            new PrivateChannel('room.' . $this->roomId),
            new PrivateChannel('pairing.' . $this->user->id),
        ];
    }



    public function broadcastAs()
    {
       
        return 'room.created';
    }

    
}
