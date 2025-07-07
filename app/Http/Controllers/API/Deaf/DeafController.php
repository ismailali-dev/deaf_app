<?php

namespace App\Http\Controllers\Deaf;

use App\Models\User\User;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Http\Requests\User\RegisterRequest;
use App\Http\Requests\User\UpdateUserProfileRequest;
use App\Http\Resources\User\ProfileResource;
use Illuminate\Support\Facades\Hash;

class DeafController extends BaseController
{

   public function getFreePlanUsage(Request $request)
    {
        $user = auth()->user();
    
        // If user has an active subscription, return error
        if ($user->subscription && $user->subscription->status === 'active') {
            return errorResponse('You are not on the free plan.', 400);
        }
    
        $usedInBytes = $user->storage_used_in_bytes ?? 0;
        $usedInMb = round($usedInBytes / (1024 * 1024), 2);
    
        $limitInMb = 500;
        $remaining = max(0, $limitInMb - $usedInMb);
        $percentUsed = round(($usedInMb / $limitInMb) * 100, 2);
    
        return response()->json([
            'status' => true,
            'data' => [
                'storage_used_in_mb' => $usedInMb,
                'storage_limit_in_mb' => $limitInMb,
                'storage_remaining_in_mb' => $remaining,
                'percent_used' => $percentUsed
            ]
        ]);
    }
    

}
