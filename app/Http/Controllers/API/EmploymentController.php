<?php

namespace App\Http\Controllers\API;

use App\Models\Employment;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Http\Resources\EmploymentResource;

class EmploymentController extends BaseController
{
    
    /**
   
    Change Password
   

    **/

    public function getEmploymentList(Request $request)
    {
        $query = Employment::query();
        $employments = $this->getFilteredData($request, $query);
        $employments = EmploymentResource::collection($employments);
        return successResponse('Employment List Retrieved',$employments, 200);
    }
    
    public function getEmploymenById($id)
    {
        $employment = Employment::find($id); 
        $employment = EmploymentResource::make($employment);
        return successResponse('Employment Retrieved',$employment, 200);
           
    }
    
    



}
