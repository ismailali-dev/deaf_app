<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/




Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('private-chat.{receiverId}', function ($user, $receiverId) {
   return true;
});




Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    return $user->current_room_id == $roomId;
});


Broadcast::channel('pairing.{receiverId}', function ($user, $receiverId) {
    return $user && ((int) $user->id === (int) $receiverId);
    
    \Log::info('Broadcast user:', ['user' => $user]);
    return $user && ((int) $user->id === (int) $receiverId);
});

Broadcast::channel('pairing.{id}', function ($user, $id) {
     return true;
});


Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('broadcasts.{userId}', function ($user, $userId) {
    return (int)$user->id === (int)$userId;
});
