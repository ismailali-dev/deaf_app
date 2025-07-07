<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['name', 'storage_in_mb', 'price', 'revenuecat_product_id'];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
