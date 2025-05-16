<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
// use Illuminate\Broadcasting\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\PrivateChannel;

class PairingRequestAcceptedCount implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    
    public $roomId;
    public $connectedCounts;

    public function __construct($roomId,$connectedCounts)
    {
    
        $this->roomId = $roomId;
        $this->connectedCounts=$connectedCounts ;
    }

    public function broadcastOn()
    {
        return [
            new Channel('room.' . $this->roomId),
        ];
    }

    public function broadcastAs()
    {
        return 'pairing.count';
    }

    
}
