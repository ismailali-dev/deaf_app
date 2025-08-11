<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\FirebaseService;

class FirebaseChannel
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    public function send($notifiable, Notification $notification)
    {
        // Ensure the notification has the toFirebase method
        if (method_exists($notification, 'toFirebase')) {
            $notification->toFirebase($notifiable);
        }
    }
}

