<?php

namespace App\Traits;
use Twilio\Rest\Client;

trait TwilioTrait
{

   
    /**
     * twillio sdk integration
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */

    private function getInstance(){
        
            $accountSid = config('services.twilio.sid');
            $authToken = config("services.twilio.token");
            $serviceSid = config("services.twilio.service_sid");
            $client = new Client($accountSid, $authToken);
            return $client;
    }


    //send twillio otp
    protected function sendOtpCode($reciever, $channel)
    {
       
        try {
            $client = $this->getInstance();
            $client->verify->v2->services($serviceSid)
                ->verifications
                ->create("$reciever", "$channel");

            $response = $this->twillioSuccessResponse();

        } catch (\Exception $e) {
            $response = $this->twillioErrorResponse($e->getMessage());
        }
        return $response;
    }

    // verify twillio otp 
    protected function verifyOtp($reciever,$verificationCode)
    {
        try {
                $accountSid = config('services.twilio.sid');
                $authToken = config("services.twilio.token");
                $serviceSid = config("services.twilio.service_sid");
                $client = new Client($accountSid, $authToken);
                $verificationCheck = $client->verify->v2->services($serviceSid)
                    ->verificationChecks
                    ->create(['code' => $verificationCode, 'to' => $reciever]);
                if ($verificationCheck->status == "approved") {
                    $response = $this->twillioSuccessResponse();
                } else {
                    $response = $this->twillioErrorResponse('Unable to verify OTP');
                }
            
        } catch (\Exception $e) {
            $response = $this->twillioErrorResponse($e->getMessage());
        }
        return $response;
    }

    //Error response
    private function twillioErrorResponse($error)
    {
        $responseData['message'] = $error;
        $responseData['success'] = false;
        return $responseData;
    }

    //Success Response
    private function twillioSuccessResponse($response=[])
    {
        $responseData['message'] = "Success";
        $responseData['success'] = true;
        $responseData['response'] = $response;
        return $responseData;
    }
}
?>