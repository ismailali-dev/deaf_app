<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Controllers\API\BaseController;
use App\Http\Requests\Therapist\SignupSetPasswordRequest;
use App\Models\InvitationLink;
use App\Models\User;
use App\Models\Device;
// use App\Traits\TwilioTrait;
use App\Mail\OtpEmail;
use Illuminate\Support\Facades\Mail; // Ensure this is present
use Illuminate\Validation\ValidationException;
use Seshac\Otp\Otp;
use Carbon\Carbon; // Import Carbon for handling date and time
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Http\Resources\Common\ProfileResource;
use Cache;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;
use App\Models\ParentApprovalRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\FirebasePushNotification;


/**
 * @group Auth
 *
 * APIs for managing users
 */

class AuthController extends BaseController
{
   
   
    // public function register(RegisterRequest $request)
    // {
    //     $data = $request->except(['password_confirmation']);
    //     $user = User::create($data);
    //     return successResponse('User registered successfully',$user);
    // }
    
    public function register(RegisterRequest $request)
    {
        $data = $request->except(['password_confirmation']);
        
        
        // Retrieve device_id from request
        $deviceId = $request->device_id;
        
       // Store session data in Redis
        Cache::put("session:{$deviceId}", $data, now()->addMinutes(10)); // Store for 10 minutes
        
        // Generate OTP
        $otp = Otp::generate($data['email']);

           
        if (!$otp->status) {
            return errorResponse($otp->message, 413);
        }

      
        Mail::to($request->email)->send(new OtpEmail($otp->token));
    

        return successResponse('Registration initiated. Please verify your email using the OTP sent to your email.');
    }
    
    
    public function socialAuth(Request $request)
    {
        $request->validate([
             'google_token' => 'required|string',
             'device_type' => 'sometimes|string', 
             'role_id' => 'required|in:2,3',
        ]);
        

        $idToken = $request->input('google_token'); // Google ID token sent 
         
        try {
            
            list($header, $payload, $signature) = explode('.', $idToken);
            $jsonToken = base64_decode($payload);
            $payload = json_decode($jsonToken, true);
            
    
            // Check if user already exists
            $user = User::where('email', $payload['email'])->first();
    
            if ($user) {
                
                // Check if email is not already verified
                if (is_null($user->email_verified_at)) {
                    $user->email_verified_at = now();
                    $user->save();
                }
            
                // Check if the profile is incomplete
                if ($user->profile_status === 'incomplete') {
                    // Automatically log in the user
                    $token = auth('api')->login($user);
    
                    // Store device information if provided
                    if ($request->has('device_type')) {
                        $deviceInfo = $request->except(['google_token']);
                        $user->devices()->updateOrCreate(['device_type' => $request->device_type], $deviceInfo);
                    }
    
                    // Prepare response for incomplete profile
                    $success['token'] = $token;
                    $success['user'] = ProfileResource::make($user);
                  
    
                    return successResponse('Logged In successful.', $success);
                }
    
                // For users with a complete profile, generate a token
                $token = auth('api')->login($user);
    
                // Store device information if provided
                if ($request->has('device_type')) {
                    $deviceInfo = $request->except(['google_token']);
                    $user->devices()->updateOrCreate(['device_type' => $request->device_type], $deviceInfo);
                }
    
                // Prepare response for complete profile
                $success['token'] = $token;
                $success['user'] = ProfileResource::make($user);
    
                return successResponse('Login successful.', $success);
            }
    
            $name = !empty($payload['name']) ? $payload['name'] : explode('@', $payload['email'])[0];
            
            $username = !empty($payload['given_name']) ? $payload['given_name'] : explode('@', $payload['email'])[0];

            // User does not exist, register them
            // $user = User::create([
            //     'name' => $name,
            //     'email' => $payload['email'],
            //     'google_id' => '', 
            //     'username' => $username, 
            //     'password' => Hash::make(Str::random(16)), 
            //     'profile_status' => 'incomplete', // Mark profile as incomplete
            // ]);
            
            $user = User::create([
                'name' => $name,
                'email' => $payload['email'],
                'google_id' => '', 
                'username' => $username, 
                'password' => Hash::make(Str::random(16)), 
                'profile_status' => 'complete',
                'role_id' => $request->role_id,
            ]);
    
            // Automatically log in the new user
            $token = auth('api')->login($user);
    
            // Store device information if provided
            if ($request->has('device_type')) {
                $deviceInfo = $request->except(['google_token']);
                $user->devices()->updateOrCreate(['device_type' => $request->device_type], $deviceInfo);
            }
    
            // Prepare response for new user with incomplete profile
            $success['token'] = $token;
            $success['user'] = ProfileResource::make($user);
            
    
            return successResponse('Logged In successful.', $success);
            
        } catch (\Exception $e) {
            return errorResponse('Google token verification failed: ' . $e->getMessage(), 500);
        }
    }
    

   
        
        
   public function setProfile(Request $request)
    {
        // Validate request input
        $validatedData = $request->validate([
            // 'user_id' => 'required|exists:users,id',
            'device_id' => 'required|string',
            'name' => 'nullable|string|max:255',
            'address' => 'required',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'device_type' => 'sometimes|string', 
        ]);
        
        try {
            
    
    
        // Retrieve the user by ID
        $user = User::find(auth()->user()->id);
    
        // If the user is not found, return an error
        if (!$user) {
            return errorResponse('User not found. Please restart the registration process.', 400);
        }
    
        // // Check if the user's email is verified (check the email_verified_at field)
        // if (is_null($user->email_verified_at)) {
        //     return errorResponse('Email not verified. Please verify your email first.', 400);
        // }
    
    
     
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            // Upload the file and get the path
            $uploadedPath = $request->file('avatar')->store('users/avatar', 'public');
            $validatedData['avatar'] = $uploadedPath;
            }
        } catch (\Exception $e) {
            // Catch any errors related to file upload and return the error message
            return errorResponse('Image upload failed: ' . $e->getMessage(), 400);
        }
    
    
        
        $validatedData['profile_status'] = 'complete';
        
        $validatedData['storage_used_in_bytes'] = 0;
        
        
        // Update the user profile with the validated data
        $user->update($validatedData);
        
        $token = auth('api')->login($user);
    
        // Store device information if provided
        if ($request->has('device_type')) {
            $deviceInfo = $request->except(['google_token']);
            $user->devices()->updateOrCreate(['device_type' => $request->device_type], $deviceInfo);
        }

        // Prepare response for new user with incomplete profile
        $success['token'] = $token;
        $success['user'] = ProfileResource::make($user);
            
            
    
        // Return success response
        return successResponse('Profile updated successfully.',$success);
    }


    /**
     * Login 
     */
//   public function login(LoginRequest $request)
//     {
//         $credentials = $request->only(['email', 'password']);
    
//         try {
//             if ($token = auth('api')->attempt($credentials)) {
//                 $user = auth('api')->user();
    
                
//                 // Role ID check
//                 if ($user->role_id != $request->role_id) {
                    
//                     if ($user->role_id == 3) {
//                         return errorResponse('Access Denied! Deaf users cannot access Listener accounts.', 403);
//                     } elseif ($user->role_id == 2) {
//                         return errorResponse('Access Denied! Listener users cannot access Deaf accounts.', 403);
//                     } else {
//                         return errorResponse('Unauthorized access detected.', 403);
//                     }
//                 }
    
//                 $success['token'] = $token;
    
//                 // Device info update
//                 if ($request->has('device_type')) {
//                     $deviceInfo = $request->except(['email', 'password', 'role_id']);
//                     $user->devices()->updateOrCreate(['device_type' => $request->device_type], $deviceInfo);
//                 }
                
          
    
//                 $user = ProfileResource::make($user);
//                 $success['user'] = $user;
    
//                 return successResponse('Logged in successfully', $success);
//             } else {
//                 return errorResponse('Wrong credentials - Email or Password is incorrect');
//             }
//         } catch (\Exception $e) {
//             return errorResponse($e->getMessage(), $e->getCode() ?: 500);
//         }
//     }


    public function login(LoginRequest $request)
    {
        $credentials = $request->only(['email', 'password']);
    
        try {
            if ($token = auth('api')->attempt($credentials)) {
                $user = auth('api')->user();
    
                // Check if role matches
                if ($user->role_id != $request->role_id) {
                    if ($user->role_id == 3) {
                        return errorResponse('Access Denied! Deaf users cannot access Listener accounts.', 403);
                    } elseif ($user->role_id == 2) {
                        return errorResponse('Access Denied! Listener users cannot access Deaf accounts.', 403);
                    } else {
                        return errorResponse('Unauthorized access detected.', 403);
                    }
                }
    
                // Store device info with FCM token
                if ($request->has('device_token') && $request->has('device_type')) {
                    $deviceData = [
                        'device_token' => $request->device_token,
                        'device_type' => $request->device_type,
                        'device_info' => $request->device_info ?? null,
                    ];
    
                    // Ensure relation is defined in User model
                    $user->devices()->updateOrCreate(
                        ['device_token' => $request->device_token],
                        $deviceData
                    );
                }
    
                // Prepare response
                $success = [
                    'token' => $token,
                    'user' => new ProfileResource($user),
                ];
    
                return successResponse('Logged in successfully', $success);
            } else {
                return errorResponse('Wrong credentials - Email or Password is incorrect');
            }
        } catch (\Exception $e) {
            return errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }





    /**
     * signupSetPassword 
     */
    public function signupSetPassword(SignupSetPasswordRequest $request)
    {
        try {
            $response = [];
            $request = $request->safe();
            $checkInvitation = InvitationLink::where('token',$request->token)->first();
            if (!Therapist::where('email',$checkInvitation->email)->exists()) {
                // $sent = $this->sendOtpCode($checkInvitationLinkExpired->email,'email');
                $data = [
                    "email"=>$checkInvitation->email,
                    "password"=>$request->password,
                ];
                $therapist = Therapist::create($data);
                $checkInvitation->where('token', $request->token)->delete();
                $response = successResponse("Your password has been updated successfully.",$therapist);
            } else {
                $response = errorResponse('Email already registered');
            }
        } catch (\Exception $e) {
            $response = errorResponse($e->getMessage(), $e->getCode());
        }
        return $response;
    }

     
    /**
     * Logout

    */
    public function logout(Request $request)
    {
        // Get the authenticated user using the API guard
        $user = auth()->guard('api')->user();
    
        // Check if a device type is provided
        if ($request->has('device_type')) {
            // Delete devices of the specified type
            $user->devices()->where('device_type', $request->device_type)->delete();
        } else {
            // Delete all devices for the user
            $user->devices()->delete();
        }
    
        // Blacklist the JWT token to effectively log out
        auth()->guard('api')->logout();
    
        // Return success response
        return successResponse('Logged out successfully');
    }

    /**
    * Refresh Token
    */

    public function refreshJWTToken(Request $request)
    {
        try {
            $user = auth()->guard('api')->user();
            if ($user) {
                $token = request()->bearerToken();
                $response['user'] = $user;
                $response['token'] = $token;
                return successResponse('Token Refreshed Successfully',$response);
            } else {
                return errorResponse('Invalid User Request');
            }
        } catch (\Exception $e) {
            return errorResponse($e->getMessage(), $e->getCode());
        }
    }

     /**
     * Forgot Password 
     */
     
    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);
            
            $email = $request->email;

            // Check if the user exists in the database
            $user = User::where('email', $email)->first();
            if (!$user) {
                return errorResponse('User does not exist.', 404);
            }
            
           $otp =  Otp::generate($email);
         
         
          
           if(!$otp->status)
           {
             return errorResponse($otp->message, 413);
           }

            // Send the OTP email
            Mail::to($email)->send(new OtpEmail($otp->token));
           
            return successResponse('OTP sent successfully to your email');
            
            
            
        } catch (\Exception $e) {
            
           
            return errorResponse($e->getMessage(), $e->getCode());
        }
    }
    
    
     /**
     * Verfiy OTP 
     */
     
    public function resendOtp(Request $request)
    {
        // Validate the input
        $request->validate([
            'email' => 'required|email',
            'device_id' => 'required_if:context,signup|string',
            'context' => 'required|in:signup,forgot_password,parent_approval',
        ]);
    
        $email = $request->email;
        $context = $request->context;
    
        if ($context === 'signup') {
            // Retrieve session data using device_id
           
            $sessionData = Cache::get("session:{$request->device_id}");
            if (!$sessionData) {
                return errorResponse('Session expired or mismatched. Please restart the registration process.',400);
    
            }
    
            // Ensure the email matches the session data
            if ($sessionData['email'] !== $email) {
                return errorResponse('Email does not match the registered session data.',404);
                
            }
        } 
        elseif ($context === 'parent_approval') {
            $parentRequest = ParentApprovalRequest::where('email', $email)
                                ->latest()
                                ->first();

            if (!$parentRequest) {
                return errorResponse('No parent approval request found for this email.', 404);
            }

            
            $otp = Otp::generate($email);
            if (!$otp->status) {
                
                return errorResponse($otp->message,413);
              
            }
        
            // Send OTP via email
             Mail::to($email)->send(new OtpEmail($otp->token));
        
            return successResponse('OTP sent successfully to your email.');
        }
        else {
            // For forgot password, check if the user exists
            $user = User::where('email', $email)->first();
            if (!$user) {
                
                return errorResponse('User does not exist.',404);
                
                
            }
        }
    
        // Generate OTP
        $otp = Otp::generate($email);
        if (!$otp->status) {
            
            return errorResponse($otp->message,413);
          
        }
    
        // Send OTP via email
         Mail::to($email)->send(new OtpEmail($otp->token));
    
        return successResponse('OTP sent successfully to your email.');
       
    }
    
    
    public function verifyOtp(Request $request)
    {
        
        // Validate the input
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:4',
            'device_id' => 'required_if:context,signup|string',
            'context' => 'required|in:signup,forgot_password,parent_approval',
        ]);
    
    
    
        $email = $request->email;
        $otp = $request->otp;
        $context = $request->context;
        
        try {
    
        //Validate OTP
        $otpValid = Otp::validate($email, $otp);
        if (!$otpValid->status) {
             return errorResponse('Invalid OTP. Please try again.',400);
          
        }
    
        if ($context === 'signup') {
            // Retrieve session data using device_id
            
            $sessionData = Cache::get("session:{$request->device_id}");
            
            
            if (!$sessionData) {
                 return errorResponse('Session expired or mismatched. Please restart the registration process',400);
                
            }
    
            // Ensure the email matches the session data
            if ($sessionData['email'] !== $email) {
                return errorResponse('Email does not match the registered session data.',400);
                
            }
    
            // Mark the email as verified and create the user
            $sessionData['email_verified'] = true;
            $user = User::create($sessionData);
            $user->email_verified_at = now();
            $user->save();
    
    
            $token = auth('api')->login($user);

            // Store device information if provided
            if ($request->has('device_type')) {
                $deviceInfo = $request->except(['email', 'otp', 'context']);
                $user->devices()->updateOrCreate(['device_type' => $request->device_type], $deviceInfo);
            }
    
            // Prepare user response
            $success['token'] = $token;
            $userResource = ProfileResource::make($user);
            $success['user'] = $userResource;
    
            return successResponse('Email verified successfully. Please complete your profile', $success);
    
           
        } 
        elseif ($context === 'parent_approval') {
            $parentRequest = ParentApprovalRequest::where('email', $email)
                                ->latest()
                                ->first();

            if (!$parentRequest) {
                return errorResponse('No parent approval request found for this email.', 404);
            }

            $parentRequest->is_approved_by_parent = true;
            $parentRequest->save();

            return successResponse('Parent approval verified successfully.');
        }
        else {
            
            
            return successResponse('OTP verified successfully. You can now reset your password.');
            
          
        }
            
        }
        catch (ValidationException $e) {
            
            throw $e; 
          
        }
        catch (\Exception $e) {
            return errorResponse($e->getMessage(), $e->getCode());
        }
        
    }
    
    public function resetPassword(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'email' => 'required|email',
                'otp' => 'required',
                'new_password' => [
                'required',
                Password::min(6)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols(),
                ],
                'password_confirmation' => 'required|same:new_password',
            ]);
    
            $email = $request->email;
            $otpToken = $request->otp;
            $newPassword = $request->new_password;
    
            // Check if the user exists in the database
            $user = User::where('email', $email)->first();
            if (!$user) {
                return errorResponse('User does not exist.', 404);
            }
    
            // Validate the OTP
            $otp = Otp::validate($email, $otpToken);
            if (!$otp->status) {
                return errorResponse('Invalid or expired OTP.', 400);
            }
            
            // Check if the new password is the same as the old password
            if (Hash::check($newPassword, $user->password)) {
                return errorResponse('New password cannot be the same as the old password.', 400);
            }
        
            // Update the user's password
            $user->password = $newPassword;
            $user->save();
    
            // Invalidate the OTP after successful use
            DB::table('otps')->where('identifier', $email)->where('token', $otpToken)->delete();
    
            return successResponse('Password has been reset successfully.');
            
        } 
        catch (ValidationException $e) {
            
            throw $e; 
          
        }
        catch (\Exception $e) {
            return errorResponse($e->getMessage(), $e->getCode());
        }
    }
    
    
    
    public function handleSubscription(Request $request)
    {
        $data = $request->all();
        $user = User::where('email', @$data['event']['app_user_id'])->first();
    
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
    
        $platform = strtolower(@$data['event']['store']);
        $productId = @$data['event']['product_id'];
    
        if ($platform === 'play_store') {
            $plan = Plan::where(function ($q) use ($productId) {
                $q->where('play_store_monthly_product_id', $productId)
                  ->orWhere('play_store_yearly_product_id', $productId);
            })->first();
    
            if (!$plan) {
                return response()->json(['message' => 'Plan not found OR Invalid Product Id'], 404);
            }
    
            return $this->handleGoogleSubscription($data['event'], $user, $plan, $platform, $productId);
    
        } elseif ($platform === 'app_store') {
            $plan = Plan::where(function ($q) use ($productId) {
                $q->where('app_store_monthly_product_id', $productId)
                  ->orWhere('app_store_yearly_product_id', $productId);
            })->first();
    
            if (!$plan) {
                return response()->json(['message' => 'Plan not found OR Invalid Product Id'], 404);
            }
    
            return $this->handleAppleSubscription($data['event'], $user, $plan, $platform, $productId);
        }
    
        return response()->json(['message' => 'Invalid platform'], 400);
    }
     private function handleGoogleSubscription($data, $user, $membership, $platform, $productId)
    {
        $now = now();
        $status = $this->mapGoogleStatus(@$data['type']);
        $expires_at = isset($data['expiration_at_ms']) ? Carbon::createFromTimestampMs($data['expiration_at_ms'])->setTimezone('UTC') : null;
        $endsAt = $expires_at;
        $renewableType = $membership->getRenewableTypeForProduct($platform, $productId);
    
        $subscription = Subscription::where('subscription_id', @$data['original_transaction_id'])
            ->where('platform', 'google')
            ->first();
    
        if (!$subscription) {
            $datasubscription = [
                'user_id' => $user->id,
                'title' => @$data['entitlement_ids'][0],
                'plan_id' => $membership->id,
                'amount' => @$data['price'],
                'platform' => 'google',
                'renewable_type' => $renewableType,
                'renewable_date' => $expires_at,
                'subscription_id' => @$data['original_transaction_id'],
                'status' => $status,
                'ends_at' => $endsAt,
                'is_active' => $status === 'expired' ? 0 : 1,
                'is_cancelled' => 0,
                'cancelled_at' => null,
            ];
    
            $subscription = Subscription::create($datasubscription);
    
            $subscriptionName = @$data['entitlement_ids'][0];
            if ($subscriptionName === 'AI Profit- under construction') {
                $subscriptionName = "AI Profit";
            }
    
            $title = 'Billing Alert';
            $amount = number_format(@$data['price'], 2);
            $body = "You’ve been charged $$amount for $subscriptionName Subscription. Thank you for staying with Deaf Talk!";
            $user->notify(new FirebasePushNotification($title, $body));
    
        } else {
            $updateData = [
                'status' => $status,
                'ends_at' => $endsAt,
            ];
    
            $notifications = [
                'expired' => [
                    'is_active' => 0,
                    'title' => 'Subscription Expired',
                    'message' => 'subscription has been expired. Renew anytime to continue using Deaf Talk.'
                ],
                'cancelled' => [
                    'is_cancelled' => 1,
                    'cancelled_at' => Carbon::now('UTC'),
                    'message' => 'subscription has been canceled. Renew anytime to continue using Deaf Talk.'
                ]
            ];
    
            if (isset($notifications[$status])) {
                $updateData = array_merge($updateData, $notifications[$status]);
                $subscriptionName = @$data['entitlement_ids'][0];
                if ($subscriptionName === 'AI Profit- under construction') {
                    $subscriptionName = "AI Profit";
                }
                $body = "Your $subscriptionName {$notifications[$status]['message']}";
                $user->notify(new FirebasePushNotification($subscriptionName, $body));
            }
    
            $subscription->update($updateData);
        }
    
        return response()->json(['message' => 'Google Subscription Updated']);
    }
    
    private function handleAppleSubscription($data, $user, $membership, $platform, $productId)
    {
        $status = $this->mapAppleStatus($data['type']);
        $expires_at = isset($data['expiration_at_ms']) ? Carbon::createFromTimestampMs($data['expiration_at_ms'])->setTimezone('UTC') : null;
        $endsAt = $expires_at;
        $renewableType = $membership->getRenewableTypeForProduct($platform, $productId);
    
        $subscription = Subscription::where('subscription_id', $data['original_transaction_id'])
            ->where('platform', 'apple')
            ->first();
    
        if (!$subscription) {
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $membership->id,
                'title' => $data['entitlement_ids'][0],
                'amount' => $data['price'],
                'platform' => 'apple',
                'renewable_type' => $renewableType,
                'renewable_date' => $expires_at,
                'subscription_id' => $data['original_transaction_id'],
                'status' => $status,
                'ends_at' => $endsAt,
                'is_active' => $status === 'expired' ? 0 : 1,
                'is_cancelled' => 0,
                'cancelled_at' => null,
            ]);
    
            $title = 'Billing Alert';
            $amount = number_format($data['price'], 2);
            $body = "You’ve been charged $$amount. Thank you for staying with Deaf Talk!";
            $user->notify(new FirebasePushNotification($title, $body));
    
        } else {
            $updateData = [
                'status' => $status,
                'ends_at' => $endsAt,
            ];
    
            $notifications = [
                'expired' => [
                    'is_active' => 0,
                    'title' => 'Subscription Expired',
                    'message' => 'subscription has been expired. Renew anytime to continue using Deaf Talk.'
                ],
                'cancelled' => [
                    'is_cancelled' => 1,
                    'cancelled_at' => Carbon::now('UTC'),
                    'title' => 'Subscription Canceled',
                    'message' => 'subscription has been canceled. Renew anytime to continue using Deaf Talk.'
                ]
            ];
    
            if (isset($notifications[$status])) {
                $updateData = array_merge($updateData, $notifications[$status]);
                $subscriptionName = @$data['entitlement_ids'][0];
                $body = "Your $subscriptionName {$notifications[$status]['message']}";
                $user->notify(new FirebasePushNotification($notifications[$status]['title'], $body));
            }
    
            $subscription->update($updateData);
        }
    
        return response()->json(['message' => 'Apple Subscription Updated']);
    }


     private function mapGoogleStatus($periodType)
        {
            switch ($periodType) {
                case 'INITIAL_PURCHASE': return 'active';
                case 'CANCELLATION': return 'cancelled';
                case 'EXPIRATION': return 'expired';
                case 'EXPIRED': return 'expired';
                case 'RENEWAL' : return 'active';
                case 'TRIAL': return 'pending';
                default: return 'pending';
            }
        }
        
        private function mapAppleStatus($type)
        {
            switch ($type) {
                case 'INITIAL_PURCHASE': return 'active';
                case 'CANCELLATION': return 'cancelled';
                case 'EXPIRATION': return 'expired';
                case 'EXPIRED': return 'expired';
                case 'RENEWAL' : return 'active';
                case 'TRIAL': return 'pending';
                default: return 'pending';
            }
        }
        
    
    
   
    

    



}
