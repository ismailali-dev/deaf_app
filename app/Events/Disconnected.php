<?php


namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class Disconnected implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public $sender;
    public $receiver;
    public $roomId;
    public $connectedCounts;

    public function __construct(User $sender, User $receiver, $roomId, $connectedCounts)
    {
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->roomId = $roomId;
        $this->connectedCounts = $connectedCounts;
    }

    public function broadcastOn()
    {
        return [
            new Channel('room.' . $this->roomId),
            new Channel('pairing.' . $this->receiver->id),
            // new Channel('pairing.' . $this->receiver->id),
        ];
    }

    public function broadcastAs()
    {
        return 'pairing.disconnected';
    }
}