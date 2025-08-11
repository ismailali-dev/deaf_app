<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::table('plans', function (Blueprint $table) {
        // Drop old columns if they exist
        if (Schema::hasColumn('plans', 'revenuecat_product_id')) {
            $table->dropColumn('revenuecat_product_id');
        }
        if (Schema::hasColumn('plans', 'price_id')) {
            $table->dropColumn('price_id');
        }
        if (Schema::hasColumn('plans', 'product_id')) {
            $table->dropColumn('product_id');
        }
        if (Schema::hasColumn('plans', 'price')) {
            $table->dropColumn('price');
        }

        // Add new columns if they don't exist
        if (!Schema::hasColumn('plans', 'app_store_yearly_product_id')) {
            $table->string('app_store_yearly_product_id')->nullable();
        }
        if (!Schema::hasColumn('plans', 'app_store_monthly_product_id')) {
            $table->string('app_store_monthly_product_id')->nullable();
        }
        if (!Schema::hasColumn('plans', 'play_store_yearly_product_id')) {
            $table->string('play_store_yearly_product_id')->nullable();
        }
        if (!Schema::hasColumn('plans', 'play_store_monthly_product_id')) {
            $table->string('play_store_monthly_product_id')->nullable();
        }
    });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Re-add dropped columns
            $table->string('revenuecat_product_id')->nullable();
            $table->string('price_id')->nullable();
            $table->string('product_id')->nullable();
            //  Drop newly added columns
            $table->dropColumn([
                'app_store_yearly_product_id',
                'app_store_monthly_product_id',
                'play_store_yearly_product_id',
                'play_store_monthly_product_id',
                'renewable_type',
            ]);
        });
    }
};
