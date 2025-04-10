<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HandleValidationErrors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Attempt to validate the request
        try {
            return $next($request);
        } catch (ValidationException $e) {
           
             return errorResponse($e->validator->errors()->first(),422);
        }
    }
}

