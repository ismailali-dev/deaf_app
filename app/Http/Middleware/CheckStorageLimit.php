<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStorageLimit
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        $now = now();

        $subscription = $user->subscription;
        $isSubscribed = $subscription && $subscription->status === 'active';

        // Determine max allowed storage (in bytes)
        if ($isSubscribed) {
            $planStorageInMb = $subscription->plan->storage_in_mb;
        } else {
            $daysSinceSignup = $now->diffInDays($user->created_at);
            $trialExpired = $daysSinceSignup >= 7;
            $planStorageInMb = $trialExpired ? 0 : 10240; // 10GB = 10240MB during trial, 0 after
        }

        $maxBytes = $planStorageInMb * 1024 * 1024;
        $usedBytes = $user->storage_used_in_bytes ?? 0;

        if ($usedBytes >= $maxBytes) {
            return errorResponse('Storage limit reached. Please upgrade your plan.', 403);
        }

        return $next($request);
    }
}