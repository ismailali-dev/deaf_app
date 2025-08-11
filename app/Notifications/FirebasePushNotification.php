<?php
namespace App\Notifications;

use App\Channels\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Services\FirebaseService;

class FirebasePushNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $body;
    protected $firebaseService;

    public function __construct($title, $body)
    {
        $this->title = $title;
        $this->body = $body;
        $this->firebaseService = app(FirebaseService::class); // Resolve the FirebaseService from the container
    }

    public function via($notifiable)
    {
        // Return an array of channels
        return [FirebaseChannel::class, 'database'];
    }

    public function toFirebase($notifiable)
    {
        // Retrieve all device tokens related to the user
        $deviceTokens = $notifiable->devices()->pluck('device_token')->toArray();
    
        // Check if there are any device tokens
        if (!empty($deviceTokens)) {
            // Send notifications using the FirebaseService
            $this->firebaseService->sendNotificationToMultiple($deviceTokens, $this->title, $this->body);
        }
    }

    public function toArray($notifiable)
    {
        \Log::info('Saving notification to database', ['title' => $this->title, 'body' => $this->body]);

        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
