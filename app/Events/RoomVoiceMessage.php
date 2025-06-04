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

class RoomVoiceMessage implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public $roomId;
    public $sender;
    public $receiver;
    public $voicePath;
    public $method;

    public function __construct($roomId, User $sender, User $receiver, $voicePath,$method)
    {
        $this->roomId = $roomId;
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->voicePath = $voicePath;
        $this->method = $method;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('room.' . $this->roomId);
    }

    public function broadcastAs()
    {
        if($this->receiver->role_id == 2){
            return 'listener.room.voice.message';
        }
        else{
            return 'room.voice.message';
        }
        
    }

    public function broadcastWith()
    {
        return [
            'message' => 'New voice message received!',
            'room_id' => $this->roomId,
            'sender' => $this->sender,
            'receiver' => $this->receiver,
            'method' => $this->method,
            'voice_path' => asset('public/storage/' . $this->voicePath),
        ];
    }
}
