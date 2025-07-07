<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStorageLimit
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        $planStorageInMb = $user->subscription
            ? $user->subscription->plan->storage_in_mb
            : 500; // free default

        $maxBytes = $planStorageInMb * 1024 * 1024;

         if ($user->storage_used_in_bytes >= $maxBytes) {
            return errorResponse('Storage limit reached. Please upgrade your plan.', 403);
        }

        return $next($request);
    }
}
