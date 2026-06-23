<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
       '/broadcasting/auth',
       '/twiml/dial-dispatcher',
       '/twiml/after-answer',
        '/twiml/dispatcher',
       'twiml/dispatcher',
       'twiml/*'
    ];
}
