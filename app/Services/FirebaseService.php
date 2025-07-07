<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;


class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(config('firebase.projects.app.credentials.file'));
        $this->messaging = $factory->createMessaging();
    }

    public function sendNotification($token, $title, $body)
    {
        
        
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(FirebaseNotification::create($title, $body));

        $response = $this->messaging->send($message);
     
        return $response;
    }
    
   public function sendNotificationToMultiple(array $tokens, $title, $body)
    {
        $responses = [];
        $failures = [];
    
        foreach ($tokens as $token) {
            try {
                // Reusing the sendNotification method for each token
                $response = $this->sendNotification($token, $title, $body);
                
                // Storing the response if successful
                $responses[] = $response;
            } catch (\Exception $e) {
                // Storing the failure
                $failures[] = [
                    'token' => $token,
                    'error' => $e->getMessage(),
                ];
            }
        }
    
        // Return success and failure counts
        return [
            'success_count' => count($responses),
            'failure_count' => count($failures),
            'failures' => $failures,
            'responses' => $responses,
        ];
    }

}
