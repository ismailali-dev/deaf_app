<?php

namespace App\Jobs;

use App\Models\Broadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Pusher\Pusher;
use App\Events\BroadcastAutoEnded;

class EndBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $broadcastId;
    /**
     * Create a new job instance.
     *
     * @param int $broadcastId
     */
    public function __construct(int $broadcastId)
    {
        $this->broadcastId = $broadcastId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
     
    public function handle()
    {
        $broadcast = Broadcast::find($this->broadcastId);
        
        if (!$broadcast || $broadcast->status === 'inactive') {
            return; // Exit without executing the job
        }
    
    
        if ($broadcast && $broadcast->status === 'active') {
            // Mark the broadcast as ended
            $broadcast->update(['status' => 'inactive']);
    
            // Trigger the BroadcastEnded event
            event(new BroadcastAutoEnded($broadcast));
        }
    }
    
    public function uniqueId()
    {
        return $this->broadcastId; // Unique identifier
    }

   
}
