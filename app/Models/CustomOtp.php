<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Seshac\Otp\Models\Otp as BaseOtp; // Use the original vendor model

class CustomOtp extends BaseOtp
{
    use HasFactory;
    
    public function isExpired(): bool
    {
        // Store the current server time in the database
        $this->current_server_time = Carbon::now(); // Assuming you have a `current_server_time` field in your database
        
        dd($this->current_server_time);

        // Check if the OTP is marked as expired
        if ($this->expired) {
            return true;
        }

        // Calculate the expiration time
        $expirationTime = $this->generated_at->addMinutes($this->validity);

        // Compare the expiration time with the current server time
        if (Carbon::now()->lessThanOrEqualTo($expirationTime)) {
            return false; // OTP is still valid
        }

        // Mark OTP as expired and save the state
        $this->expired = true;
        $this->save();

        return true; // OTP has expired
    }

    /**
     * Get the expiration time of the OTP.
     *
     * @return Carbon
     */
    public function expiredAt(): Carbon
    {
        return $this->generated_at->addMinutes($this->validity);
    }
}
