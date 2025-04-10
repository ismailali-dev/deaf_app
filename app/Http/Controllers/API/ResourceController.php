<?php

namespace App\Http\Controllers\API;

use App\Models\Resource;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Http\Resources\GovermentResourcesResourcesList;

class ResourceController extends BaseController
{
    
    /**
   
    Change Password
   

    **/

    public function getResourcesList(Request $request)
    {
        
        $query = Resource::query();
        
       
        $resources = $this->getFilteredData($request, $query);
         
        $resources = GovermentResourcesResourcesList::collection($resources);
        
        return successResponse('Resources List Retrieved',$resources, 200);
    }
    
    public function getResourceById($id)
    {
        $resource = Resource::find($id); // Fetch resource by ID
        $resource = GovermentResourcesResourcesList::make($resource);
        return successResponse('Resource Retrieved',$resource, 200);
           
    }
    
    



}
