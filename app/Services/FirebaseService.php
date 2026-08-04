<?php

// namespace App\Services;

// use Kreait\Firebase\Factory;
// use Kreait\Firebase\Messaging\CloudMessage;
// use Kreait\Firebase\Messaging\Notification as FirebaseNotification;


// class FirebaseService
// {
//     protected $messaging;

//     public function __construct()
//     {
//         $factory = (new Factory)->withServiceAccount(config('firebase.projects.app.credentials.file'));
//         $this->messaging = $factory->createMessaging();
//     }

//     public function sendNotification($token, $title, $body)
//     {
        
        
//         $message = CloudMessage::withTarget('token', $token)
//             ->withNotification(FirebaseNotification::create($title, $body));

//         $response = $this->messaging->send($message);
     
//         return $response;
//     }
    
//   public function sendNotificationToMultiple(array $tokens, $title, $body)
//     {
//         $responses = [];
//         $failures = [];
    
//         foreach ($tokens as $token) {
//             try {
//                 // Reusing the sendNotification method for each token
//                 $response = $this->sendNotification($token, $title, $body);
                
//                 // Storing the response if successful
//                 $responses[] = $response;
//             } catch (\Exception $e) {
//                 // Storing the failure
//                 $failures[] = [
//                     'token' => $token,
//                     'error' => $e->getMessage(),
//                 ];
//             }
//         }
    
//         // Return success and failure counts
//         return [
//             'success_count' => count($responses),
//             'failure_count' => count($failures),
//             'failures' => $failures,
//             'responses' => $responses,
//         ];
//     }

// }

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        $credentialsPath = config('firebase.projects.app.credentials.file');

        if ($credentialsPath && !$this->isAbsolutePath($credentialsPath)) {
            $credentialsPath = base_path($credentialsPath);
        }

        if (!$credentialsPath || !is_readable($credentialsPath)) {
            throw new \RuntimeException('Firebase service account file is not configured or readable.');
        }

        $factory = (new Factory)->withServiceAccount($credentialsPath);
        $this->messaging = $factory->createMessaging();
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    /**
     * Send notification to a single FCM token
     */
    public function sendNotification($token, $title, $body)
    {
        // Validation: Token must not be null
        if (!$token || trim($token) === '') {
            throw new \Exception("FCM token is missing or invalid (null or empty).");
        }

        // Build the message
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(FirebaseNotification::create($title, $body));

        // Send the message
        return $this->messaging->send($message);
    }

    /**
     * Send notification to multiple tokens
     */
    public function sendNotificationToMultiple(array $tokens, $title, $body)
    {
        $responses = [];
        $failures = [];

        foreach ($tokens as $token) {

            // Skip and log empty tokens
            if (!$token || trim($token) === '') {
                $failures[] = [
                    'token' => $token,
                    'error' => 'Token is empty or null'
                ];
                continue;
            }

            try {
                $response = $this->sendNotification($token, $title, $body);
                $responses[] = $response;

            } catch (\Exception $e) {

                $failures[] = [
                    'token' => $token,
                    'error' => $e->getMessage()
                ];

                Log::error('Firebase notification failed', [
                    'token_suffix' => substr($token, -8),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Firebase notification batch completed', [
            'success_count' => count($responses),
            'failure_count' => count($failures),
        ]);

        return [
            'success_count' => count($responses),
            'failure_count' => count($failures),
            'responses' => $responses,
            'failures' => $failures
        ];
    }
}
