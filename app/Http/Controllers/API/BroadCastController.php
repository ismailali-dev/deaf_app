<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController;
use App\Http\Resources\Common\ProfileResource;
use App\Http\Resources\FriendshipResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\Friendship;
use App\Models\Broadcast;
use App\Events\BroadcastStarted;
use App\Events\BroadcastEnded;
use Carbon\Carbon;
use App\Events\MessageSent;
use App\Models\Message;
use Pusher\Pusher;
use App\Http\Resources\BroadcastResource;
use App\Jobs\EndBroadcastJob;
use DB;


class BroadCastController extends BaseController
{

    public function getUsersList(Request $request)
    {
        try {
            $currentUserId = auth()->id();
    
            // Query users excluding the current user and those with role_id = 1
            $query = User::select('users.*', 
                    DB::raw("COALESCE(friendships.status, '') as friendship_status")) // Convert NULL to ''
                ->leftJoin('friendships', function ($join) use ($currentUserId) {
                    $join->on('users.id', '=', 'friendships.recipient_id')
                         ->where('friendships.sender_id', '=', $currentUserId);
                })
                ->where('users.id', '!=', $currentUserId)
                ->where('users.role_id', '!=', 1)
                ->where(function ($query) {
                    $query->whereNull('friendships.status')
                          ->orWhere('friendships.status', '!=', 'accepted');
                });
                
            $users = $this->getFilteredData($request, $query);
    
            // Transform user collection to resource
            $users = ProfileResource::collection($users);
            
    
            return successResponse('Record found successfully', $users);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    
        
    public function getFriendRequestsList(Request $request)
    {
        try {
            $user = $this->user;

           $friendRequests = $user->getFriendRequests();

            // Apply custom filtering
            $filteredRequests = $this->getFilteredData($request, $friendRequests);

            if ($filteredRequests->isEmpty()) {
                return successResponse('No Record Found', []);
            }

            // Return the filtered friend request records using the FriendshipResource
            return successResponse('Record found successfully', FriendshipResource::collection($filteredRequests));

        } catch (\Throwable $th) {
            // Handle and return error response
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    
   public function getMyFriends(Request $request)
    {
        try {
            $user = $this->user;
    
            // Retrieve friends as a collection
            $friends = $user->getFriends();
    
            // Apply custom filtering (assuming this method returns a Collection)
            $filteredRequests = $this->getFilteredData($request, $friends);
    
           
    
            // Return the filtered friend request records with modified avatars
            return successResponse('Records found successfully', $filteredRequests);
    
        } catch (\Throwable $th) {
            // Handle and return error response
            return errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * Modify the $avatar column.
     *
     * @param string $avatar
     * @return string
     */
    protected function modifyAvatar(string $avatar): string
    {
        // Example modification: prepend a URL path to the avatar
        return url('public/storage/' . $avatar);
    }

    
   public function friendRequest(Request $request)
    {
        try {
            // Validate request parameters
            $request->validate([
                'recipient_id' => 'required|exists:users,id',
                'action' => 'required|in:send,cancel',
            ]);
    
            // Get authenticated user
            $user = auth()->user();
    
            // Find recipient user
            $recipient = User::find($request->recipient_id);
            
            
    
            if (!$recipient) {
                return errorResponse('Recipient user not found', 404);
            }
    
            // Check age restriction
            if ($user->age < 18 && $recipient->age > $user->age) {
                return errorResponse('Please get parent approval first.', 403);
            }
    
            // Find existing request if it exists
            $existingRequest = Friendship::where('sender_id', $user->id)
                                         ->where('recipient_id', $recipient->id)
                                         ->first();
                                         
             
    
            if ($request->action === 'send') {
                
               
                if ($existingRequest) {
                   
                    if ($existingRequest->status === 'pending') {
                        return errorResponse('You have already sent a friend request.', 400);
                    } elseif ($existingRequest->status === 'cancelled' || $existingRequest->status === 'denied') {
                        // Update status from cancelled to pending
                        $existingRequest->update(['status' => 'pending']);
                        return successResponse('Friend request sent again.');
                    }
                } else {
                    
                   
                    // Create new friend request with "pending" status
                    Friendship::create([
                       'sender_type' => User::class,
                        'sender_id' => $user->id,
                        'recipient_type' => User::class,
                        'recipient_id' => $recipient->id,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    return successResponse('Friend request sent successfully.');
                }
            } elseif ($request->action === 'cancel') {
                if (!$existingRequest || $existingRequest->status !== 'pending') {
                    return errorResponse('No pending friend request found.', 400);
                }
                // Update status to "cancelled" instead of deleting
                $existingRequest->update(['status' => 'cancelled']);
                return successResponse('Friend request cancelled successfully.');
            }
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    

    
    
    public function unFriend(Request $request)
    {
        try {
            // Validate the friend_id from the request
            $request->validate([
                'friend_id' => 'required|exists:users,id', // Ensure friend_id is present and corresponds to an existing user
            ]);

            // Retrieve the authenticated user
            $user = $this->user; // Assuming $this->user contains the authenticated user
            
            // Find the friend user by ID
            $friend = User::find($request->friend_id);

            // Check if the friend is valid
            if (!$friend) {
                return errorResponse('Friend user not found', 404);
            }

            // Check if the user is friends with the provided user
            if (!$user->isFriendWith($friend)) {
                return errorResponse('You are not friends with this user', 404);
            }

            // Unfriend the user
            $user->unfriend($friend);

            // Return success response
            return successResponse('Unfriended successfully');

        } catch (\Throwable $th) {
            // Handle exceptions and return error response
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    
    public function handleFriendRequest(Request $request)
    {
        try {
            $user = Auth::user();
            $senderId = $request->input('sender_id');
            $action = $request->input('action'); // "accept" or "deny"
            
            // Validate action parameter
            if (!in_array($action, ['accept', 'deny'])) {
                return errorResponse('Invalid action specified', 400);
            }
            
            // Find the sender user model
            $sender = User::findOrFail($senderId);
            
            // Check if the user has a pending friend request from the sender
            if (!$user->hasFriendRequestFrom($sender)) {
                return errorResponse('You are Already Friend', 404);
            }

            // Handle the action
            if ($action === 'accept') {
                $user->acceptFriendRequest($sender);
                return successResponse('Friend request accepted successfully');
            } elseif ($action === 'deny') {
                $user->denyFriendRequest($sender);
                return successResponse('Friend request denied successfully');
            }

        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    // public function startBroadcast(Request $request)
    // {
    //     // Define validation rules
    //     $rules = [
    //         'latitude' => 'required|numeric',
    //         'longitude' => 'required|numeric',
    //         'broadcastForAll' => 'required|boolean',
    //     ];
    
    //     // Add conditional rules
    //     if ($request->broadcastForAll === false) {
    //         $rules['user_ids'] = 'required|array|min:1';
    //         $rules['user_ids.*'] = 'integer|exists:users,id'; // Ensure IDs exist in the users table
    //         $rules['duration'] = 'required|string|regex:/^\d{2}:\d{2}$/'; // Validate format "HH:MM"
    //         $rules['age_group_from'] = 'required|integer';
    //         $rules['age_group_to'] = 'required|integer';
    //     }
    
    //     // Validate the request
    //     $validatedData = $request->validate($rules);
    
    //     // Check if the user has an active broadcast
    //     $activeBroadcast = Broadcast::where('user_id', auth()->id())
    //         ->where('status', 'active')
    //         ->first();
    
    //     if ($activeBroadcast) {
    //         return errorResponse('You already have an active broadcast. Please end it before starting a new one.', 403);
    //     }
    
    //     // Create the broadcast
    //     $broadcast = Broadcast::create([
    //         'user_id' => auth()->id(),
    //         'latitude' => $request->latitude,
    //         'longitude' => $request->longitude,
    //         'duration' => $request->duration ?? null, // Null if broadcastForAll is true
    //         'age_group_from' => $request->age_group_from ?? null,
    //         'age_group_to' => $request->age_group_to ?? null,
    //         'status' => 'active',
    //     ]);
    
    //     // Calculate end time if duration is provided
    //     if (!empty($request->duration)) {
    //         $endTime = Carbon::now()->addMinutes($this->convertDurationToMinutes($request->duration));
    //         $broadcast->update(['end_time' => $endTime]);
    //     }
    
    //     // Radius for nearby broadcasts
    //     $radius = 10;
    //     $nearbyActiveBroadcasts = Broadcast::selectRaw(
    //         'id, user_id, latitude, longitude, duration, age_group_from, age_group_to, 
    //         (3959 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + 
    //         sin(radians(?)) * sin(radians(latitude)))) AS distance',
    //         [$request->latitude, $request->longitude, $request->latitude]
    //     )
    //     ->where('status', 'active')
    //     ->where('id', '!=', $broadcast->id)
    //     ->having('distance', '<', $radius)
    //     ->get();
    
    //     // Broadcast the event including nearby broadcasts
    //     broadcast(new BroadcastStarted($broadcast, $nearbyActiveBroadcasts));
    
    //     // Prepare response data
    //     $data = [
    //         'broadcast' => $broadcast,
    //         'nearby_broadcasts' => $nearbyActiveBroadcasts,
    //     ];
    
    //     return successResponse('Broadcast started successfully.', $data);
    // }

    // public function endBroadcast()
    // {
    //     // Fetch the active broadcast for the current user
    //     $broadcast = Broadcast::where('user_id', auth()->id())
    //         ->where('status', 'active')
    //         ->first();
    
    //     // Check if an active broadcast exists
    //     if (!$broadcast) {
    //         return errorResponse('No active broadcast found for the current user', 404);
    //     }
    
    //     // Update the broadcast status to inactive
    //     $broadcast->update(['status' => 'inactive']);
    
    //       $latitude = $broadcast->latitude;
    //     $longitude = $broadcast->longitude;
        
    //     // Get the radius from the request or use the default of 10 miles
    //     $radius = 10; // Set the radius to 10 miles (this can be dynamic)
        
    //     // Fetch nearby active broadcasts within the radius
    //     $nearbyBroadcasts = Broadcast::selectRaw(
    //         'id, user_id, latitude, longitude, duration, age_group_from, age_group_to, 
    //         (3959 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + 
    //         sin(radians(?)) * sin(radians(latitude)))) AS distance',
    //         [$latitude, $longitude, $latitude]
    //     )
    //     ->where('status', 'active') // Only active broadcasts
    //     ->get();
    
    //     // Broadcast the event for the end of the broadcast
    //     broadcast(new BroadcastEnded($broadcast, $nearbyBroadcasts));
    
    //     return successResponse('Broadcast ended successfully');
    // }

    // public function getNearbyBroadcasts(Request $request)
    // {
       
        
    //     $broadcast = Broadcast::where('user_id', auth()->id())
    //         ->where('status', 'active')
    //         ->first();
    
    //     // Check if the broadcast exists
    //     if (!$broadcast) {
    //         return errorResponse('Unauthorized or broadcast not found', 403);
    //     }
    
       
        
    //     $nearbyBroadcasts = Broadcast::selectRaw(
    //         'id, user_id, latitude, longitude, duration, age_group_from, age_group_to, 
    //         (3959 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + 
    //         sin(radians(?)) * sin(radians(latitude)))) AS distance',
    //         [$request->latitude, $request->longitude, $request->latitude]
    //     )
    //     ->where('status', 'active')
    //     ->where('id', '!=', $broadcast->id)
    //     ->get();
        
      
    
    //     // If no nearby broadcasts are found
    //     if ($nearbyBroadcasts->isEmpty()) {
    //         return errorResponse('No nearby broadcasts found', 404);
    //     }
    
    //     // Add user details to the response
    //     $nearbyBroadcasts = $nearbyBroadcasts->map(function ($broadcast) {
    //         $user = User::find($broadcast->user_id);
    //         $broadcast->user_name = $user ? $user->name : 'Unknown';
    //         $broadcast->email = $user ? $user->email : 'Unknown';
    //         return $broadcast;
    //     });
    
    //     $data = [];
    //     $data['broadcast'] = $broadcast;
    //     $data['nearby_broadcasts'] = $nearbyBroadcasts; // Use the correct variable
    
    //     return successResponse('Nearby broadcasts retrieved successfully.', $data);
    // }


   

     // Converts duration "HH:MM" to minutes
    private function convertDurationToMinutes($duration)
    {
        [$hours, $minutes] = explode(':', $duration);
        return ($hours * 60) + $minutes;
    }


   

    public function startBroadcast(Request $request)
        {
            $rules = [
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'broadcastForAll' => 'required|boolean',
            ];
        
            if (!$request->broadcastForAll) {
                $rules['user_ids'] = 'required|array|min:1';
                $rules['user_ids.*'] = 'integer|exists:users,id';
                $rules['duration'] = 'required|string|regex:/^\d{2}:\d{2}$/';
            }
        
            $validatedData = $request->validate($rules);
        
            // Check if the user already has an active broadcast
            $broadcast = Broadcast::where('user_id', auth()->id())
                ->where('status', 'active')
                ->first();
                
               
        
            if ($broadcast) {
                // Update existing broadcast
                $broadcast->update([
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'type' => $request->broadcastForAll ? 'all' : 'specific',
                    'allowed_user_ids' => $request->broadcastForAll ? null : json_encode($request->user_ids),
                    'duration' => $request->broadcastForAll ? null : $request->duration,
                    'end_time' => $request->duration ? Carbon::now()->addMinutes($this->convertDurationToMinutes($request->duration)) : null,
                ]);
            } else {
                // Create a new broadcast
                $broadcast = Broadcast::create([
                    'user_id' => auth()->id(),
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'type' => $request->broadcastForAll ? 'all' : 'specific',
                    'allowed_user_ids' => $request->broadcastForAll ? null : json_encode($request->user_ids),
                    'duration' => $request->broadcastForAll ? null : $request->duration,
                    'status' => 'active',
                    'end_time' => $request->duration ? Carbon::now()->addMinutes($this->convertDurationToMinutes($request->duration)) : null,
                ]);
            }
        
        
            // Fetch nearby broadcasts
            $nearbyBroadcasts = $this->getNearbyData();
            
        
            // Notify users or perform other actions
            // if (!$request->broadcastForAll) {
            //     $specificUsers = User::whereIn('id', $request->user_ids)->get();
            //     // Notification::send($specificUsers, new BroadcastNotification($broadcast));
            // }
        
            // Return the broadcast data
            
            if (!$request->broadcastForAll && $request->duration) {
                $durationInMinutes = $this->convertDurationToMinutes($request->duration);
                
                
                // Dispatch the job to end the broadcast after the duration
                EndBroadcastJob::dispatch($broadcast->id)->delay(now()->addMinutes($durationInMinutes));
                
                
              
            }

            $friends = auth()->user()->getFriends()->pluck('id')->toArray();
            broadcast(new BroadcastStarted($broadcast, $nearbyBroadcasts,$friends));
            
            return successResponse('Broadcast started successfully.', new BroadcastResource($broadcast));
        }

    // End Broadcast
    public function endBroadcast()
    {
        $broadcast = Broadcast::where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$broadcast) {
            return errorResponse('No active broadcast found.', 404);
        }

        $broadcast->update(['status' => 'inactive']);

       
        broadcast(new BroadcastEnded($broadcast));

        return successResponse('Broadcast ended successfully.');
    }

    // Get Nearby Broadcasts
  

    public function getNearbyBroadcasts(Request $request)
    {
        // Fetch the active broadcast for the authenticated user
        $broadcast = Broadcast::where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();
    
        if (!$broadcast) {
            return errorResponse('Unauthorized or no active broadcast found.', 403);
        }
    
        $nearbyBroadcasts = $this->getNearbyData();
    
        $friends = auth()->user()->getFriends()->pluck('id')->toArray();
        // Use the BroadcastResource to return the broadcast data
        $broadcastResource = new BroadcastResource($broadcast,$friends);
    
        // Make sure to use BroadcastResource for each nearby broadcast
        
       
        $nearbyBroadcastsResource = BroadcastResource::collection($nearbyBroadcasts->map(function ($broadcast) use ($friends) {
            // Add friends to each broadcast instance
            return new BroadcastResource($broadcast, $friends);
        }));
    
        // Return the response with the data
        return successResponse('Nearby broadcasts retrieved successfully.', [
            'broadcast' => $broadcastResource,
            'nearby_broadcasts' => $nearbyBroadcastsResource,
        ]);
    }
    
    
    
   
    function getNearbyData()
    {
        // Fetch current authenticated user's ID
            $userId = auth()->id();
        
            // Default radius for nearby search (e.g., 10 miles)
            $radius = 10;
        
            // Fetch current user's active broadcast
            $broadcast = Broadcast::where('user_id', $userId)
                ->where('status', 'active')
                ->first();
        
            if (!$broadcast) {
                return null; // No active broadcast for the current user
            }
        
            $latitude = $broadcast->latitude;
            $longitude = $broadcast->longitude;
            $allowedUserIds = $broadcast->type === 'specific' ? $broadcast->allowed_user_ids : null;
       
            // Base query to calculate distance and fetch nearby data
            $query = Broadcast::selectRaw(
                'id, user_id, latitude, longitude, duration, 
                (3959 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(latitude)))) AS distance',
                [$latitude, $longitude, $latitude]
            )
            ->where('status', 'active') // Only active broadcasts
            ->whereRaw('(3959 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(latitude)))) <= ?', 
                [$latitude, $longitude, $latitude, $radius]);
        
            // Exclude the current user's broadcast
            $query->where('user_id', '!=', $userId);
        
            // Apply user-specific filters if required
            if (!is_null($allowedUserIds) && is_array($allowedUserIds)) {
        $query->where(function ($q) use ($allowedUserIds) {
            foreach ($allowedUserIds as $id) {
                $q->orWhereRaw('JSON_CONTAINS(allowed_user_ids, ?)', [$id]);
            }
        });
    }
    
        return $query->get();
    }




    
    // public function sendMessage(Request $request)
    // {
    //     $validated = $request->validate([
    //         'receiver_id' => 'required|exists:users,id',
    //         'message' => 'required|string',
    //     ]);

    //     $message = Message::create([
    //         'sender_id' => Auth::id(),
    //         'receiver_id' => $validated['receiver_id'],
    //         'message' => $validated['message'],
    //     ]);
        
    //      // Pusher configuration
    //     $options = [
    //         'cluster' => 'ap2',
    //         'useTLS' => true,
    //     ];
    //     $pusher = new Pusher(
    //         env('PUSHER_APP_KEY'),
    //         env('PUSHER_APP_SECRET'),
    //         env('PUSHER_APP_ID'),
    //         $options
    //     );

    //     // Trigger the event
    //   // $pusher->trigger('chat.' . $request->receiver_id, 'message-sent', $message);
    //     $pusher->trigger('chat.' . Auth::id(), 'message-sent', $message);

    //     // Broadcast the message
    //     // broadcast(new MessageSent($message))->toOthers();
        
    //     return response()->json($message);
    // }
 
    public function sendMessage(Request $request)
    {
        
        try {
        $validated = $request->validate([
            'receiver_id' => 'required|exists:users,id',
           'message' => 'nullable|string|required_without:attachment', // Message is required if no attachment is provided
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:3072|required_without:message', // Attachment is required if no message is provided
        ]);
    
        $data = [
            'sender_id' => Auth::id(),
            'receiver_id' => $validated['receiver_id'],
        ];
    
        // If there's an image attachment
        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            try {
              
                $uploadedPath = $request->file('attachment')->store('chat/attachments', 'public');
                $data['attachment'] = $uploadedPath;
            } catch (\Exception $e) {
                return errorResponse('Image upload failed', 400);
                
            }
        }
    
        // If there's a message, add it to the data
        if ($request->filled('message')) {
            $data['message'] = $validated['message'];
        }
    
        // Create the message
        $message = Message::create($data);
        
        
        
        // Pusher configuration
        $options = [
            'cluster' => 'ap2',
            'useTLS' => true,
        ];
        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            $options
        );
    
        // $pusher->trigger('chat.' . $request->receiver_id, 'message-sent', $message);
        // Trigger the event
        $pusher->trigger('chat.' . Auth::id(), 'message-sent', $message);
    
        return successResponse('Message Sent Successfully', $message);
        
        } catch (\Exception $e) {
                return errorResponse('Unable To Send Message'.$e->getMessage(), 400);
                
            }
    }
  
    public function getRecentChats(Request $request)
{
    try {
        // Get the authenticated user ID
        $userId = Auth::id();

        // Fetch the most recent 5 chats, including both sender and receiver IDs
        $recentChats = Message::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->latest() // Order by the latest message
            ->take(5) // Limit to 5 most recent chats
            ->get();

        // Get a list of distinct user IDs (other than the authenticated user)
        $userIds = $recentChats->pluck('sender_id')
            ->merge($recentChats->pluck('receiver_id')) // Combine sender and receiver IDs
            ->unique()
            ->filter(function ($id) use ($userId) { // Anonymous function instead of fn()
                return $id != $userId; // Exclude the authenticated user
            });

        // Fetch only those users who are friends
        $friends = Friendship::where(function ($query) use ($userId, $userIds) {
                $query->where('status', 'accepted')
                      ->whereIn('sender_id', $userIds)
                      ->where('recipient_id', $userId);
            })
            ->orWhere(function ($query) use ($userId, $userIds) {
                $query->where('status', 'accepted')
                      ->where('sender_id', $userId)
                      ->whereIn('recipient_id', $userIds);
            })
            ->pluck('sender_id', 'recipient_id');

        // Get only those users who are friends
        $friendsIds = $friends->keys()->merge($friends->values())->unique();

        $users = User::whereIn('id', $friendsIds)->get();

        return successResponse('Recent Chats Users (Friends Only) Fetched Successfully', $users);
        
    } catch (\Exception $e) {
        return errorResponse('Unable to Fetch Recent Chats Users: ' . $e->getMessage(), 400);
    }
}



   public function fetchMessages($userId)
    {
        // Manually find the user by ID
        $user = User::find($userId);
    
        // Check if the user exists
        if (!$user) {
            return errorResponse('User not found.', 404);
        }
    
        // Fetch the messages
        $messages = Message::where(function ($query) use ($user) {
            $query->where('sender_id', Auth::id())
                ->where('receiver_id', $user->id);
        })->orWhere(function ($query) use ($user) {
            $query->where('sender_id', $user->id)
                ->where('receiver_id', Auth::id());
        })->orderBy('created_at', 'asc')->get();
    
        // Check if no messages are found
        if ($messages->isEmpty()) {
            return errorResponse('No messages found.', 200);
        }
    
        return successResponse('Record found successfully', $messages);
    }
        


}
