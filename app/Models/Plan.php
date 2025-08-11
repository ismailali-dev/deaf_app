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
    
    
    public function getRenewableTypeForProduct($store, $productId)
    {
        if ($store === 'play_store') {
            if ($this->play_store_monthly_product_id === $productId) {
                return 'month';
            } elseif ($this->play_store_yearly_product_id === $productId) {
                return 'year';
            }
        } elseif ($store === 'app_store') {
            if ($this->app_store_monthly_product_id === $productId) {
                return 'month';
            } elseif ($this->app_store_yearly_product_id === $productId) {
                return 'year';
            }
        }
    
        return null; // fallback
    }

}
