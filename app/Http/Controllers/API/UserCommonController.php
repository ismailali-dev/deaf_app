<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Http\Resources\Common\ProfileResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Aws\Polly\PollyClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Str;
use App\Models\GlobalEmergencyRecording;
use App\Models\UserEmergencyRecording;
use App\Events\PairingRequestSent;
use App\Events\PairingRequestAccepted;
use App\Jobs\NoRespond;
use App\Events\PairingRequestRejected;
use App\Events\RoomVoiceMessage;
use App\Events\RoomExited;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Events\PairingRequestExpired;
use App\Models\Sentence;
use App\Models\AudioFile;

class UserCommonController extends BaseController
{

    /**
    Update Profile
    **/
    public function updateProfile(UpdateUserProfileRequest $request)
    {
        try {
            $validatedData = $request->validated();
            $user = User::find($this->userID);
    
            // Check if a new avatar is being uploaded
            if (isset($validatedData['avatar']) && $validatedData['avatar'] instanceof \Illuminate\Http\UploadedFile) {
                $uploadedPaths = $this->uploadFiles([$validatedData['avatar']], 'users/avatar');
                $validatedData['avatar'] = $uploadedPaths[0];
            }
    
            // Check if gender is changing
            $genderChanged = isset($validatedData['gender']) && $validatedData['gender'] !== $user->gender;
    
            // Update the user profile
            $user->update($validatedData);
    
            // If gender changed, update all existing voice recordings
            if ($genderChanged) {
                $this->updateUserVoiceRecordings($user);
            }
    
            // Prepare the response
            $response = ProfileResource::make($user);
            return successResponse('Profile Updated Successfully', $response, 200);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    
    
    
    private function updateUserVoiceRecordings($user)
    {
        // Determine the new voice ID based on updated gender
        $voiceId = ($user->gender === 'female') ? env('AWS_FEMALE_VOICE_ID', 'Joanna') : env('AWS_MALE_VOICE_ID', 'Matthew');
    
        // Fetch all existing recordings for the user
        $recordings = UserEmergencyRecording::where('user_id', $user->id)->get();
    
        if ($recordings->isEmpty()) {
            return;
        }
    
        // Define storage path
        $storagePath = storage_path('app/public/voicemails/');
    
        // Initialize Polly client
        $pollyClient = new PollyClient([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ]
        ]);
    
        foreach ($recordings as $recording) {
            // Generate unique filename based on sentence hash & gender
            $sentenceHash = md5($recording->sentence);
            $fileName = "user_voicemail_{$sentenceHash}_{$user->gender}.mp3";
            $relativePath = "voicemails/{$fileName}";
            $fullPath = $storagePath . $fileName;
    
            // Check if the converted file already exists
            if (file_exists($fullPath)) {
                // Just update the database with the existing file path
                $recording->update([
                    'voice_path' => $relativePath,
                ]);
                continue; // Skip Polly conversion
            }
    
            // If file does not exist, generate a new one
            try {
                // Generate new speech file
                $result = $pollyClient->synthesizeSpeech([
                    'Text' => $recording->sentence,
                    'OutputFormat' => 'mp3',
                    'VoiceId' => $voiceId,
                ]);
    
                $audioStream = $result->get('AudioStream');
    
                // Ensure directory exists
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0777, true);
                }
    
                // Save new voice file
                file_put_contents($fullPath, $audioStream);
    
                // Delete old voice file if it exists
                $oldPath = storage_path('app/public/' . $recording->voice_path);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
    
                // Update database record
                $recording->update([
                    'voice_path' => $relativePath,
                ]);
    
            } catch (AwsException $e) {
                \Log::error('Failed to update voice file: ' . $e->getMessage());
            }
        }
    }



    /**
    Get Profile
    */
    public function getProfile(Request $request)
    {
        try {  
            $user = User::findOrFail($this->userID);
            $response = ProfileResource::make($user);
            return successResponse('Record found Successfully',$response);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }

    /**
   
    Change Password
   

    **/

   public function changePassword(Request $request)
    {
        $request->validate([
                'old_password' => 'required',
                'new_password' => [
                    'required',
                    Password::min(6)->letters()->mixedCase()->numbers()->symbols(),
                    'different:old_password', // Ensures new password is different
                ],
                'password_confirmation' => 'required|same:new_password',
            ], [
                'new_password.different' => "New password must not be the same as old password.", // Custom message
            ]);
        
        
             try {  
                   
                    
                $user = auth()->user();
            
                if (!Hash::check($request->old_password, $user->password)) {
                    return errorResponse("Old Password doesn't match!");
                }
            
                $user->password = $request->new_password; // Hash new password
                $user->save();
                return successResponse('Password changed successfully.');
            
            }
            catch (\Throwable $th) {
                return errorResponse($th->getMessage(), 500);
        }
    }
    
    
    
    public function updateLocationAndGetNearbyUsers(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);
    
            $user = auth()->user();
    
            // Update user location
            $user->update([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);
    
            // Define opposite role_id (3 → 2 and 2 → 3)
            $oppositeRole = ($user->role_id == 3) ? 2 : (($user->role_id == 2) ? 3 : null);
    
            if (!$oppositeRole) {
                return errorResponse('Invalid role_id for this operation', 403);
            }
    
            // Get nearby users within 10 meters and only fetch opposite role_id users
            $range = 100000000000; // Bluetooth range in meters
    
            $nearbyUsers = User::whereNot('id', $user->id)
                ->where('role_id', $oppositeRole) // Fetch only users with opposite role_id
                ->whereRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?",
                    [$user->latitude, $user->longitude, $user->latitude, $range / 1000])
                ->get();
    
            // Transform nearby users using ProfileResource
            $response = ProfileResource::collection($nearbyUsers);
    
            return successResponse('Record found successfully', $response);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    
    
    public function sendPairingRequest(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
        ]);
    
        $sender = auth()->user();
        $receiver = User::find($request->receiver_id);
    
        if (!$receiver) {
            return errorResponse("User not found");
        }
        
        if ($receiver->current_room_id) {
            return errorResponse("This receiver is already paired with another user.");
        }
    
        // Save pairing request in cache with 10 seconds expiration
        Cache::put('pairing_request_' . $sender->id, [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id
        ], now()->addSeconds(20)); 
    
        // Send real-time event to receiver
        broadcast(new PairingRequestSent($sender, $receiver));
    
        // Schedule request expiry after 10 seconds
        NoRespond::dispatch($sender->id,$receiver->id)->delay(now()->addSeconds(15));
    
        return successResponse('Pairing request sent');
    }
            
    
    
    public function acceptPairingRequest(Request $request)
    {
        $request->validate([
            'sender_id' => 'required|exists:users,id',
        ]);
    
        $receiver = auth()->user();
        $sender = User::find($request->sender_id);
    
        if (!$sender) {
            return errorResponse("Sender not found");
        }
    
        // Create unique chat room ID
        // $roomId = md5(min($sender->id, $receiver->id) . max($sender->id, $receiver->id));
        $roomId = md5(uniqid(mt_rand(), true));
        
        
    
        // Broadcast event to notify both users
        broadcast(new PairingRequestAccepted($sender, $receiver, $roomId));
        
        $receiver->update(['current_room_id' => $roomId]);
        $sender->update(['current_room_id' => $roomId]);
        
        // Remove the NoRespond job from cache
        $jobKey = 'pairing_request_' . $sender->id;
        Cache::forget($jobKey);
    
    
        return successResponse('Pairing request accepted', ['room_id' => $roomId]);
    }
    
    public function exitRoomRequest(Request $request)
    {
        $request->validate([
            'room_id' => 'required|string',
        ]);
    
        $user = auth()->user();
    
        // Find room members (Assuming you store room members somewhere)
        $roomId = $request->room_id;

      
        // Broadcast event to notify both users
        broadcast(new RoomExited($roomId, $user));
        
        // Find both users in this chat room
        $users = User::where('current_room_id', $roomId)->get();
    
        // Ensure exactly 2 users exist in this room
        if ($users->count() === 2) {
            foreach ($users as $user) {
                $user->update(['current_room_id' => null]);
            }
            return successResponse("Chat ended for both users.");
        }
    
        return successResponse('Room exited successfully');
    }

    public function rejectPairingRequest(Request $request)
    {
        $request->validate([
            'sender_id' => 'required|exists:users,id',
        ]);
    
        $receiver = auth()->user();
        $sender = User::find($request->sender_id);
    
        if (!$sender) {
            return errorResponse("Sender not found");
        }
    
        // Broadcast event to notify sender about rejection
        broadcast(new PairingRequestRejected($sender, $receiver));
         $jobKey = 'pairing_request_' . $sender->id;
        Cache::forget($jobKey);
    
        return successResponse('Pairing request rejected');
    }



    // public function sendVoiceMessage(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'room_id' => 'required|string',
    //         'receiver_id' => 'required|exists:users,id',
    //         'message' => 'required|string',
    //     ]);
    
    //     $sender = auth()->user();
    //     $receiver = User::find($validatedData['receiver_id']);
    
    //     // Set voice ID dynamically based on receiver's gender
    //     $voiceId = ($sender->gender === 'female') ? env('AWS_FEMALE_VOICE_ID', 'Joanna') : env('AWS_MALE_VOICE_ID', 'Matthew');
    
    //     // Generate unique filename based on message hash
    //     $messageHash = md5($validatedData['message']);
    //     $fileName = "room_voice_{$messageHash}_{$receiver->gender}.mp3";
    //     $relativePath = "room_voicemails/{$fileName}";
    //     $fullPath = storage_path("app/public/{$relativePath}");
    
    //     // Check if voice file already exists
    //     if (file_exists($fullPath)) {
    //         // Send existing voice file to receiver
    //         broadcast(new RoomVoiceMessage($validatedData['room_id'], $sender, $receiver, $relativePath));
            
    //       return successResponse('Existing voice message sent.', [
    //             'voice_path' => url('public/storage/' . $relativePath)
    //         ]);
    //     }
    
    //     // Initialize Polly client
    //     $pollyClient = new PollyClient([
    //         'version' => 'latest',
    //         'region' => env('AWS_DEFAULT_REGION'),
    //         'credentials' => [
    //             'key' => env('AWS_ACCESS_KEY_ID'),
    //             'secret' => env('AWS_SECRET_ACCESS_KEY'),
    //         ]
    //     ]);
    
    //     try {
    //         // Generate speech using Polly
    //         $result = $pollyClient->synthesizeSpeech([
    //             'Text' => $validatedData['message'],
    //             'OutputFormat' => 'mp3',
    //             'VoiceId' => $voiceId,
    //         ]);
    
    //         $audioStream = $result->get('AudioStream');
    
    //         // Ensure directory exists
    //         if (!file_exists(dirname($fullPath))) {
    //             mkdir(dirname($fullPath), 0777, true);
    //         }
    
    //         // Save the audio file
    //         file_put_contents($fullPath, $audioStream);
    
    //         // Broadcast voice message to receiver
    //         broadcast(new RoomVoiceMessage($validatedData['room_id'], $sender, $receiver, $relativePath));
            
    //         return successResponse('Voice message sent successfully.', [
    //             'voice_path' => url('public/storage/' . $relativePath)
    //         ]);
    
    //     } catch (AwsException $e) {
    //         return errorResponse('Failed to generate voice message.', $e->getMessage());
    //     }
    // }
    
    
    public function sendVoiceMessage(Request $request)
    {
        $validatedData = $request->validate([
            'room_id' => 'required|string',
            'receiver_id' => 'required|exists:users,id',
            'message' => 'nullable|string',
            'audio' => 'nullable|file|mimes:wav,mp3',
        ]);
    
    try{
    
        $sender = auth()->user();
        $receiver = User::find($validatedData['receiver_id']);
    
        if (!$receiver) {
            return errorResponse("Receiver not found.");
        }
    
        // Process Voice Message from File
        if ($request->hasFile('audio')) {
            
           
            // Save audio file temporarily
            $audioFile = $request->file('audio');
            $path = $audioFile->store('temp_audio', 'public');
            $fullPath = public_path('storage/' . $path);
    
            // Extract Features using Python
            $command = "source /home/appokfqz/virtualenv/app.appogramengineering.com/python/3.6/bin/activate && "
                . "python /home/appokfqz/app.appogramengineering.com/python/test_audio_features.py " . escapeshellarg($fullPath);
    
            $output = shell_exec($command);
            $features = json_decode($output, true);
    
            if (!$features || $features['status'] !== 'success') {
                return errorResponse($features['message'] ?? 'Failed to extract features from audio', 404);
            }
    
            $extractedFeatures = $features['features'];
    
            // Match Features with Stored Audio Files
            $closestMatch = null;
            $closestDistance = PHP_FLOAT_MAX;
    
            $audioFiles = AudioFile::all();
            
            foreach ($audioFiles as $audioFile) {
                if ($audioFile->features) {
                    $distance = $this->calculateFeatureDistance($extractedFeatures, $audioFile->features);
                    if ($distance < $closestDistance) {
                        $closestDistance = $distance;
                        $closestMatch = $audioFile;
                    }
                }
            }
            
          
            if (!$closestMatch) {
                return errorResponse('No matching sentence found', 404);
            }
    
            // Retrieve Matched Sentence
            $sentence = Sentence::find($closestMatch->audioable_id);
            if (!$sentence) {
                return errorResponse('Matched sentence not found', 404);
            }
    
            // Convert Sentence to Voice
            return $this->generatePollyVoice($validatedData['room_id'],$sentence->sentence, $sender, $receiver);
        }
    

        // Process Text Message
        if (!empty($validatedData['message'])) {
            return $this->generatePollyVoice($validatedData['room_id'],$validatedData['message'], $sender, $receiver);
        }
        
    
    } catch (\Throwable $th) {
            return errorResponse('Failed to generate voice message.', $th->getMessage());
        }
        return errorResponse('No valid input provided.');
    }

/**
 * Convert Text to Speech using AWS Polly
 */
private function generatePollyVoice($roomId,$text, $sender, $receiver)
{
    
 
    $voiceId = ($sender->gender === 'female') ? env('AWS_FEMALE_VOICE_ID', 'Joanna') : env('AWS_MALE_VOICE_ID', 'Matthew');

    $messageHash = md5($text);
    $fileName = "room_voice_{$messageHash}_{$receiver->gender}.mp3";
    $relativePath = "room_voicemails/{$fileName}";
    $fullPath = storage_path("app/public/{$relativePath}");


    if (file_exists($fullPath)) {
        
        broadcast(new RoomVoiceMessage($roomId, $sender, $receiver, $relativePath));
      
        return successResponse('Existing voice message sent.', ['voice_path' => asset('public/storage/' . $relativePath)]);
    }

   

    try {
        
        
         $pollyClient = new PollyClient([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ]
            ]);
            
           
            
        $result = $pollyClient->synthesizeSpeech([
            'Text' => $text,
            'OutputFormat' => 'mp3',
            'VoiceId' => $voiceId,
        ]);
        
        

        $audioStream = $result->get('AudioStream');

        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        file_put_contents($fullPath, $audioStream);

        broadcast(new RoomVoiceMessage($roomId, $sender, $receiver, $relativePath));
        return successResponse('Voice message sent successfully.', ['voice_path' =>  asset('public/storage/' . $relativePath)]);

    } catch (AwsException $e) {
        return errorResponse('Failed to generate voice message.', $e->getMessage());
    }
}

/**
 * Calculate Feature Distance for Audio Matching
 */
private function calculateFeatureDistance(array $features1, array $features2)
{
    $distance = 0;
    foreach ($features1 as $key => $values1) {
        $values2 = $features2[$key] ?? [];
        foreach ($values1 as $index => $value1) {
            $value2 = $values2[$index] ?? 0;
            $distance += pow($value1 - $value2, 2);
        }
    }
    return sqrt($distance);
}




    
    


}
