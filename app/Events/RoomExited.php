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

class RoomExited implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public $roomId;
    public $exitedUser;

    public function __construct($roomId, User $exitedUser)
    {
        $this->roomId = $roomId;
        $this->exitedUser = $exitedUser;
        
        // dd($this->roomId);
    }

    public function broadcastOn()
    {
        return new Channel('room.' . $this->roomId);
    }

    public function broadcastAs()
    {
        return 'room.exited';
    }

    public function broadcastWith()
    {
        return [
            'message' => 'User has left the chat',
            'room_id' => $this->roomId,
            'exited_user' => $this->exitedUser
        ];
    }
}