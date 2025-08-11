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
        $now = now();
    
        $isSubscribed = $subscription && $subscription->status === 'active';
    
        if ($isSubscribed) {
            // Use subscription plan limits
            $plan = $subscription->plan;
            $planTitle = $plan->name;
            $storageLimitMb = $plan->storage_in_mb;
            $trialExpired = false;
        } else {
            // Free trial logic (10GB for first 7 days, then 0)
            $createdAt = $user->created_at;
            $daysSinceSignup = $now->diffInDays($createdAt);
            $trialExpired = $daysSinceSignup >= 7;
    
            $planTitle = 'Free Trial';
            $storageLimitMb = $trialExpired ? 0 : 10240; // 10GB (10240 MB), or 0 after expiry
        }
    
        $usedInBytes = $user->storage_used_in_bytes ?? 0;
        $usedInMb = $usedInBytes / (1024 * 1024);
        $remainingMb = max(0, $storageLimitMb - $usedInMb);
        $percentUsed = $storageLimitMb > 0 
            ? round(($usedInMb / $storageLimitMb) * 100, 2) 
            : 100;
    
        return successResponse('Plan usage retrieved', [
            'plan_title' => $planTitle,
            'storage_used' => $this->formatStorage($usedInBytes),
            'storage_limit' => $this->formatStorage($storageLimitMb * 1024 * 1024),
            'storage_remaining' => $this->formatStorage($remainingMb * 1024 * 1024),
            'percent_used' => $percentUsed . '%',
            'trial_expired' => $trialExpired,
            'is_subscribed' => $isSubscribed,
            'days_remaining' => $trialExpired ? 0 : max(0, 7 - $daysSinceSignup),
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
