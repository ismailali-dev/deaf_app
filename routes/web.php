<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\TwiML\VoiceResponse;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// use Illuminate\Support\Facades\Artisan;

// Route::get('/import-resources', function () {
//     $file = storage_path('app/public/data.csv');
//     // dd($file);

//     Artisan::call('import:resources', [
//         'file' => $file
//     ]);

//     return Artisan::output();
// });

//https://app.appogramengineering.com/import-resources

// Route::get('/test', function () {
//     dd('dfaf');
//     $file = storage_path('app/public/deaf_resources_complete (2).csv');
//     // dd($file);

//     Artisan::call('import:resources', [
//         'file' => $file
//     ]);

//     return Artisan::output();
// });


Route::group(['prefix' => 'dashboard'], function () {
    
    Voyager::routes();
    
});

Route::get('/', function () {
    return view('welcome');
});


Route::post('/twiml/dial-dispatcher', function () {
    $dispatcherNumber = env('DISPATCHER_NUMBER');
    $afterAnswerUrl =  'https://app.appogramengineering.com/twiml/after-answer';

    $response = "
        <Response>
            <Dial action='{$afterAnswerUrl}' answerOnBridge='true'>
                <Number>{$dispatcherNumber}</Number>
            </Dial>
        </Response>
    ";

    return response($response, 200)->header('Content-Type', 'text/xml');
});



Route::any('/twiml/dispatcher', function () {

    $response = new \Twilio\TwiML\VoiceResponse();
    $response->say(
        'This is an emergency dispatch call from Appogram Engineering. Please respond immediately.',
        ['voice' => 'alice']
    );

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $response;

    return response($xml, 200)
        ->header('Content-Type', 'text/xml');
})->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Route::get('/twiml/dispatcher', function () {
//     $response = new VoiceResponse();

//     $response->say(
//         'This is an emergency dispatch call from Appogram Engineering. Please respond immediately.',
//         [
//             'voice' => 'alice',
//             'language' => 'en-US'
//         ]
//     );

//     return response($response->__toString(), 200) // ✅ Convert to raw XML
//         ->header('Content-Type', 'text/xml');
// })->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);








Route::post('/twiml/after-answer', function () {
    $audioUrl = env('DEAF_AUDIO_URL');

    $response = "
        
    ";

    return response($response, 200)->header('Content-Type', 'text/xml');
});

// Optional: Logs all Twilio call events
Route::post('/twilio/call-status', function (Request $request) {
    Log::info('ðŸ“ž Twilio Call Event:', $request->all());
    return response('OK', 200);
});