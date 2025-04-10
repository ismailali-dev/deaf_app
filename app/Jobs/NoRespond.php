<?php
namespace App\Jobs;

use App\Events\PairingRequestExpired;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class NoRespond implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $senderId;
    protected $receiverId;

    public function __construct($senderId, $receiverId)
    {
        $this->senderId = $senderId;
        $this->receiverId = $receiverId;
        
       
    }
    
    
    public function handle()
{
    $jobKey = 'pairing_request_' . $this->senderId;
    
    // Check if request was accepted before running this job
    if (!Cache::has($jobKey)) {
        Log::info("Pairing request already accepted. Job skipped.");
        return;
    }

    // Remove pairing request from cache
    Cache::forget($jobKey);
    
    // Broadcast event for expiry
    Log::info("Job running, broadcasting PairingRequestExpired...");
    broadcast(new PairingRequestExpired($this->senderId, $this->receiverId));
}


//     public function handle()
//     {
//         $cacheKey = 'pairing_request_' . $this->senderId;
    
//         Log::info("Job executing: NoRespond for sender {$this->senderId}, receiver {$this->receiverId}");
    
//         if (Cache::has($cacheKey)) {
//             Cache::forget($cacheKey);
//             Log::info("Pairing request expired. Broadcasting event...");
    
//             // Broadcast event
//             broadcast(new PairingRequestExpired($this->senderId, $this->receiverId));
    
//             Log::info("Broadcasting completed.");
//         } else {
//             Log::warning("Cache key not found: {$cacheKey}");
//         }
// }

    // public function handle()
    // {
        
    //     $cacheKey = 'pairing_request_' . $this->senderId;
        
    //     // if (Cache::has($cacheKey)) {
    //     //     // Remove pairing request from cache
           
    //     //     Cache::forget($cacheKey);
            
    //         // Notify sender that receiver did not respond
    //         Log::info("Job running, now broadcasting event...");
    //         broadcast(new PairingRequestExpired($this->senderId, $this->receiverId));
    //         Log::info("Broadcast done.");
    //     // }
    //     //  Cache::forget($cacheKey);
        
        
    // }
}
