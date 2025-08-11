<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\PConstant;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Controllers\Controller as Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;

use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image;

class BaseController extends Controller
{

    protected $apiResponse = [];
    protected $user;
    protected $userID;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user =  auth()->user();
            $this->userID = $this->user?$this->user->id:null;
            return $next($request);
        });

    }
    
    
    protected function validateRequest(Request $request, array $rules)
    {
         return $request->validate($rules); // Let it throw ValidationException if validation failshrreutntrrer
    }
    

    public function syncPermissions(Request $request, $user)
    {
        $permissions = $request->get('permissions', []);

        // Get the roles
        $roles = Role::find($roles);

        // check for current role changes
        if( ! $user->hasAllRoles( $roles ) ) {
            // reset all direct permissions for user
            $user->permissions()->sync([]);
        } else {
            // handle permissions
            $user->syncPermissions($permissions);
        }

        $user->syncRoles($roles);
        return $user;
    }

   public function getFilteredData($request, $query)
    {
        
        $numericColumns = ['description']; // Add any other numeric string columns here
        
        // Check if $query is an instance of a collection
        if ($query instanceof \Illuminate\Support\Collection) {
           
            // Ensure $query is not empty
            if ($query->isEmpty()) {
                return collect(); // Return an empty collection if no results
            }
    
            // Collection filtering
            if ($request->has('search')) {
                $query = $query->filter(function ($item) use ($request) {
                    return stripos($item->name, $request->search) !== false; // Example search logic
                });
            }
    
            $start = $request->has('date_start') ? Carbon::parse($request->date_start)->startOfDay() : null;
            $end = $request->has('date_end') ? Carbon::parse($request->date_end)->endOfDay() : null;
    
            if ($start && $end) {
                $query = $query->filter(function ($item) use ($start, $end) {
                    return $item->created_at >= $start && $item->created_at <= $end;
                });
            } elseif ($start) {
                $query = $query->filter(function ($item) use ($start) {
                    return $item->created_at >= $start;
                });
            } elseif ($end) {
                $query = $query->filter(function ($item) use ($end) {
                    return $item->created_at <= $end;
                });
            }
    
            // Sort by the given column and direction
            if ($request->has('sortBy') && $request->has('sortType')) {
                
                if (in_array($request->sortBy, $numericColumns)) {
                    // Use orderByRaw to sort as numeric
                    $query->orderByRaw("CAST({$request->sortBy} AS UNSIGNED) {$request->sortType}");
                } else {
                    $query = $query->sortBy($request->sortBy, SORT_REGULAR, $request->sortType === 'desc');
                }
        
                
            }
            
    
            // Pagination (simulated for collection)
            $perPage = $request->has('perPage') ? (int)$request->perPage : 50;
            $currentPage = $request->has('page') ? (int)$request->page : 1;
            $query = $query->forPage($currentPage, $perPage)->values(); // Use ->values() to reset the keys
    
        } elseif ($query instanceof \Illuminate\Database\Eloquent\Builder) {
            
           
        // Query Builder filtering
        if ($request->has('search')) {
            $query = $query->search($request->search);
            // $query = $query->where('name', 'like', '%' . $request->search . '%'); // Example search logic
        }

        if ($request->has('date_start')) {
            $start = Carbon::parse($request->date_start)->startOfDay();
            $query->where('created_at', '>=', $start);
        }

        if ($request->has('date_end')) {
            $end = Carbon::parse($request->date_end)->endOfDay();
            $query->where('created_at', '<=', $end);
        }
        
        

       if ($request->has('sortBy') && $request->has('sortType')) {
            // Check if the sortBy column is in the numeric columns array
            if (in_array($request->sortBy, $numericColumns)) {
                // Use orderByRaw to sort as numeric
                $query->orderByRaw("CAST({$request->sortBy} AS UNSIGNED) {$request->sortType}");
            } else {
                // Regular sorting for other columns
                $query->orderBy($request->sortBy, $request->sortType);
            }
        }
        
        
        if($request->has('perPage')){
            // Pagination
            $perPage = $request->has('perPage') ? $request->perPage : 500;
            $query = $query->paginate($perPage);
        }
        else{
            $query = $query->get();
        }

        
    } else {
        // If $query is neither a Collection nor a Query Builder, return an empty collection
        return collect();
    }

    return $query;
}


    public function uploadFilePublicRepo($file,$path='public') {
        try {
            $path = $file->store($path, 's3');
            return $path = Storage::disk('s3')->url($path);
        } catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    // Base64 Upload to S3
    // @returns URL of the filename
    public function uploadBase64PublicRepo($file,$path='public')
    {
        $url = NULL;

        try {
            if (!empty($file)) {
                $pos  = strpos($file, ';');
                $expl = explode('/', substr($file, 0, $pos));
                $extension = (isset($expl[1])) ? $expl[1] : 'png';
                $value = substr($file, strpos($file, ',') + 1);
                $value = base64_decode($value);
                $imageName = $path.'/' . rand(111111111, 999999999) . '.' . $extension;
                $uploadToS3 = Storage::disk('s3')->put($imageName, $value, 's3');
                $url = Storage::disk('s3')->url($imageName);
            }
            return $url;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    
    public function uploadFile(Request $request, $fileKey, $folder = 'site', $disk = 'public')
    {
        if ($request->hasFile($fileKey) && $request->file($fileKey)->isValid()) {
            try {
                // Get the uploaded file
                $file = $request->file($fileKey);
                
                // Create an instance of the image
                $image = Image::make($file);
                
                // Compress the image (you can adjust the quality as needed, 75 is a good starting point)
                $image->encode('jpg', 75); // Compress to JPEG with 75% quality
                
                // Store the compressed image to the specified folder and disk
                $uploadedPath = $file->storeAs($folder, uniqid() . '.jpg', $disk);
                
                // Save the compressed image to the path
                $image->save(storage_path('app/' . $uploadedPath));
                
                return $uploadedPath;
            } catch (\Exception $e) {
                // Handle error
                return null;
            }
        }
        return null;
    }


    function base64_to_jpeg($base64_string, $output_file) {
        // open the output file for writing
        $ifp = fopen( $output_file, 'wb' );

        // split the string on commas
        // $data[ 0 ] == "data:image/png;base64"
        // $data[ 1 ] == <actual base64 string>
        $data = explode( ',', $base64_string );

        // we could add validation here with ensuring count( $data ) > 1
        fwrite( $ifp, base64_decode( $data[ 1 ] ) );

        // clean up the file resource
        fclose( $ifp );

        return $output_file;
    }
    
    protected function uploadFiles(array $files, $baseFolder, $disk = 'public')
    {
        $paths = [];
        
        // Create folder paths based on the current date
        $currentYear = now()->format('Y'); // Current year
        $currentMonth = now()->format('F'); // Current month
    
        foreach ($files as $file) {
            if ($file) {
                // Construct the folder path
                $folderPath = "{$baseFolder}/{$currentMonth}{$currentYear}";
                $path = $file->store($folderPath, $disk);
                $paths[] = $path;
            }
        }
    
        return $paths;
    }
    
    protected function updateUserStorageUsage(array $paths, $disk = 'public')
    {
        $totalBytesUsed = 0;
    
        foreach ($paths as $path) {
            $filePath = storage_path("app/{$disk}/{$path}");
            if (file_exists($filePath)) {
                $totalBytesUsed += filesize($filePath);
            }
        }
    
        if ($totalBytesUsed > 0 && $this->user) {
            $this->user->increment('storage_used_in_bytes', $totalBytesUsed);
        }
    
        return $totalBytesUsed;
    }
    
    protected function addToUserStorageUsage(array $paths, $disk = 'public')
    {
        $totalBytes = 0;
    
        foreach ($paths as $path) {
            $fullPath = Storage::disk($disk)->path($path);
            if (file_exists($fullPath)) {
                $totalBytes += filesize($fullPath);
            }
        }
    
        if ($totalBytes > 0 && $this->user) {
            $this->user->increment('storage_used_in_bytes', $totalBytes);
        }
    
        return $totalBytes;
    }

    
    protected function reduceUserStorageUsage(array $paths, $disk = 'public')
    {
        $totalBytesFreed = 0;
    
        foreach ($paths as $path) {
            $fullPath = Storage::disk($disk)->path($path);
            if (file_exists($fullPath)) {
                $totalBytesFreed += filesize($fullPath);
            }
        }
    
        if ($totalBytesFreed > 0 && $this->user) {
            $newValue = max(0, $this->user->storage_used_in_bytes - $totalBytesFreed);
            $this->user->update(['storage_used_in_bytes' => $newValue]);
        }
    
        return $totalBytesFreed;
    }
    
    

    
    

    /**
     * Get the guard to be used during authentication.
     * @return \Illuminate\Contracts\Auth\Guard
     */
    public function guard($role = null)
    {
        if($role == null){
            if(request()->segment(2) && in_array(request()->segment(2),['deaf','listener'])){
                $role = request()->segment(2);
            }
            $role = request()->segment(2);
        }
        return Auth::guard($role);
    }

}

?>