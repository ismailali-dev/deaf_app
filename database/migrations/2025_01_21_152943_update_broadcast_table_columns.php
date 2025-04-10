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
        Schema::table('broadcasts', function (Blueprint $table) {
           

               // Modify the 'status' column to be a string instead of enum
                $table->string('status')->default('active')->nullable()->change();  // Changed enum to string
        
                // Ensure 'duration' column is being handled properly
                $table->string('duration')->nullable()->change();  // Modify if needed
                
                // Add new columns
                $table->string('type')->default(false)->after('longitude');
                $table->json('allowed_user_ids')->nullable()->after('type');
        
                // Use dateTime instead of timestamp
                $table->dateTime('end_time')->nullable()->change()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       
        Schema::table('broadcasts', function (Blueprint $table) {
            // Rollback any added or modified columns
            $table->dropColumn('type');
            $table->dropColumn('user_ids');
            $table->dropColumn('duration');  // If you want to drop this column
            $table->dropColumn('end_time');  // If you want to drop this column
        });
        
    }
};
