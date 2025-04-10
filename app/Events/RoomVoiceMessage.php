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

    public function __construct($roomId, User $sender, User $receiver, $voicePath)
    {
        $this->roomId = $roomId;
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->voicePath = $voicePath;
    }

    public function broadcastOn()
    {
        return new Channel('room.' . $this->roomId);
    }

    public function broadcastAs()
    {
        return 'room.voice.message';
    }

    public function broadcastWith()
    {
        return [
            'message' => 'New voice message received!',
            'room_id' => $this->roomId,
            'sender' => $this->sender,
            'receiver' => $this->receiver,
            'voice_path' => asset('public/storage/' . $this->voicePath),
        ];
    }
}
