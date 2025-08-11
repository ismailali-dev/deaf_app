<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Events\BroadcastEnded;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class EndBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $broadcastId;

    public function __construct(int $broadcastId)
    {
        $this->broadcastId = $broadcastId;
    }

    public function handle()
    {
        $broadcast = Broadcast::find($this->broadcastId);
    
        if (!$broadcast || $broadcast->status === 'inactive') {
            return;
        }
    
        $broadcast->update(['status' => 'inactive']);
    
        $isPublic = $broadcast->type === 'all';
    
        // Determine allowed user IDs
        $allowedUserIds = $isPublic
            ? $broadcast->user->getFriends()->pluck('id')->toArray()
            : json_decode($broadcast->allowed_user_ids, true) ?? [];
    
        // Fire event to viewers
        broadcast(new BroadcastEnded($broadcast, $allowedUserIds, $isPublic, false)); // false here
    
        // Fire event to owner only if autoEnded
        broadcast(new BroadcastEnded($broadcast, [], false, true));
    }


    public function uniqueId()
    {
        return $this->broadcastId;
    }
}
