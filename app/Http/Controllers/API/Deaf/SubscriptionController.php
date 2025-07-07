<?php

namespace App\Http\Controllers\API\Deaf;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use App\Models\User\User;

class SubscriptionController extends BaseController
{
    public function getPlanUsage(Request $request)
    {
        $user = auth()->user();
        $subscription = $user->subscription;
    
        $isActive = $subscription && $subscription->status === 'active';
        $membership = $isActive ? $subscription->membership : null;
    
        $planTitle = $membership ? $membership->title : 'Free';
        $storageLimitMb = $membership ? $membership->storage_in_mb : 500;
    
        $usedInBytes = $user->storage_used_in_bytes ?? 0;
        $usedInMb = $usedInBytes / (1024 * 1024);
        $remainingMb = max(0, $storageLimitMb - $usedInMb);
        $percentUsed = round(($usedInMb / $storageLimitMb) * 100, 2);
    
        return successResponse('Plan usage retrieved', [
            'plan_title' => $planTitle,
            'storage_used' => $this->formatStorage($usedInBytes),
            'storage_limit' => $this->formatStorage($storageLimitMb * 1024 * 1024), // keep this
            'storage_remaining' => $this->formatStorage($remainingMb * 1024 * 1024),
            'percent_used' => $percentUsed . '%',
        ]);
    }
    
    protected function formatStorage($bytes)
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' Bytes';
        }
    }
}
