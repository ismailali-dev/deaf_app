<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop old columns
            $table->dropColumn(['plan_id', 'product_id']);

            // Add new columns
            $table->unsignedBigInteger('plan_id')->after("user_id");
            $table->string('title')->nullable()->after('plan_id');
            $table->decimal('amount', 10, 2)->nullable()->after('title');
            $table->enum('platform', ['google', 'apple'])->nullable()->after('amount');
            $table->string('renewable_type')->nullable()->after('platform');
            $table->timestamp('renewable_date')->nullable()->after('renewable_type');
            $table->string('subscription_id')->nullable()->after('renewable_date');
            $table->boolean('is_active')->default(true)->after('subscription_id');
            $table->boolean('is_cancelled')->default(false)->after('is_active');
            $table->timestamp('cancelled_at')->nullable()->after('is_cancelled');
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('product_id')->nullable();

            $table->dropColumn([
                'title', 'amount', 'platform', 'renewable_type',
                'renewable_date', 'subscription_id', 'slug',
                'is_active', 'is_cancelled', 'cancelled_at'
            ]);
        });
    }
};
