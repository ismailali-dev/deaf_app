<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class PairingRequestRejected implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $sender;
    public $receiver;

    public function __construct(User $sender, User $receiver)
    {
        
      
        $this->sender = $sender;
        $this->receiver = $receiver;
    }

    public function broadcastOn()
    {
        return new Channel('pairing.'.$this->sender->id);
        
        
    }
    
     public function broadcastAs()
    {
        return 'pairing.rejected';
    }

    
    public function broadcastWith()
    {
        
        return [
            'message' => 'Your pairing request was rejected',
            'receiver_id' => $this->receiver->id,
            'receiver_name' => $this->receiver->name,
        ];
    }
}