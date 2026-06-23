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
use App\Events\PairingRequestAcceptedCount;
use App\Jobs\NoRespond;
use App\Events\PairingRequestRejected;
use App\Events\RoomVoiceMessage;
use App\Events\RoomCreated;
use App\Events\RoomExited;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Events\PairingRequestExpired;
use App\Events\GroupMessageSent;
use App\Events\Disconnected;
use App\Models\Sentence;
use App\Models\Word;
use App\Models\ListenerSetting;
use App\Models\AudioFile;
use App\Models\Pairing;
use App\Models\Connection;
use Illuminate\Support\Facades\Auth;
use App\Notifications\FirebasePushNotification;


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
    
    
   public function deleteMyAccount(Request $request)
    {
        try {

            $user = auth()->user();
            if (!$user) {
                return errorResponse('User not authenticated.', 401);
            }
            $user->forceDelete();
            return successResponse('Account deleted successfully');

        } catch (\Throwable $th) {

            \Log::error('Delete Error: ' . $th->getMessage());

            return errorResponse('Something went wrong.', 500);
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
    
            
            $setting = ListenerSetting::where('user_id', $user->id)->first();

            // Prepare listener settings as an array of boolean values or defaults if null
            $listenerSettings = [
                'autosend'     => $setting->autosend ?? false,
                'notification' => $setting->notification ?? false,
                'mute'         => $setting->mute ?? true,
            ];
    
            $data = [
                'room_id'           => $roomId,
                'listener_settings' => $listenerSettings,
            ];
    
 
            return successResponse('Location updated successfully', $data);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    
    
public function getActivatelistenerUersCount()
{
    try {
        $user = auth()->user();

        // All connected user IDs
       $connectedUserIds = Connection::where(function ($q) use ($user) {
            $q->where('from_id', $user->id)->orWhere('to_id', $user->id);
        })
        ->where('status', 'connected')
        ->get()
        ->flatMap(function ($conn) use ($user) {
            return $conn->from_id == $user->id ? [$conn->to_id] : [$conn->from_id];
        })
        ->unique()
        ->values()
        ->all();

        // PAIRED COUNT excluding connected users
        $pairings = Pairing::where(function ($q) use ($user) {
                $q->where('from_id', $user->id)->orWhere('to_id', $user->id);
            })
            ->get();

           $pairedCount = Pairing::where(function ($q) use ($user) {
            $q->where('from_id', $user->id)->orWhere('to_id', $user->id);
        })
        ->get()
        ->filter(function ($pair) use ($user, $connectedUserIds) {
            $otherUserId = $pair->from_id == $user->id ? $pair->to_id : $pair->from_id;
            return !in_array($otherUserId, $connectedUserIds); // exclude connected
        })
    ->count();

        // CONNECTED COUNT (same room & connected)
        $connectedCount = 0;
        if ($user->current_room_id) {
            $connectedCount = Connection::where('room_id', $user->current_room_id)
                ->where(function ($q) use ($user) {
                    $q->where('from_id', $user->id)->orWhere('to_id', $user->id);
                })
                ->where('status', 'connected')
                ->count();
        }

        // AVAILABLE COUNT (based on location, not paired, not connected)
        $oppositeRole = $user->role_id == 3 ? 2 : ($user->role_id == 2 ? 3 : null);
        $availableCount = 0;

        if ($oppositeRole) {
            $pairedUserIds = $pairings->map(function ($pair) use ($user) {
                return $pair->from_id == $user->id ? $pair->to_id : $pair->from_id;
            })->unique()->all();

            $availableCount = User::where('role_id', $oppositeRole)
                ->where('id', '!=', $user->id)
                ->whereNotIn('id', array_merge($connectedUserIds, $pairedUserIds))
                ->whereRaw(
                    "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?",
                    [$user->latitude, $user->longitude, $user->latitude, 10 / 1000]
                )
                ->count();
        }

        return successResponse('Counts fetched', [
            'paired' => $pairedCount,
            'connected' => $connectedCount,
            'available' => $availableCount
        ]);
    } catch (\Throwable $th) {
        return errorResponse($th->getMessage(), 500);
    }
}






    
  public function getActivatelistenerUers(Request $request)
{
    try {
        $request->validate(['type' => 'required|in:paired,connected,available']);

        $user = auth()->user();

        // All connected user IDs
       $connectedUserIds = Connection::where(function ($q) use ($user) {
            $q->where('from_id', $user->id)->orWhere('to_id', $user->id);
        })
        ->where('status', 'connected')
        ->get()
        ->flatMap(function ($conn) use ($user) {
            return $conn->from_id == $user->id ? [$conn->to_id] : [$conn->from_id];
        })
        ->unique()
        ->values()
        ->all();

        if ($request->type === 'paired') {
            $pairings = Pairing::with(['fromUser', 'toUser'])
                ->where(function ($q) use ($user) {
                    $q->where('from_id', $user->id)->orWhere('to_id', $user->id);
                })
                ->get();

            // Filter out any user who is already connected
                $pairedUsers = $pairings->filter(function ($pair) use ($user, $connectedUserIds) {
                $otherUserId = $pair->from_id == $user->id ? $pair->to_id : $pair->from_id;
                return !in_array($otherUserId, $connectedUserIds); // Exclude connected users
            })->map(function ($pair) use ($user) {
                $other = $pair->from_id == $user->id ? $pair->toUser : $pair->fromUser;
                return new ProfileResource($other);
            });
                return successResponse('Paired users retrieved', $pairedUsers);
        }

        if ($request->type === 'connected') {
            if (!$user->current_room_id) {
                return successResponse('No connected users', []);
            }

            $connections = Connection::with(['fromUser', 'toUser'])
                ->where('room_id', $user->current_room_id)
                ->where(function ($q) use ($user) {
                    $q->where('from_id', $user->id)->orWhere('to_id', $user->id);
                })
                ->where('status', 'connected')
                ->get();

            $connectedUsers = $connections->map(function ($conn) use ($user) {
                $other = $conn->from_id == $user->id ? $conn->toUser : $conn->fromUser;
                return new ProfileResource($other);
            });

            return successResponse('Connected users retrieved', $connectedUsers);
        }

        if ($request->type === 'available') {
            $oppositeRole = $user->role_id == 3 ? 2 : ($user->role_id == 2 ? 3 : null);
            if (!$oppositeRole) {
                return errorResponse('Invalid role_id for this operation', 403);
            }

            $pairings = Pairing::where(function ($q) use ($user) {
                    $q->where('from_id', $user->id)->orWhere('to_id', $user->id);
                })
                ->get();

            $pairedUserIds = $pairings->map(function ($pair) use ($user) {
                return $pair->from_id == $user->id ? $pair->to_id : $pair->from_id;
            })->unique()->all();

            $availableUsers = User::where('role_id', $oppositeRole)
                ->where('id', '!=', $user->id)
                ->whereNotIn('id', array_merge($connectedUserIds, $pairedUserIds))
                ->whereRaw(
                    "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?",
                    [$user->latitude, $user->longitude, $user->latitude, 10 / 1000]
                )
                ->get();

            return successResponse('Available users retrieved', ProfileResource::collection($availableUsers));
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
    
    
    public function saveListenerSetting(Request $request)
    {
        
        
        $validated = $request->validate([
            'autosend' => 'nullable|boolean',
            'notification' => 'nullable|boolean',
            'mute' => 'nullable|boolean',
        ]);
    
        $user = auth()->user();
    
        // Allowed keys to update
        $fields = ['autosend', 'notification', 'mute'];
    
        // Filter only valid keys from request
        $data = collect($request->only($fields))->filter(function ($value) {
            return !is_null($value);
        })->toArray();
    
        // Return error if nothing to update
        if (empty($data)) {
            return errorResponse("No valid settings provided.");
        }
    
        // Get or create setting record
        $setting = ListenerSetting::firstOrCreate(
            ['user_id' => $user->id],
            [] // default values if needed
        );
    
        // Update only provided fields
        $setting->update($data);
    
        return successResponse("Settings updated successfully.");
    }
    
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
           
       $receiver->notify(new FirebasePushNotification(
            'New Pairing Request',
            $sender->name . ' has sent you a pairing request.'
        ));
    
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
        
    //   $deviceTokens = $sender->devices()->pluck('device_token')->toArray();
    //     if (!empty($deviceTokens)) {
    //         $firebase->sendNotificationToMultiple(
    //             $deviceTokens,
    //             'Pairing Request Accepted',
    //             $receiver->name . ' has accepted your pairing request. You are now connected.'
    //         );
    //     }
    
        // Broadcast pairing accepted
        broadcast(new PairingRequestAccepted($sender, $receiver, $roomId,$connectedCounts));
        broadcast(new PairingRequestAcceptedCount($roomId,$connectedCounts));
        
        

    
        return successResponse('Pairing request accepted', ['room_id' => $roomId]);
    }
    
    public function disConnectUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
    
        try {
            $authUser = auth()->user();
            $otherUser = User::find($request->user_id);
    
            // Check if the user exists (redundant but safe)
            if (!$otherUser) {
                return errorResponse("User not found");
            }
    
            // Make sure both users are in the same room
            if (!$authUser->current_room_id || $authUser->current_room_id !== $otherUser->current_room_id) {
                return errorResponse("Users are not in the same room or missing room ID");
            }
    
            $roomId = $authUser->current_room_id;
    
            // Disconnect the connection (in either direction)
            Connection::where(function ($q) use ($authUser, $otherUser) {
                    $q->where('from_id', $authUser->id)->where('to_id', $otherUser->id);
                })
                ->orWhere(function ($q) use ($authUser, $otherUser) {
                    $q->where('from_id', $otherUser->id)->where('to_id', $authUser->id);
                })
                ->update([
                    'status' => 'disconnected',
                    'room_id' => $roomId
                ]);
    
            // Get the updated connected count for the room
            $connectedCounts = Connection::where('room_id', $roomId)
                ->where('status', 'connected')
                ->count();
            
            $otherUser->update(['current_room_id' => null]);
    
            // Broadcast the disconnection event
            broadcast(new Disconnected($authUser, $otherUser, $roomId, $connectedCounts));
    
            return successResponse('User disconnected successfully', [
                'room_id' => $roomId,
                'connected_count' => $connectedCounts
            ]);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
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


   

  // if ($request->hasFile('audio')) {
        //     $file = $request->file('audio');
        
        //     dd([
        //         'original_name' => $file->getClientOriginalName(), // e.g., audio.mp3
        //         'extension'     => $file->getClientOriginalExtension(), // e.g., mp3
        //         'mime_type'     => $file->getMimeType(), // e.g., audio/mpeg
        //         'size_in_kb'    => round($file->getSize() / 1024, 2), // e.g., 420.55 KB
        //     ]);
        // }
      
      
    //old code   
public function sendGroupMessage(Request $request)
{
    try {
        
        $validated = $request->validate([
            'room_id' => 'required|string',
            'method'  => 'required|in:text,audio',
            'message' => 'nullable|string|required_if:method,text',
            'audio' => 'nullable|file|mimetypes:audio/mpeg,audio/x-m4a,video/mp4,audio/mp4,audio/wav,audio/x-wav,audio/ogg|required_if:method,audio',
            'isFromAutoSend'  => 'nullable|string', // Expects 'true' as string,
            'voiceFile' => 'nullable|required_if:method,isFromAutoSend',
            
        ]);
    
        $room_id = $validated['room_id'];
        $method  = $validated['method'];
        $message = $validated['message'] ?? null;
        $isFromAutoSend  = ($validated['isFromAutoSend'] ?? '') === 'true';
        $voiceFile = @$validated['voiceFile'];
        
        $roomUsers = null;
        $sender = auth()->user();
        
        
        if (!$sender) {
            return errorResponse("Sender not found");
        }
        if ($sender->current_room_id != $room_id) {
                return errorResponse("You are not in this room.");
        }
        

        $roomUsers = User::where('current_room_id', $room_id)
                ->get();
       
        if (!$roomUsers) {
            return errorResponse("No users found in this room.");
        }
        
        
        
        if($roomUsers->count() == 1){   //single user case
                
                
                $myselfSettings = ListenerSetting::firstOrCreate(
                    ['user_id' => $sender->id], // Search criteria
                    [ 
                        
                        'autosend'     =>  false,
                        'notification' =>  false,
                        'mute'         =>  false,
                        
                    ]
                );
                
                if ($method === 'audio') {  
                    
                        if($myselfSettings && !$myselfSettings->mute){
                  
                            $audioMatchData = $this->handleAudioMessage($request->file('audio'), $room_id, $sender, $sender);
                            if($audioMatchData){
                                
                                    if($myselfSettings->autosend){
                                       
                                        broadcast(new \App\Events\RoomVoiceMessage($validated['room_id'], $sender, $sender, $audioMatchData['voice_file'],$method))->toOthers();
                                        broadcast(new GroupMessageSent($sender, $audioMatchData['final_match'], $room_id,$method))->toOthers();
                                        return successResponse("Audio and Text message sent successfully.");
                                    }
                                    else{
                                        
                                        $apidata = [
                                            'final_match'=>$audioMatchData['final_match'],
                                            'voice_file' => $audioMatchData['voice_file'],
                                            ];
                                         return successResponse("Audio and Text message recieved.",$apidata);
                                    }
                                    
                                   
                            }
                            else{
                                return errorResponse("No Match Found! Try Again");
                            }
                            
                        }
                        else{
                                $audioMatchData = $this->handleAudioMessage($request->file('audio'), $room_id, $sender, $sender);
                                if($audioMatchData){
                                    
                                     if($myselfSettings->autosend){
                                         broadcast(new GroupMessageSent($sender, $audioMatchData['final_match'], $room_id,$method))->toOthers();
                                         return successResponse("Text message sent successfully.");
                                     }
                                    else{
                                            $apidata = [
                                            'final_match'=>$audioMatchData['final_match'],
                                             'voice_file' => $audioMatchData['voice_file'],
                                            ];
                                            
                                         return successResponse("Audio and Text message recieved.",$apidata);
                                    }
                                    
                                }
                                else{
                                    return errorResponse("No Match Found! Try Again");
                                }
                        }
             
                 
                }
                else{
                    
                    if($myselfSettings && !$myselfSettings->mute){
                        
                        $voicePath = '';
                        
                        if($isFromAutoSend && $isFromAutoSend == 'true'){
                            
                            
                            $voiceId = $sender->gender === 'female'
                            ? env('AWS_FEMALE_VOICE_ID', 'Joanna')
                            : env('AWS_MALE_VOICE_ID', 'Matthew');
                            
                            $voicePath = $voiceFile;
                            $method = 'audio';
                        }
                        else{
                            
                            $voicePath = $this->generatePollyVoiceOnce($room_id, $message, $sender,$sender);
                            
                        }
                        
                        if($voicePath){
                            broadcast(new \App\Events\RoomVoiceMessage($validated['room_id'], $sender, $sender, $voicePath,$method))->toOthers();
                        }
                        else{
                            return errorResponse("No Voice Path Found");
                        }
                        
                        broadcast(new GroupMessageSent($sender, $message, $room_id,$method))->toOthers();
                        return successResponse("Audio and Text message sent successfully.");
                    }
                    else{
                        broadcast(new GroupMessageSent($sender, $message, $room_id,$method))->toOthers();
                        return successResponse("Text message sent successfully.");
                    }
                     
                }
            
            
        }
        else if($roomUsers->count() == 2){ //double user case
        
            
            // $senderListenerSettings = ListenerSetting::where('user_id', $sender->id)->first();
            
             $senderListenerSettings = ListenerSetting::firstOrCreate(
                    ['user_id' => $sender->id], // Search criteria
                    [ 
                        
                        'autosend'     =>  false,
                        'notification' =>  false,
                        'mute'         =>  false,
                        
                    ]
                );
                
            
            // Filter to get the receiver
            $receiver = $roomUsers->firstWhere('id', '!=', $sender->id);
          
           
            if($sender->role_id == 3){ // if sender is deaf then broadcast voice message or message to listener
            
              
             
                if ($method === 'audio') {
                    
                
                        $receiverListenerSettings = ListenerSetting::firstOrCreate(
                                ['user_id' => $receiver->id], // Search criteria
                                [ 
                                    
                                    'autosend'     =>  false,
                                    'notification' =>  false,
                                    'mute'         =>  false,
                                    
                                ]
                            );
                        
                         $audioMatchData = $this->handleAudioMessage($request->file('audio'), $room_id, $sender, $receiver);
                        
                        if($receiverListenerSettings && !$receiverListenerSettings->mute){
                  
                           
                            if($audioMatchData){
                                
                                    if($senderListenerSettings->autosend){
                                        
                                        
                                        broadcast(new \App\Events\RoomVoiceMessage($validated['room_id'], $sender, $receiver, $audioMatchData['voice_file'],$method))->toOthers();
                                        return successResponse("Audio message sent successfully.");
                                    }
                                    else{
                                        
                                        
                                            $apidata = [
                                            'final_match'=>$audioMatchData['final_match'],
                                             'voice_file' => $audioMatchData['voice_file'],
                                            ];
                                            
                                         return successResponse("Audio and Text message recieved.",$apidata);
                                    }
                                    
                                    
                            }
                            else{
                                return errorResponse("No Match Found! Try Again");
                            }
                            
                        }
                        else{
                                
                                if($audioMatchData){
                                    
                                    if($senderListenerSettings->autosend){
                                    
                                        broadcast(new GroupMessageSent($sender, $audioMatchData['final_match'], $room_id,$method))->toOthers();
                                        return successResponse("Text message sent successfully.");
                                    }
                                    else{
                                        
                                        
                                            $apidata = [
                                            'final_match'=>$audioMatchData['final_match'],
                                             'voice_file' =>$audioMatchData['voice_file'],
                                            ];
                                            
                                         return successResponse("Audio and Text message recieved.",$apidata);
                                    }
                                    
                                   
                                }
                                else{
                                    return errorResponse("No Match Found! Try Again");
                                }
                        }
                }
                else{
                    
                    
                    $receiverListenerSettings = ListenerSetting::firstOrCreate(
                                ['user_id' => $receiver->id], // Search criteria
                                [ 
                                    
                                    'autosend'     =>  false,
                                    'notification' =>  false,
                                    'mute'         =>  false,
                                    
                                ]
                            );
                            
                    
                    
                    if($receiverListenerSettings && !$receiverListenerSettings->mute){
                       
                       
                        $voicePath = '';
                       
                        if($isFromAutoSend && $isFromAutoSend == 'true'){
                                $voiceId = $sender->gender === 'female'
                                ? env('AWS_FEMALE_VOICE_ID', 'Joanna')
                                : env('AWS_MALE_VOICE_ID', 'Matthew');
                                $voicePath = $voiceFile;
                                
                                $method = 'audio';
                           }
                         else{
                            $voicePath = $this->generatePollyVoiceOnce($room_id, $message, $sender,$receiver);
                        }
                        
                        if($voicePath){
                                    broadcast(new \App\Events\RoomVoiceMessage($validated['room_id'], $sender, $receiver, $voicePath,$method))->toOthers();
                                    return successResponse("Audio message sent successfully.");
                        }
                        else{
                            return errorResponse("No Voice Path Found");
                        }
                            
                        
                    }
                    else{
                        broadcast(new GroupMessageSent($sender, $message, $room_id,$method,$receiver))->toOthers();
                        return successResponse("Text message sent successfully.");
                    }
                    
                }
            }
            else{
                
                 broadcast(new GroupMessageSent($sender, $message, $room_id,$method))->toOthers();
                 return successResponse("Text message sent successfully.");
                
            }
        
            
        }
        else{ //multiple users case
            
            //normal chat 
            broadcast(new GroupMessageSent($sender, $message, $room_id,$method))->toOthers();
            return successResponse("Text message sent successfully.");
            
        }
        
       return successResponse("Api bypassed successfully.");
      
        
    } catch (\Exception $e) {
        return errorResponse("Unable to broadcast message: " . $e->getMessage(), 400);
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
    try {
        
        $path = $audioFile->store('temp_audio', 'public');
        $fullPath = base_path('public/storage/' . $path);
        $features = $this->extractAudioFeatures($fullPath);
        if (!$features || !isset($features['status']) || $features['status'] !== 'success') {
            return false;
        }

        // Find closest matching sentence
        $match = $this->findClosestAudioMatch($features['features']);
        
        if (!$match) {
            return false;
        }

      
        $finalmatch = "";
        if($match->audioable_type == 'App\Models\Word')
        {
                $word = Word::find($match->audioable_id);
                if (!$word) {
                    return false;
                }
                $relativePath = $this->generatePollyVoiceOnce($roomId, $word->word, $sender, $receiver);
                
                $finalmatch = $word->word;
                
        }
        else{
            
            $sentence = Sentence::find($match->audioable_id);
            if (!$sentence) {
                return false;
            }
            // Generate and return Polly voice response
            $relativePath = $this->generatePollyVoiceOnce($roomId, $sentence->sentence, $sender, $receiver);
            $finalmatch = $sentence->sentence;
        }
     
       $data = [
            'final_match' => $finalmatch,
            'voice_file' => $relativePath
        ];
        
        return $data;
        
        
    } catch (\Throwable $e) {
        return false;
    }
}

protected function extractTextFromAudio($audioFile)
{
    try {
        $path = $audioFile->store('temp_audio', 'public');
        $fullPath = base_path('public/storage/' . $path);

        $features = $this->extractAudioFeatures($fullPath);

        if (!$features || !isset($features['status']) || $features['status'] !== 'success') {
            return null;
        }

        $match = $this->findClosestAudioMatch($features['features']);

        if (!$match) {
            return null;
        }

        if ($match->audioable_type === 'App\Models\Word') {
            $word = Word::find($match->audioable_id);
            return $word?->word ?? null;
        }

        $sentence = Sentence::find($match->audioable_id);
        return $sentence?->sentence ?? null;

    } catch (\Throwable $e) {
        return null;
    }
}

protected function extractAudioFeatures($filePath)
{
    
    $baseCommand = config('python.feature_script');
    $command = $baseCommand . ' ' . escapeshellarg($filePath);
    $output = shell_exec($command);
    return json_decode($output, true);
}


protected function euclideanDistance(array $vec1, array $vec2): float
{
    $sum = 0.0;
    foreach ($vec1 as $i => $v) {
        $diff = ($v ?? 0) - ($vec2[$i] ?? 0);
        $sum += $diff * $diff;
    }
    return sqrt($sum);
}

protected function findClosestAudioMatch($extractedFeatures)
{
    $audioFiles = AudioFile::whereNotNull('features')->orderBy('created_at', 'desc')->get(); // Order by latest first
    $closestMatch = null;
    $closestDistance = PHP_FLOAT_MAX;

    foreach ($audioFiles as $file) {
        $distance = $this->calculateFeatureDistance($extractedFeatures, $file->features);

        // Give preference to the last created (latest) file if distance is the same
        if ($distance < $closestDistance || ($distance == $closestDistance && $file->created_at > $closestMatch->created_at)) {
            $closestDistance = $distance;
            $closestMatch = $file;
        }
    }

    return $closestMatch;
}



/**
 * Convert Text to Speech using AWS Polly
 */
// private function generatePollyVoiceOnce($roomId, $text, $voiceId)
// {
//     $messageHash = md5($text);
//     $fileName = "room_voice_{$messageHash}_{$voiceId}.mp3";
//     $relativePath = "room_voicemails/{$fileName}";
//     $fullPath = storage_path("app/public/{$relativePath}");

//     if (file_exists($fullPath)) {
//         return $relativePath;
//     }

//     try {
//         $pollyClient = new PollyClient([
//             'version' => 'latest',
//             'region' => env('AWS_DEFAULT_REGION'),
//             'credentials' => [
//                 'key' => env('AWS_ACCESS_KEY_ID'),
//                 'secret' => env('AWS_SECRET_ACCESS_KEY'),
//             ]
//         ]);

//         $result = $pollyClient->synthesizeSpeech([
//             'Text' => $text,
//             'OutputFormat' => 'mp3',
//             'VoiceId' => $voiceId,
//         ]);

//         $audioStream = $result->get('AudioStream');

//         if (!file_exists(dirname($fullPath))) {
//             mkdir(dirname($fullPath), 0777, true);
//         }

//         file_put_contents($fullPath, $audioStream);

//         return $relativePath;
//     } catch (AwsException $e) {
//         throw new \Exception('Failed to generate voice message: ' . $e->getMessage());
//     }
// }


private function generatePollyVoiceOnce($roomId, $text, $sender, $receiver=null)
{
    $text = strip_tags((string) $text);
    
   
    $messageHash = md5($text);
    $fileName = "room_voice_{$messageHash}_{$sender->id}.mp3";
    $relativePath = "room_voicemails/{$fileName}";
    $fullPath = storage_path("app/public/{$relativePath}");

    if (file_exists($fullPath)) {
        return $relativePath;
    }

    // Ensure directory exists
    
    if (!file_exists(dirname($fullPath))) {
        mkdir(dirname($fullPath), 0777, true);
    }
 
    // Escape input and call python TTS script
    $escapedText = escapeshellarg($text);
    $escapedPath = escapeshellarg($fullPath);

    $baseCommand = config('python.tts_script');
    $command = "$baseCommand $escapedText $escapedPath";

    $output = shell_exec($command);
    
  
    $response = json_decode($output, true);
    
    if ($response && $response['status'] === 'success' && file_exists($fullPath)) {
        return $relativePath;
    }

    throw new \Exception('Failed to generate voice: ' . ($response['message'] ?? 'Unknown error'));
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
