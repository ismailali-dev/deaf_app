<?php

namespace App\Http\Controllers\API\Deaf;

use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Word;
use App\Models\AudioFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Resources\Deaf\WordResourceList;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class WordController extends BaseController
{



   public function getMyWordList(Request $request)
    {
        try {
            // Define the default audioable type (e.g., Sentence or Word)
            $defaultAudioableType = Word::class; // or another default type if needed
            
            // Initialize the query for Word
            $query = Word::query();
            
            // Get the current user ID
            $currentUserID = $this->userID;
    
            // Filter words that belong to the current user and based on the default audioable type
            $query->where('user_id', $currentUserID)
                  ->whereHas('audioFiles', function ($query) use ($defaultAudioableType) {
                      $query->where('audioable_type', $defaultAudioableType);
                  });
    
            // Apply additional filters using your custom method
            $wordList = $this->getFilteredData($request, $query);
    
            // Transform the collection of words using the resource
            $wordList = WordResourceList::collection($wordList);
    
            return successResponse('Word List Retrieved', $wordList, 200);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }

    public function createWord(Request $request)
    {
  
  
        try{
            
        
            $request->validate([
                'word' => 'required|string',
                'paired_number' => [
                    'required',
                    Rule::unique('words')->where(function ($query) use ($request) {
                        return $query->where('user_id', $this->userID);
                    }),
                ],
                'audio_1' => 'required|file',
                'audio_2' => 'required|file',
                'audio_3' => 'required|file',
            ]);
            
           
              $audioFiles = [
                $request->file('audio_1'),
                $request->file('audio_2'),
                $request->file('audio_3'),
            ];
    
            // Upload audio files using the method in the base controller
            $uploadedPaths = $this->uploadFiles($audioFiles, 'audio', 'public');
    
            //Create the sentence in the database
            $word = Word::create([
                'user_id' => $this->userID,
                'word' => $request->word,
                'paired_number' => $request->paired_number,
                'status'=>1
            ]);
           
    
            // Save audio file paths to the database
            // foreach ($uploadedPaths as $path) {
            //     AudioFile::create([
            //         'audioable_id' => $word->id, // Associate with the created sentence
            //         'audioable_type' => get_class($word),
            //         'file_path' => $path,
            //     ]);
            // }
            
            foreach ($uploadedPaths as $path) {
                
                $fullPath = public_path('storage/'.$path); // Full path to pass to Python
    
                // Python command to extract features
                $command = "source /home/appokfqz/virtualenv/app.appogramengineering.com/python/3.6/bin/activate && " .
                    "python /home/appokfqz/app.appogramengineering.com/python/test_audio_features.py " . escapeshellarg($fullPath);
    
                $output = shell_exec($command);
                 
                $features = json_decode($output, true);

                if (!$features || $features['status'] !== 'success') {
                    throw new \Exception($features['message'] ?? 'Unknown error occurred during feature extraction.');
                }
              
    
                $features = $features['features'];
                
                // Save audio file and extracted features in database
                AudioFile::create([
                    'audioable_id' => $word->id,
                    'audioable_type' => get_class($word),
                    'file_path' => $path,
                    'features' => $features,
                ]);
            }
        
            
            return successResponse('Word and audio files saved successfully.', 200);
        }
       catch (ValidationException $exception) {
            // Specifically catch validation exceptions
            return errorResponse($exception->validator->errors()->first(), 422);
        } catch (\Throwable $th) {
            // Catch all other exceptions
            return errorResponse($th->getMessage(), 500);
        }
        

       
    }
    
    
    
    public function identifyAudio(Request $request)
    {
        try {
            
            $request->validate([
                'audio' => 'required|file|mimes:wav,mp3',
            ]);
    
            // Upload audio file to temporary path
            $audioFile = $request->file('audio');
            $path = $audioFile->store('temp_audio', 'public');
    
            $fullPath = public_path('storage/' . $path);
            
    
            // Step 1: Extract features using Python
            $command = "source /home/appokfqz/virtualenv/app.appogramengineering.com/python/3.6/bin/activate && "
                . "python /home/appokfqz/app.appogramengineering.com/python/test_audio_features.py " . escapeshellarg($fullPath);
    
            $output = shell_exec($command);
            $features = json_decode($output, true);
 
            if (!$features || $features['status'] !== 'success') {
                
                 return errorResponse($features['message'] ?? 'Failed to extract features from audio', 404);
                 
              
            }
    
            $extractedFeatures = $features['features'];
    
            // Step 2: Compare with existing features in database
            $audioFiles = AudioFile::all();
    
            $closestMatch = null;
            $closestDistance = PHP_FLOAT_MAX;
    
            foreach ($audioFiles as $audioFile) {
                if($audioFile->features)
                {
                    
                    // $storedFeatures = json_decode($audioFile->features, true);
    
                    $distance = $this->calculateFeatureDistance($extractedFeatures, $audioFile->features);
        
                    if ($distance < $closestDistance) {
                        $closestDistance = $distance;
                        $closestMatch = $audioFile;
                    }
                }
                
            }
    
            if (!$closestMatch) {
                
                 return errorResponse('No matching audio found', 404);
               
            }
    
            // Step 3: Fetch Word using audioable_id
            $word = Word::find($closestMatch->audioable_id);
    
            if (!$word) {
                
                return errorResponse('Matched word not found', 404);
                
                
            }

            return successResponse('Word and audio files saved successfully.', $word);
            
          
        } catch (ValidationException $e) {
            return errorResponse($e->getMessage(), 422);
            
        } catch (\Throwable $e) {
             return errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Calculate Euclidean distance between two feature arrays.
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



    public function updateWord(Request $request, $id)
    {
        try {
            // Find the existing word and ensure it belongs to the current user
            $word = Word::where('id', $id)
                        ->where('user_id', $this->userID)
                        ->first();
    
            if (!$word) {
                return errorResponse('Word not found or does not belong to the user', 404);
            }
    
            // Validate the request data, only required fields are validated
            $request->validate([
                'word' => 'required|string', // Make word nullable as well
                'paired_number' => [
                    'required',
                    'nullable', // Make it nullable since it's optional for updates
                    Rule::unique('words')->ignore($word->id)->where(function ($query) use ($request) {
                        return $query->where('user_id', $this->userID);
                    }),
                ],
                'audio_1' => 'nullable|file',
                'audio_2' => 'nullable|file',
                'audio_3' => 'nullable|file',
            ]);
    
            // Only update the fields that are provided in the request
            $word->update(array_filter([
                'word' => $request->has('word') ? $request->word : $word->word,
                'paired_number' => $request->has('paired_number') ? $request->paired_number : $word->paired_number,
            ]));
    
            // Handle audio files if provided
            $audioFiles = [
                'audio_1' => $request->file('audio_1'),
                'audio_2' => $request->file('audio_2'),
                'audio_3' => $request->file('audio_3'),
            ];
    
            $uploadedPaths = [];
            foreach ($audioFiles as $key => $audioFile) {
                if ($audioFile) {
                    // Upload the new audio file
                    $uploadedPaths[$key] = $this->uploadFiles([$audioFile], 'audio', 'public')[0];
    
                    // Update or create new audio file records in the database
                    AudioFile::updateOrCreate(
                        [
                            'audioable_id' => $word->id,
                            'audioable_type' => get_class($word),
                        ],
                        [
                            'file_path' => $uploadedPaths[$key],
                        ]
                    );
                }
            }
    
            return successResponse('Word updated successfully.', 200);
    
        } catch (ValidationException $exception) {
            return errorResponse($exception->validator->errors()->first(), 422);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }

    
    public function getWordById($id)
    {
        try {
            // Define the audioable type for Word
            $audioableType = Word::class;
            
            // Get the current user ID
            $currentUserID = $this->userID;
    
            // Fetch the word record by ID, ensuring it belongs to the current user
            $word = Word::where('id', $id)
                        ->where('user_id', $currentUserID) // Ensure the word belongs to the current user
                        ->whereHas('audioFiles', function ($query) use ($audioableType) {
                            $query->where('audioable_type', $audioableType);
                        })
                        ->first();
    
            // Check if the word was found and belongs to the user
            if (!$word) {
                return errorResponse('Word not found or does not belong to the user', 404);
            }
    
            // Transform the word record using the resource
            $wordResource = WordResourceList::make($word);
    
            return successResponse('Word Retrieved', $wordResource, 200);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    public function deleteWord($id)
    {
        try {
            // Find the word that belongs to the authenticated user
            $word = Word::where('id', $id)
                        ->where('user_id', $this->userID)
                        ->first();
    
            if (!$word) {
                return errorResponse('Word not found or does not belong to the user', 404);
            }
    
            // Delete associated audio files (assuming there's an audioFiles relationship)
            foreach ($word->audioFiles as $audioFile) {
                if (Storage::exists($audioFile->file_path)) {
                    Storage::delete($audioFile->file_path);
                }
            }
    
            // Attempt to delete the word
            $word->delete();
    
            return successResponse('Word and associated audio files deleted successfully.', 200);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }

}
