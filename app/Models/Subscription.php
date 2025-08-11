<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Plan;

class Subscription extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    
    protected $casts = [
        'ends_at' => 'datetime',
        'renewable_date' => 'date',
        'cancelled_at' => 'datetime', // timestamp bhi Laravel mein datetime cast se handle ho jata hai
    ];
    

    protected $guarded = ['id'];
    
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    
    protected static function booted()
    {
        static::creating(function ($subscription) {
            // Get membership details from the selected membership_id
            $membership = Plan::find($subscription->membership_id);
    
            if ($membership) {
                $subscription->title = $membership->title;
                $subscription->amount = $membership->amount;
            }
            
           
            $subscription->status = $subscription->is_active == 1 ? 'active' : 'pending';
            
            // Platform default
            $subscription->platform = 'google';
    
            // Set dates based on renewable_type
            $now = Carbon::now();
            
            if ($subscription->renewable_type === 'month') {
                $subscription->renewable_date = $now->copy()->addMonth();
                $subscription->ends_at = $now->copy()->addMonth();
            } elseif ($subscription->renewable_type === 'year') {
                $subscription->renewable_date = $now->copy()->addYear();
                $subscription->ends_at = $now->copy()->addYear();
            }
                
            // if ($subscription->renewable_type === 'month') {
            //     $subscription->renewable_date = $now->copy()->addMonth();
            //     $subscription->ends_at = $now->copy()->addMonth();
            // } elseif ($subscription->renewable_type === 'year') {
            //     $subscription->renewable_date = $now->copy()->addYear();
            //     $subscription->ends_at = $now->copy()->addYear();
            // } else {
            //     // Fallback if none set
            //     $subscription->renewable_date = $now;
            //     $subscription->ends_at = $now;
            // }
    
            // Optional: if you want to auto-activate
            if ($subscription->is_active === null) {
                $subscription->is_active = 1;
            }
    
            // You can log or debug to confirm
            // \Log::info('Creating Subscription (final):', $subscription->toArray());
        });
    }
    
    
}