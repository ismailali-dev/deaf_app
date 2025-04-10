<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use  Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    // TODO: Add more methods to return different responses

    public function render($request, Throwable $exception)
    {

        if($exception instanceof AuthenticationException){
            return errorResponse('you are not authenticated. Please login.', 401);
        }
        if ($exception instanceof UnauthorizedException) {
            return errorResponse('You do not have required authorization.', 403);
        }
        if($exception instanceof ValidationException){
            return errorResponse($exception->getMessage(), 422);
        }
        if($exception instanceof ModelNotFoundException){
            return errorResponse($exception->getMessage(), 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return errorResponse('The specified URL cannot be found.', 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return errorResponse($exception->getMessage(), 405);
        }
        
        if ($exception instanceof ValidationException) {
            
            return errorResponse($exception->validator->errors()->first(), 422);
        }
        
        
        $errorFile = $exception->getFile();
        $errorLine = $exception->getLine();
        
        // dd(get_class($exception), $errorFile, $errorLine, $exception->getMessage());
         return errorResponse($exception->getMessage(), 500, config('app.debug') ? $exception->getTrace() : []);
    }
}
