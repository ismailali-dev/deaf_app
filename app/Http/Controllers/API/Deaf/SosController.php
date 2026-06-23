<?php

namespace App\Http\Controllers\API\Deaf;

use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use App\Models\SosRecording;
use App\Models\GlobalEmergencyRecording;
use App\Models\UserEmergencyRecording;
use App\Models\User;
use Aws\Polly\PollyClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Str;
use App\Http\Requests\UpdateUserProfileInfoRequest;
use App\Http\Resources\Common\ProfileResource;
use Twilio\Rest\Client;


use Twilio\TwiML\VoiceResponse;



class SosController extends BaseController
{
    /**
     * Create a new SOS recording.
     */
     public function create(Request $request)
    {
        try {
            // Validate the incoming data
            $validator = Validator::make($request->all(), [
                'emergency_type' => 'required|string',
                'sos_type' => 'required|in:E1,E2,E3,E4',
                'file_type' => 'required|in:audio,video',
                'file_path' => 'nullable|file|mimes:mp3,wav|max:10240',  // Validate the audio file if provided
                'audio_file_path' => 'nullable|file|mimes:mp3,wav|max:51200',  // Validate the audio file if provided
                'video_file_path' => 'nullable|file|mimes:mp4,mov,avi|max:102400',  // Validate the video file if provided
            ]);
    
            if ($validator->fails()) {
                return errorResponse($validator->errors()->first(), 422);
            }
    
            // Initialize file paths
            $audioFilePath = null;
            $videoFilePath = null;
            $generalFilePath = null;
    
            // Handle file upload for general file path
            if ($request->hasFile('file_path')) {
                // Upload the general file
                $generalFile = $request->file('file_path');
                $generalFilePath = $generalFile->store('sos-recordings/general_files', 'public'); // Store in 'general_files' directory
            }
    
            // Handle file upload for audio file
            if ($request->hasFile('audio_file_path')) {
                // Upload the audio file
                $audioFile = $request->file('audio_file_path');
                $audioFilePath = $audioFile->store('sos-recordings/audio', 'public'); // Store in 'audio' directory
            }
    
            // Handle file upload for video file
            if ($request->hasFile('video_file_path')) {
                // Upload the video file
                $videoFile = $request->file('video_file_path');
                $videoFilePath = $videoFile->store('sos-recordings/videos', 'public'); // Store in 'videos' directory
            }
    
            // Create the SOS recording record with the file paths
            $sosRecording = SosRecording::create([
                'user_id' => $this->userID,
                'emergency_type' => $request->emergency_type,
                'sos_type' => $request->sos_type,
                'file_type' => $request->file_type,
                'file_path' => $generalFilePath,  // Store the general file path if uploaded
                'audio_file_path' => $audioFilePath,  // Store the audio file path if uploaded
                'video_file_path' => $videoFilePath,  // Store the video file path if uploaded
            ]);
    
            return successResponse('SOS Recording created successfully', $sosRecording, 201);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }

    /**
     * Delete an SOS recording.
     */
    public function delete($id)
    {
        try {
            $sosRecording = SosRecording::find($id);
            
    
            if (!$sosRecording || $sosRecording->user_id != $this->userID) {
                return errorResponse('Recording not found or unauthorized', 404);
            }

            $sosRecording->delete();

            return successResponse('SOS Recording deleted successfully', null, 200);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }


    /**
     * Get all SOS recordings for the current user.
     */
    public function getUserRecordings(Request $request)
    {
        try {
            // Get the authenticated user's ID
           
            // Retrieve recordings for the current user
            $recordings = SosRecording::where('user_id', $this->userID)->get();
    
            // Check if recordings exist
            if ($recordings->isEmpty()) {
                return successResponse('No recordings found for the current user.', [], 200);
            }
    
            return successResponse('Recordings retrieved successfully.', $recordings, 200);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }


    

    /**
     * Get all SOS recordings for all users.
     */
    public function getAllRecordings()
    {
        try {
            $recordings = SosRecording::all();

            return successResponse('All recordings retrieved successfully', $recordings, 200);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    
     /**
     * Get all global emergency recordings (Predefined by Admin)
     */
    public function getGlobalRecordings()
    {
        $recordings = GlobalEmergencyRecording::all();

        if ($recordings->isEmpty()) {
            return errorResponse('No recordings found.', 404);
        }

        return successResponse('Global emergency recordings retrieved successfully.', $recordings);
    }

    /**
     * Save user-specific emergency recording
     */
    // public function saveUserVoicemail(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'type' => 'required|string',
    //         'sentence' => 'required|string',
    //         'voice_file' => 'required|file|mimes:mp3,wav',
    //     ]);
    
    //     // Handle file upload
    //     $path = $request->file('voice_file')->store('user_recordings');
        
    //     // Check if a recording already exists for this user and type
    //     $userRecording = UserEmergencyRecording::where('user_id', $this->userID)
    //                                           ->where('type', $validatedData['type'])
    //                                           ->first();
    
    //     // If recording exists, update it; otherwise, create a new one
    //     if ($userRecording) {
    //         // Update the existing recording
    //         $userRecording->update([
    //             'sentence' => $validatedData['sentence'],
    //             'voice_path' => $path,
    //         ]);
    
    //         return successResponse('User emergency recording updated successfully.', $userRecording);
    //     } else {
    //         // Create a new recording
    //         $newRecording = UserEmergencyRecording::create([
    //             'user_id' => $this->userID,
    //             'type' => $validatedData['type'],
    //             'sentence' => $validatedData['sentence'],
    //             'voice_path' => $path,
    //         ]);
    
    //         return successResponse('User emergency recording saved successfully.', $newRecording);
    //     }
    
    // }
   
   
    private function saveUserVoicemail($type, $sentence)
    {
            $user = auth()->user(); // Get the authenticated user
        
            if (!$user) {
                return false; // If user is not authenticated, return false
            }

            // Set voice ID dynamically
            $voiceId = ($user->gender === 'female') ? env('AWS_FEMALE_VOICE_ID', 'Joanna') : env('AWS_MALE_VOICE_ID', 'Matthew');
        
            // Generate unique filename based on sentence hash & gender
            $sentenceHash = md5($sentence);
            $fileName = "user_voicemail_{$user->id}_{$sentenceHash}_{$user->gender}.mp3";
            $relativePath = "voicemails/{$fileName}";
            $fullPath = storage_path("app/public/{$relativePath}");
        
            // Initialize Polly client
            $pollyClient = new PollyClient([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ]
            ]);
        
            try {
                // Generate speech using Polly
                $result = $pollyClient->synthesizeSpeech([
                    'Text' => $sentence,
                    'OutputFormat' => 'mp3',
                    'VoiceId' => $voiceId,
                ]);
        
                $audioStream = $result->get('AudioStream');
        
                // Ensure directory exists
                if (!file_exists(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0777, true);
                }
        
                // Save the audio content (Overwrite existing files)
                file_put_contents($fullPath, $audioStream);
        
                // Save/update database record
                UserEmergencyRecording::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'type' => $type,
                        'sentence' => $sentence,
                    ],
                    ['voice_path' => $relativePath]
                );
        
                return true; // Successfully saved, return true
            } catch (AwsException $e) {
                return false; // If Polly fails, return false
            }
        }



    
    // public function getUserVoicemails(Request $request)
    // {
    //     // Fetch all available global types
    //     $globalRecordings = GlobalEmergencyRecording::all();
    
    //     // Create an array of types from global recordings
    //     $types = $globalRecordings->pluck('type')->toArray();
    
    //     $recordings = [];
    //     $user = auth()->user(); // Get the authenticated user
    //     $isFemale = strtolower(optional($user)->gender) === 'female'; // Check if the user is female
    
    //     foreach ($types as $type) {
    //         // Check if the user has a recording of this type
    //         $userRecording = UserEmergencyRecording::where('user_id', $this->userID)
    //             ->where('type', $type)
    //             ->latest('created_at') // Ensure this orders by the latest record based on a timestamp
    //             ->first();
    
    //         if ($userRecording) {
    //             // If the user has the recording, add it to the response
    //             $recordings[] = [
    //                 'id' => $userRecording->id,  // Include the user-specific recording ID
    //                 'type' => $type,
    //                 'sentence' => $userRecording->sentence,
    //                 'voice_path' => $userRecording->voice_path,
    //             ];
    //         } else {
    //             // If the user does not have the recording, fetch the global recording
    //             $globalRecording = $globalRecordings->where('type', $type)->first();
    
    //             if ($globalRecording) {
    //                 // Determine the correct voice path based on user gender
    //                 $voicePath = $isFemale ? $globalRecording->voice_path_female : $globalRecording->voice_path_male;
    
    //                 // If a global recording exists, add it to the response
    //                 $recordings[] = [
    //                     'id' => 0,  // Set ID to 0 for global recordings
    //                     'type' => $type,
    //                     'sentence' => $globalRecording->sentence,
    //                     'voice_path' => storage_path("app/public/{$voicePath}"),
    //                 ];
    //             } else {
    //                 // If neither user-specific nor global recording exists, provide a default response
    //                 $recordings[] = [
    //                     'id' => 0,  // Set ID to 0 for missing recordings
    //                     'type' => $type,
    //                     'sentence' => 'No recording available.',
    //                     'voice_path' => null,
    //                 ];
    //             }
    //         }
    //     }
    
    //     return successResponse('Emergency recordings retrieved successfully.', $recordings);
    // }
    
    
  

    public function getUserVoicemails(Request $request)
    {
        
        
        // Fetch all available global types
        $globalRecordings = GlobalEmergencyRecording::all();
        $types = $globalRecordings->pluck('type')->toArray();
    
        $recordings = [];
        $user = auth()->user();
        
        if (
            !$user->race || 
            count(@$user->medical_conditions) < 1
        ) {
            return errorResponse("Please complete your emergency profile information", 400);
        }
        
        try {
            // Get the authenticated user's ID
           
            // Retrieve recordings for the current user
            $recordings = UserEmergencyRecording::where('user_id', $this->userID)->get();
    
            // Check if recordings exist
            if ($recordings->isEmpty()) {
                return successResponse('No emergency recordings found for the current user.', [], 200);
            }
    
            return successResponse('Emergency recordings retrieved successfully', $recordings, 200);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
        
        
        
    }
    
    
   public function updateProfileInfo(UpdateUserProfileInfoRequest $request)
    {
        try {
            $user = auth()->user();
            
            // Update user profile details
            $user->update([
                'medical_conditions' => $request->medical_conditions,
                'race' => $request->race,
                'armed' => $request->armed,
            ]);
    
            // Check if required fields are present
            if (!is_null($user->medical_conditions) && !is_null($user->race) && !is_null($user->armed)) {
    
                // Emergency types
                $emergencyTypes = ['Fire', 'Crime', 'Health', 'Report/Witness'];
    
                // Convert medical conditions array to a comma-separated string
                $medicalConditions = is_array($user->medical_conditions) 
                    ? implode(', ', $user->medical_conditions) 
                    : $user->medical_conditions;
                
                // Default message if no medical conditions
                $medicalConditionsText = $medicalConditions ?: "No known medical conditions";
    
                foreach ($emergencyTypes as $type) {
                    // Generate dynamic sentence for each emergency type
                    $sentence = "Hello, I have a {$type} emergency. My name is {$user->name}, my address is {$user->address}. 
                                I am {$user->age} years old with {$medicalConditionsText}. I am a {$user->race}. I am " . ($user->armed ? "armed" : "unarmed") . ".";
    
                    
                    $this->saveUserVoicemail($type,$sentence);
                }
            }
    
            $response = ProfileResource::make($user);
            return successResponse('Profile Updated Successfully', $response, 200);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
            
    
    // Function to generate voice file using AWS Polly
    private function generateVoice($text, $isFemale, $filePath)
    {
        $pollyClient = new PollyClient([
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);
    
        try {
            $voiceId = $isFemale ? 'Joanna' : 'Matthew';
            $result = $pollyClient->synthesizeSpeech([
                'Text' => $text,
                'OutputFormat' => 'mp3',
                'VoiceId' => $voiceId,
            ]);
    
            // Save file to public storage
            Storage::disk('public')->put($filePath, $result['AudioStream']->getContents());
        } catch (\Exception $e) {
            \Log::error('AWS Polly error: ' . $e->getMessage());
        }
    }
    
    
   public function call911()
{
    $twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));

    $twilioNumber = env('TWILIO_NUMBER');
    $dispatcherNumber = env('DISPATCHER_NUMBER');

    //Generate TwiML directly
    $twiml = new VoiceResponse();
    $twiml->play('https://app.appogramengineering.com/public/storage/global-emergency-recordings/health-emergency.wav', ['loop' => 5]);

    // Make the call
    $call = $twilio->calls->create(
        $dispatcherNumber,   // To
        $twilioNumber,       // From
        [
            'twiml' => $twiml->__toString()
        ]
    );

    return response()->json([
        'success' => true,
        'call_sid' => $call->sid
    ]);
}
        


    
    
}