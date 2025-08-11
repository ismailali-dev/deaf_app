<?php

namespace App\Events;

use App\Models\Broadcast;
use App\Http\Resources\BroadcastResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class BroadcastEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcast;
    public $allowedUserIds;
    public $isPublic;
    public $autoEnded;

    public function __construct(Broadcast $broadcast, ?array $allowedUserIds = null, bool $isPublic = false,$autoEnded = false)
    {
        $this->broadcast = new BroadcastResource($broadcast);
        $this->allowedUserIds = $allowedUserIds;
        $this->isPublic = $isPublic;
        $this->autoEnded = $autoEnded;
    }

    public function broadcastOn()
    {
        $channels = [];
    
        // Viewer event: only when not autoEnded
        if (!$this->autoEnded) {
            if (!$this->isPublic && $this->allowedUserIds) {
                $viewerChannels = collect($this->allowedUserIds)
                    ->map(fn($id) => new PrivateChannel('broadcasts.' . $id))
                    ->toArray();
                $channels = array_merge($channels, $viewerChannels);
            }
    
            if ($this->isPublic) {
                $channels[] = new Channel('broadcasts');
            }
        }
    
        // Auto-end event for owner only
        if ($this->autoEnded) {
            $channels[] = new PrivateChannel('broadcasts.' . $this->broadcast->user_id);
        }
    
        return $channels;
    }
    
    public function broadcastAs()
    {
        return $this->autoEnded ? 'broadcast.autoended' : 'broadcast.ended';
    }

}

