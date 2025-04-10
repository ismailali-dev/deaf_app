<?php

namespace App\Http\Controllers\API\Listener;

use App\Models\SignChart;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Http\Resources\Listener\SignChartResource\SignChartResourceList;

class SignChartController extends BaseController
{
    
    /**
    Change Password
    **/

    public function getSignChartList(Request $request)
    {
        $query = SignChart::query();
    
        if ($request->has('type')) {
            $query->where('sign_type', $request->input('type'));
        }
        
        $signchartList = $this->getFilteredData($request, $query);
    
       
      
        return successResponse('Sign Chart List Retrieved', SignChartResourceList::collection($signchartList), 200);
    }
    
    


}
