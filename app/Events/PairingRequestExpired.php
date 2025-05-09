<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
// use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PairingRequestExpired implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public $senderId;
    public $receiverId;

    public function __construct($senderId, $receiverId)
    {
        $this->senderId = $senderId;
        $this->receiverId = $receiverId;
    }

    public function broadcastOn()
    {
        return [
            new Channel('pairing.' . $this->senderId),
            new Channel('pairing.' . $this->receiverId),
        ];
    }

    public function broadcastAs()
    {
        return 'pairing.norespond';
    }

    public function broadcastWith()
    {
        return [
            'message' => 'No response from receiver',
            'status' => 'expired',
        ];
    }
}

