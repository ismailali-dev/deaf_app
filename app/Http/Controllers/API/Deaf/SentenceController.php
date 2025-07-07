<?php

namespace App\Http\Controllers\API\Deaf;

use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Sentence;
use App\Models\AudioFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Resources\Deaf\SentenceResourceList;
use App\Http\Resources\Deaf\LatestShowPairsResource;
use Illuminate\Validation\ValidationException;
use App\Models\Word;
use Illuminate\Support\Facades\Storage;


class SentenceController extends BaseController
{



    public function getMySentenceList(Request $request)
    {
        try {
            // Define the default audioable type (e.g., Sentence or Word)
            $defaultAudioableType = Sentence::class; // or another default type if needed
            
            // Initialize the query for Sentence
            $query = Sentence::query();
            
            // Get the current user ID
            $currentUserID = $this->userID;
    
            // Filter sentences that belong to the current user and based on the default audioable type
            $query->where('user_id', $currentUserID)
                  ->whereHas('audioFiles', function ($query) use ($defaultAudioableType) {
                      $query->where('audioable_type', $defaultAudioableType);
                  });
    
            // Apply additional filters using your custom method
            $sentencesList = $this->getFilteredData($request, $query);
    
            // Transform the collection of sentences using the resource
            $sentencesList = SentenceResourceList::collection($sentencesList);
    
            return successResponse('Sentence List Retrieved', $sentencesList, 200);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    public function createSentence(Request $request)
    {
  
  
        try{
            
        
            $request->validate([
                'sentence' => 'required|string',
                 'paired_number' => [
                    'required',
                    Rule::unique('sentences')->where(function ($query) use ($request) {
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
            $this->updateUserStorageUsage($uploadedPaths);
    
            // Create the sentence in the database
            $sentence = Sentence::create([
                'user_id' => $this->userID,
                'sentence' => $request->sentence,
                'paired_number' => $request->paired_number,
                'status'=>1
            ]);
            
           
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
                    'audioable_id' => $sentence->id,
                    'audioable_type' => get_class($sentence),
                    'file_path' => $path,
                    'features' => $features,
                ]);
            }
            
    
            // Save audio file paths to the database
            // foreach ($uploadedPaths as $path) {
            //     AudioFile::create([
            //         'audioable_id' => $sentence->id, // Associate with the created sentence
            //         'audioable_type' => get_class($sentence),
            //         'file_path' => $path,
            //     ]);
            // }
            
            return successResponse('Sentence and audio files saved successfully.', 200);
        }
        catch (ValidationException $exception) {
            // Specifically catch validation exceptions
            return errorResponse($exception->validator->errors()->first(), 422);
        } catch (\Throwable $th) {
            // Catch all other exceptions
            return errorResponse($th->getMessage(), 500);
        }
        

       
    }
    
    public function updateSentence(Request $request, $id)
    {
    try {
        // Find the existing sentence and ensure it belongs to the current user
        $sentence = Sentence::where('id', $id)
                            ->where('user_id', $this->userID)
                            ->first();

        if (!$sentence) {
            return errorResponse('Sentence not found or does not belong to the user', 404);
        }

        // Validate the request data, only required fields are validated
        $request->validate([
            'sentence' => 'required|string', // Make sentence nullable as well
            'paired_number' => [
                'required',
                'nullable', // Make it nullable since it's optional for updates
                Rule::unique('sentences')->ignore($sentence->id)->where(function ($query) use ($request) {
                    return $query->where('user_id', $this->userID);
                }),
            ],
            'audio_1' => 'nullable|file',
            'audio_2' => 'nullable|file',
            'audio_3' => 'nullable|file',
        ]);

        // Only update the fields that are provided in the request
        $sentence->update(array_filter([
            'sentence' => $request->has('sentence') ? $request->sentence : $sentence->sentence,
            'paired_number' => $request->has('paired_number') ? $request->paired_number : $sentence->paired_number,
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
                        'audioable_id' => $sentence->id,
                        'audioable_type' => get_class($sentence),
                    ],
                    [
                        'file_path' => $uploadedPaths[$key],
                    ]
                );
            }
        }
        
        $this->updateUserStorageUsage(array_values($uploadedPaths));

        return successResponse('Sentence updated successfully.', 200);

    } catch (ValidationException $exception) {
        return errorResponse($exception->validator->errors()->first(), 422);
    } catch (\Throwable $th) {
        return errorResponse($th->getMessage(), 500);
    }
}

    
    public function getSentenceById($id)
    {
        try {
            // Define the audioable type for Sentence
            $audioableType = Sentence::class;
            
            // Get the current user ID
            $currentUserID = $this->userID;
    
            // Fetch the sentence record by ID, ensuring it belongs to the current user
            $sentence = Sentence::where('id', $id)
                            ->where('user_id', $currentUserID) // Ensure the sentence belongs to the current user
                            ->whereHas('audioFiles', function ($query) use ($audioableType) {
                                $query->where('audioable_type', $audioableType);
                            })
                            ->first();
    
            // Check if the sentence was found and belongs to the user
            if (!$sentence) {
                return errorResponse('Sentence not found or does not belong to the user', 404);
            }
    
            // Transform the sentence record using the resource
            $sentenceResource = SentenceResourceList::make($sentence);
    
            return successResponse('Sentence Retrieved', $sentenceResource, 200);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
    
    public function deleteSentence($id)
    {
        try {
            $sentence = Sentence::where('id', $id)
                                ->where('user_id', $this->userID)
                                ->first();
    
            if (!$sentence) {
                return errorResponse('Sentence not found or does not belong to the user', 404);
            }
    
            $filePaths = [];
    
            // Delete associated audio files and collect file paths
            foreach ($sentence->audioFiles as $audioFile) {
                $filePaths[] = $audioFile->file_path;
    
                if (Storage::exists($audioFile->file_path)) {
                    Storage::delete($audioFile->file_path);
                }
    
                $audioFile->delete(); // Optional: clean up audio DB entries
            }
    
            // Reduce used storage for the user
            $this->reduceUserStorageUsage($filePaths);
    
            // Delete the sentence itself
            $sentence->delete();
    
            return successResponse('Sentence and associated audio files deleted successfully.', 200);
    
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }


    
    public function getLatestShowPairs(Request $request)
    {
       // Fetch the latest 3 sentences
        $latestSentences = Sentence::where('user_id', $this->userID)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        // Fetch the latest 3 words
        $latestWords = Word::where('user_id', $this->userID)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        // Create an anonymous object to hold sentences and words
        $data = new \stdClass();
        $data->sentences = $latestSentences;
        $data->words = $latestWords;

        // Return the combined resource
        $LatestShowPairsResource = new LatestShowPairsResource($data);
        return successResponse('Latest Pairs List Retrieved', $LatestShowPairsResource, 200);
       
    }

}
