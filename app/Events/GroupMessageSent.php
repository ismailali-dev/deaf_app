<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;

class GroupMessageSent implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $sender;
    public $message;
    public $roomId;
    public $method;
    public $reciever;
    
    

    public function __construct($sender, $message, $roomId,$method,$reciever=null)
    {
        $this->sender = $sender;
        $this->message = $message;
        $this->roomId = $roomId;
        $this->method = $method;
        $this->reciever = $reciever;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('room.' . $this->roomId);
    }

    public function broadcastAs()
    {
        
        if($this->reciever){
            return 'listener.message-sent';
        }
        else{
            return 'message-sent';
        }
        
    }
    
    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'roomId' => $this->roomId,
            'sender' => $this->sender,
            'method' => $this->method,
        ];
    }
}
