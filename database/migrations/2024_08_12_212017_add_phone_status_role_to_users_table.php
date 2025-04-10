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
        Schema::table('users', function (Blueprint $table) {
            
            $table->unsignedBigInteger('role_id')->after('id')->nullable(); // Add role_id column
            $table->string('username')->unique()->nullable()->after('name'); // Username, unique
            $table->string('phone')->nullable(); // Add phone column
            $table->tinyInteger('status')->default(1); // Add status column as tiny integer, default is 1 (active/enabled)
            $table->date('date_of_birth')->nullable(); // Date of birth
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('date_of_birth'); // Gender
            $table->string('avatar')->nullable()->after('gender'); // Avatar (image URL)
           
        
        
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'status',
                'role_id',
                'username',
                'date_of_birth',
                'gender',
                'avatar',
                
            ]);
            
            
        });
    }
};
