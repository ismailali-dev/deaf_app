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
use App\Events\RoomCreated;
use App\Events\RoomExited;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Events\PairingRequestExpired;
use App\Events\GroupMessageSent;
use App\Models\Sentence;
use App\Models\AudioFile;
use App\Models\Pairing;
use App\Models\Connection;
use Illuminate\Support\Facades\Auth;

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
    
    
    public function updateLocation(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);
    
            $user = auth()->user();
    
            $user->update([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);
    
            $roomId = $user->current_room_id;
    
          
            if (!$roomId && $user->role_id == 3) {
                $roomId = md5(uniqid(mt_rand(), true));
                $user->update(['current_room_id' => $roomId]);
    
                broadcast(new RoomCreated($user, $roomId));
            }
    
            $data['room_id'] = $roomId;
    
            return successResponse('Location updated successfully', $data);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    public function getActivatelistenerUersCount(Request $request)
    {
        try {
            $user = auth()->user();
    
            // Paired Count
            $pairedCount = Pairing::where(function ($q) use ($user) {
                    $q->where('from_id', $user->id)
                      ->orWhere('to_id', $user->id);
                })
                ->where('status', 'paired')
                ->count();
    
            // Connected Count (in current room)
            $connectedCount = 0;
            $connectedUserIds = [];
            if ($user->current_room_id) {
                $connections = Connection::where('room_id', $user->current_room_id)
                    ->where(function ($q) use ($user) {
                        $q->where('from_id', $user->id)
                          ->orWhere('to_id', $user->id);
                    })
                    ->where('status', 'connected')
                    ->get();
    
                $connectedUserIds = $connections->flatMap(function ($conn) use ($user) {
                        return [$conn->from_id, $conn->to_id];
                    })
                    ->unique()
                    ->filter(function ($id) use ($user) {
                        return $id != $user->id;
                    })
                    ->values()
                    ->all();
    
                $connectedCount = count($connectedUserIds);
            }
    
            // Available Count (nearby users)
            $availableCount = 0;
            $oppositeRole = ($user->role_id == 3) ? 2 : (($user->role_id == 2) ? 3 : null);
            if ($oppositeRole && $user->latitude && $user->longitude) {
                // Re-fetch connected users for exclusion (same as in getActivatelistenerUers)
                $connectedUserIds = Connection::where(function ($q) use ($user) {
                        $q->where('from_id', $user->id)
                          ->orWhere('to_id', $user->id);
                    })
                    ->where('status', 'connected')
                    ->get()
                    ->flatMap(function ($conn) use ($user) {
                        return [$conn->from_id, $conn->to_id];
                    })
                    ->unique()
                    ->filter(function ($id) use ($user) {
                        return $id != $user->id;
                    })
                    ->values()
                    ->all();
    
                $range = 10; // meters
    
                $availableCount = User::where('role_id', $oppositeRole)
                    ->where('id', '!=', $user->id)
                    ->whereNotIn('id', $connectedUserIds)
                    ->whereRaw(
                        "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?",
                        [$user->latitude, $user->longitude, $user->latitude, $range / 1000]
                    )
                    ->count();
            }
    
            return successResponse('Counts retrieved successfully', [
                'paired' => $pairedCount,
                'connected' => $connectedCount,
                'available' => $availableCount,
            ]);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }


    
    public function getActivatelistenerUers(Request $request)
    {
        try {
            $request->validate([
                'type' => 'required|in:paired,connected,available',
            ]);
    
            $user = auth()->user();
    
            // Paired Users
            if ($request->type === 'paired') {
                $pairings = Pairing::with(['fromUser', 'toUser'])
                    ->where(function ($q) use ($user) {
                        $q->where('from_id', $user->id)
                          ->orWhere('to_id', $user->id);
                    })
                    ->where('status', 'paired')  // only confirmed pairs
                    ->get();
    
                $pairedUsers = $pairings->map(function ($pair) use ($user) {
                    $other = $pair->from_id == $user->id ? $pair->toUser : $pair->fromUser;
                    return new ProfileResource($other);
                });
    
                return successResponse('Paired users retrieved', $pairedUsers);
            }
    
            // Connected Users (in the same room)
            if ($request->type === 'connected') {
                if (!$user->current_room_id) {
                    return successResponse('No connected users', []);
                }
    
                $connections = Connection::with(['fromUser', 'toUser'])
                    ->where('room_id', $user->current_room_id)
                    ->where(function ($q) use ($user) {
                        $q->where('from_id', $user->id)
                          ->orWhere('to_id', $user->id);
                    })
                    ->where('status', 'connected')
                    ->get();
    
                $connectedUsers = $connections->map(function ($connection) use ($user) {
                    $other = $connection->from_id == $user->id ? $connection->toUser : $connection->fromUser;
                    return new ProfileResource($other);
                });
    
                return successResponse('Connected users retrieved', $connectedUsers);
            }
    
            // Available Nearby Users (Bluetooth range) excluding connected users
            if ($request->type === 'available') {
                $oppositeRole = ($user->role_id == 3) ? 2 : (($user->role_id == 2) ? 3 : null);
                if (!$oppositeRole) {
                    return errorResponse('Invalid role_id for this operation', 403);
                }
    
                $range = 10; // meters
    
                // Get already connected user IDs to exclude
                $connectedUserIds = Connection::where(function ($q) use ($user) {
                    $q->where('from_id', $user->id)
                      ->orWhere('to_id', $user->id);
                })
                ->where('status', 'connected')
                ->get()
                ->flatMap(function ($conn) use ($user) {
                    return [$conn->from_id, $conn->to_id];
                })
                ->unique()
                ->filter(function ($id) use ($user) {
                    return $id != $user->id;
                })
                ->values()
                ->all();
    
                $availableUsers = User::where('role_id', $oppositeRole)
                    ->where('id', '!=', $user->id)
                    ->whereNotIn('id', $connectedUserIds)
                    ->whereRaw(
                        "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?",
                        [$user->latitude, $user->longitude, $user->latitude, $range / 1000]
                    )
                    ->get();
    
                return successResponse('Nearby users retrieved', ProfileResource::collection($availableUsers));
            }
    
            return errorResponse('Invalid type', 400);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }





    // public function updateLocationAndGetNearbyUsers(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'latitude' => 'required|numeric',
    //             'longitude' => 'required|numeric',
    //         ]);
    
    //         $user = auth()->user();
    
    //         // Update user location
    //         $user->update([
    //             'latitude' => $request->latitude,
    //             'longitude' => $request->longitude,
    //         ]);
    
    //         // Define opposite role_id (3 → 2 and 2 → 3)
    //         $oppositeRole = ($user->role_id == 3) ? 2 : (($user->role_id == 2) ? 3 : null);
    
    //         if (!$oppositeRole) {
    //             return errorResponse('Invalid role_id for this operation', 403);
    //         }
    
    //         // Get nearby users within 10 meters and only fetch opposite role_id users
    //         $range = 100000000000; // Bluetooth range in meters
    
    //         $nearbyUsers = User::whereNot('id', $user->id)
    //             ->where('role_id', $oppositeRole) // Fetch only users with opposite role_id
    //             ->whereRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?",
    //                 [$user->latitude, $user->longitude, $user->latitude, $range / 1000])
    //             ->get();
    
    //         // Transform nearby users using ProfileResource
    //         $response = ProfileResource::collection($nearbyUsers);
    
    //         return successResponse('Record found successfully', $response);
    //     } catch (\Throwable $th) {
    //         return errorResponse($th->getMessage(), 500);
    //     }
    // }
    
    
    public function sendPairingRequest(Request $request)
{
    $request->validate([
        'receiver_id' => 'required|exists:users,id',
    ]);

    $sender = auth()->user();
    $receiver = User::find($request->receiver_id);

    if (!$receiver) return errorResponse("User not found");
    if ($receiver->current_room_id) return errorResponse("This receiver is already paired.");

    \App\Models\Pairing::updateOrCreate(
        ['from_id' => $sender->id, 'to_id' => $receiver->id],
        ['status' => 'pending']
    );

    Cache::put('pairing_request_' . $sender->id, [
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id
    ], now()->addSeconds(20));

    // Broadcast pairing request with room id
    broadcast(new PairingRequestSent($sender, $receiver, $sender->current_room_id));

    NoRespond::dispatch($sender->id, $receiver->id)->delay(now()->addSeconds(15));

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

    // Make sure sender already has a room_id (should be set during updateLocation or pairing request)
    if (!$sender->current_room_id) {
        return errorResponse("Sender does not have a valid room ID");
    }

    $roomId = $sender->current_room_id;

    // Update pairing status
    \App\Models\Pairing::where('from_id', $sender->id)
        ->where('to_id', $receiver->id)
        ->update(['status' => 'paired']);

    // Create connection
    \App\Models\Connection::updateOrCreate(
        ['from_id' => $sender->id, 'to_id' => $receiver->id],
        ['room_id' => $roomId, 'status' => 'connected']
    );

    // Update both users with same room_id
    $receiver->update(['current_room_id' => $roomId]);

    // Optional: update sender again just in case (but not necessary if already set)
    // $sender->update(['current_room_id' => $roomId]);

    // Remove request from cache
    Cache::forget('pairing_request_' . $sender->id);
    
   $connectedCounts =  \App\Models\Connection::where('status','connected')->count();
    
   

    // Broadcast pairing accepted
    broadcast(new PairingRequestAccepted($sender, $receiver, $roomId,$connectedCounts));

    return successResponse('Pairing request accepted', ['room_id' => $roomId]);
}


public function exitRoomRequest(Request $request)
{
    $request->validate([
        'room_id' => 'required|string',
    ]);

    $currentUser = auth()->user();
    $roomId = $request->room_id;

    $users = User::where('current_room_id', $roomId)->get();

    // Admin leaves — disconnect all and end room
    if ($currentUser->role_id == 3) {
        // Disconnect all connections in the room
        \App\Models\Connection::where('room_id', $roomId)
            ->update(['status' => 'disconnected']);

        // Clear current_room_id for all users
        foreach ($users as $user) {
            $user->update(['current_room_id' => null]);
        }

        broadcast(new RoomExited($roomId, $currentUser, true)); // true = admin
        return successResponse("Admin left. Room ended for all.");
    }

    // Normal user leaves — disconnect their specific connection
    \App\Models\Connection::where(function ($query) use ($currentUser) {
        $query->where('from_id', $currentUser->id)
              ->orWhere('to_id', $currentUser->id);
    })->where('room_id', $roomId)
      ->update(['status' => 'disconnected']);

    // Clear current_room_id for the current user
    $currentUser->update(['current_room_id' => null]);

    broadcast(new RoomExited($roomId, $currentUser, false)); // false = not admin

    return successResponse('You have left the room.');
}
    
    
public function rejectPairingRequest(Request $request)
{
    $request->validate([
        'sender_id' => 'required|exists:users,id',
    ]);

    $receiver = auth()->user();
    $sender = User::find($request->sender_id);

    if (!$sender) return errorResponse("Sender not found");

    \App\Models\Pairing::where('from_id', $sender->id)
        ->where('to_id', $receiver->id)
        ->update(['status' => 'removed']);

    Cache::forget('pairing_request_' . $sender->id);

    broadcast(new PairingRequestRejected($sender, $receiver));
    return successResponse('Pairing request rejected');
}


   

public function sendGroupMessage(Request $request)
{
    try {
        $validated = $request->validate([
            'room_id' => 'required|string|exists:connections,room_id',
            'message' => 'nullable|string|required_without:attachment',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:3072|required_without:message',
        ]);

        $data = [
            'sender_id' => Auth::id(),
            'room_id' => $validated['room_id'],
        ];
        
        
       $sender = User::find(Auth::id(), ['id', 'name', 'avatar','created_at']);

        if (!$sender) {
            return errorResponse("Sender not found");
        }
    

        // Handle attachment
        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            try {
                $uploadedPath = $request->file('attachment')->store('chat/attachments', 'public');
                $data['attachment'] = $uploadedPath;
            } catch (\Exception $e) {
                return errorResponse('Image upload failed', 400);
            }
        }

        // Handle message
        if ($request->filled('message')) {
            $data['message'] = $validated['message'];
        }

       
         broadcast(new GroupMessageSent($sender, $data['message'], $validated['room_id']))->toOthers();

        return successResponse('Message Broadcasted Successfully', $data);

    } catch (\Exception $e) {
        return errorResponse('Unable to broadcast message: ' . $e->getMessage(), 400);
    }
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
    $validated = $request->validate([
        'room_id' => 'required|string',
        'message' => 'nullable|string',
        'audio' => 'nullable|file|mimes:wav,mp3',
    ]);

    try {
        $sender = auth()->user();

        $roomUsers = User::where('current_room_id', $validated['room_id'])
            ->where('id', '!=', $sender->id)
            ->get();

        if ($roomUsers->isEmpty()) {
            return errorResponse("No other users found in this room.");
        }

        // Audio File Handling
        if ($request->hasFile('audio')) {
            foreach ($roomUsers as $receiver) {
                $this->handleAudioMessage($request->file('audio'), $validated['room_id'], $sender, $receiver);
            }
            return successResponse("Audio message sent to all room users.");
        }

        // Text to Voice (Polly) Handling
        if (!empty($validated['message'])) {
            $grouped = $roomUsers->groupBy('gender');

            foreach ($grouped as $gender => $receivers) {
                $voiceId = ($gender === 'female') ? env('AWS_FEMALE_VOICE_ID', 'Joanna') : env('AWS_MALE_VOICE_ID', 'Matthew');

                $voicePath = $this->generatePollyVoiceOnce(
                    $validated['room_id'],
                    $validated['message'],
                    $voiceId
                );

                foreach ($receivers as $receiver) {
                    broadcast(new RoomVoiceMessage($validated['room_id'], $sender, $receiver, $voicePath));
                }
            }

            return successResponse("Text-to-speech message sent to all room users.");
        }

        return errorResponse('No valid input provided.');
    } catch (\Throwable $e) {
        return errorResponse('Failed to send voice message.', $e->getMessage());
    }
}



protected function handleAudioMessage($audioFile, $roomId, $sender, $receiver)
{
    $path = $audioFile->store('temp_audio', 'public');
    $fullPath = public_path('storage/' . $path);

    $features = $this->extractAudioFeatures($fullPath);


    if (!$features || $features['status'] !== 'success') {
        return errorResponse($features['message'] ?? 'Failed to extract features from audio', 404);
    }

    $match = $this->findClosestAudioMatch($features['features']);

    if (!$match) {
        return errorResponse('No matching sentence found', 404);
    }

    $sentence = Sentence::find($match->audioable_id);
    if (!$sentence) {
        return errorResponse('Matched sentence not found', 404);
    }

    return $this->generatePollyVoice($roomId, $sentence->sentence, $sender, $receiver);
}


protected function extractAudioFeatures($filePath)
{
    $command = "source /home/appokfqz/virtualenv/app.appogramengineering.com/python/3.6/bin/activate && "
             . "python /home/appokfqz/app.appogramengineering.com/python/test_audio_features.py " . escapeshellarg($filePath);

    $output = shell_exec($command);
    return json_decode($output, true);
}

protected function findClosestAudioMatch($extractedFeatures)
{
    $audioFiles = AudioFile::whereNotNull('features')->get(); // Avoid loading empty features
    $closestMatch = null;
    $closestDistance = PHP_FLOAT_MAX;

    foreach ($audioFiles as $file) {
        $distance = $this->calculateFeatureDistance($extractedFeatures, $file->features);
        if ($distance < $closestDistance) {
            $closestDistance = $distance;
            $closestMatch = $file;
        }
    }

    return $closestMatch;
}

/**
 * Convert Text to Speech using AWS Polly
 */
private function generatePollyVoiceOnce($roomId, $text, $voiceId)
{
    $messageHash = md5($text);
    $fileName = "room_voice_{$messageHash}_{$voiceId}.mp3";
    $relativePath = "room_voicemails/{$fileName}";
    $fullPath = storage_path("app/public/{$relativePath}");

    if (file_exists($fullPath)) {
        return $relativePath;
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

        return $relativePath;
    } catch (AwsException $e) {
        throw new \Exception('Failed to generate voice message: ' . $e->getMessage());
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
