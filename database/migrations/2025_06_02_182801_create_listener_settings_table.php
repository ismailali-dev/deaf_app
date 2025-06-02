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
       Schema::create('listener_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
        
            $table->boolean('autosend')->default(false);
            $table->boolean('notification')->default(false); 
            $table->boolean('mute')->default(false); 
            $table->timestamps();
        
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listener_settings');
    }
};
