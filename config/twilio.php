<?php

return [

    'default' => [
        'account_sid' => env('TWILIO_SID'),
        'auth_token'  => env('TWILIO_AUTH_TOKEN'),
        'from'        => env('TWILIO_NUMBER'), // your Twilio phone number
    ],

];