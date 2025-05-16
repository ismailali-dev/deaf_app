<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/test', function () {
    return successResponse('User API is working');
});


Route::group(['prefix'=>'auth'], function () {
    
    
    Route::post('login', 'AuthController@login');
    Route::post('register', 'AuthController@register');       
    Route::post('social-auth', 'AuthController@socialAuth');  
    Route::post('setup-profile', 'AuthController@setProfile')->middleware('auth:api');  
    Route::post('logout', 'AuthController@logout')->middleware('auth:api');
    Route::post('refresh-token', 'AuthController@refreshJWTToken')->middleware('JwtTokenRefresh');
    
    Route::get('forgot-password', 'AuthController@forgotPassword');

    Route::post('resend-otp', 'AuthController@resendOtp');    // Resend OTP for both signup and forgot password
    Route::post('verify-otp', 'AuthController@verifyOtp');    // Verify OTP for both signup and forgot password   
    
    Route::post('reset-password', 'AuthController@resetPassword');
    
    
});


//Common auth and non auth
Route::group(['prefix'=>'common'], function () {
    
        //Common authented Apis
        Route::group(['middleware' => 'auth:api'], function () {
            
            //user profile apis
            Route::get('get-profile', 'UserCommonController@getProfile');
            Route::post('update-profile', 'UserCommonController@updateProfile');
            Route::post('change-password', 'UserCommonController@changePassword');
            
            //users apis 
            Route::group(['prefix'=>'users'], function () {
                Route::get('list', 'BroadCastController@getUsersList');
                Route::get('my-friend-requests', 'BroadCastController@getFriendRequestsList');
                Route::post('friend-request', 'BroadCastController@friendRequest');
                Route::post('accept-or-deny-friend-request', 'BroadCastController@handleFriendRequest');
                Route::get('get-my-friends', 'BroadCastController@getMyFriends');
                Route::post('unfriend', 'BroadCastController@unFriend');
                
                Route::post('send-message', 'BroadCastController@sendMessage');
                Route::get('messages/{user}', 'BroadCastController@fetchMessages');
                
            });
            
            //Recent Chats
            Route::get('recent-chats', 'BroadCastController@getRecentChats');
            
            Route::group(['prefix' => 'broadcast'], function () {
                Route::post('start', 'BroadCastController@startBroadcast');
                Route::post('end', 'BroadCastController@endBroadcast');
                Route::get('nearby', 'BroadCastController@getNearbyBroadcasts');
            });
            
    
            //Government resources apis
            Route::group(['prefix' => 'resources'], function () {
                Route::get('list', 'ResourceController@getResourcesList');
                Route::get('{id}', 'ResourceController@getResourceById');
            });
            
            //employments apis
            Route::group(['prefix' => 'employments'], function () {
                Route::get('list', 'EmploymentController@getEmploymentList');
                Route::get('{id}', 'EmploymentController@getEmploymenById');
            });
            
            
            
            Route::group(['prefix' => 'activate_listener'], function () {
                Route::post('update-location', 'UserCommonController@updateLocation');
                Route::get('/get-activate-listener-users', 'UserCommonController@getActivatelistenerUers');
                Route::get('/get-activate-listener-users-count', 'UserCommonController@getActivatelistenerUersCount');
                Route::post('/send-pairing-request', 'UserCommonController@sendPairingRequest');
                Route::post('/accept-pairing-request', 'UserCommonController@acceptPairingRequest');
                Route::post('/reject-pairing-request', 'UserCommonController@rejectPairingRequest');
                Route::post('/exit-room-request', 'UserCommonController@exitRoomRequest');
                Route::post('/send-group-messsage', 'UserCommonController@sendGroupMessage');
                Route::post('/send-voice-messsage', 'UserCommonController@sendVoiceMessage');
                
                // Route::get('/nearby-users', [UserController::class, 'getNearbyUsers']);
                // Route::post('/connect', [UserController::class, 'connectUser']);
                // Route::post('/disconnect', [UserController::class, 'disconnectUser']);
            });
            
    
    
        });
        
        
        
});



//Deaf Role Apis 
Route::group(['prefix'=>'deaf','middleware' => 'auth:api','namespace' => 'Deaf'], function () {
    
    
            Route::group(['prefix' => 'sentences'], function () {
                Route::get('list', 'SentenceController@getMySentenceList');
                Route::get('{id}', 'SentenceController@getSentenceById');
                Route::post('create', 'SentenceController@createSentence');
                Route::post('update/{id}', 'SentenceController@updateSentence'); // Update route
                Route::delete('delete/{id}', 'SentenceController@deleteSentence');
                
            });
            
            Route::group(['prefix' => 'words'], function () {
                Route::get('list', 'WordController@getMyWordList');
                Route::get('{id}', 'WordController@getWordById');
                Route::post('create', 'WordController@createWord');
                Route::post('identify-audio',  'WordController@identifyAudio');
                Route::post('update/{id}', 'WordController@updateWord'); // Update route
                Route::delete('delete/{id}', 'WordController@deleteWord');
                
            });
            
            Route::get('latest-show-pairs', 'SentenceController@getLatestShowPairs'); 
          
          
            Route::group(['prefix' => 'sos'], function () {
                Route::post('sos-recording', 'SosController@create'); 
                Route::delete('sos-recording/{id}', 'SosController@delete'); 
                Route::get('sos-recordings/list', 'SosController@getUserRecordings'); 
                
                 Route::group(['prefix' => 'sos-voicemail'], function () {
                    Route::get('global-recordings', 'SosController@getGlobalVoicemails');
                    // Route::post('save-user-voicemail', 'SosController@saveUserVoicemail');
                    Route::get('list', 'SosController@getUserVoicemails');
                    Route::post('convert-voicemail', 'SosController@convertVoicemailToText');
                 });
                 
                 Route::post('update-profile-info', 'SosController@updateProfileInfo');
                 

            });
           
            Route::group(['prefix' => 'emergency-contacts'], function () {
                Route::post('create', 'EmergencyContactController@create');
                Route::get('list', 'EmergencyContactController@getUserContacts');
                Route::put('update/{id}', 'EmergencyContactController@update');
                Route::delete('delete/{id}', 'EmergencyContactController@delete');
            });
            
    
});



//Listener Role Apis 
Route::group(['prefix'=>'listener','middleware' => 'auth:api','namespace' => 'Listener'], function () {
    
            // sign chart apis
            Route::group(['prefix' => 'sign-chart'], function () {
                
                Route::get('list', 'SignChartController@getSignChartList');
              
            });
            
});



Route::post('/broadcasting/auth', function (Request $request) {
    if (!auth()->check()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    return Broadcast::auth($request);
})->middleware('auth:api');



