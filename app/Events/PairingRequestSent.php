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

class PairingRequestSent implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public $sender;
    public $receiver;

    public function __construct(User $sender, User $receiver)
    {
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->expiresAt = now()->addMinutes(5); // Expiry time
    }

    public function broadcastOn()
    {
        return new Channel('pairing.'.$this->receiver->id);
    }

    public function broadcastAs()
    {
        return 'pairing.request';
    }
    
     public function broadcastWith()
    {
        return [
            'message' => 'New pairing request received!',
            'sender' => $this->sender
        ];
    }
}