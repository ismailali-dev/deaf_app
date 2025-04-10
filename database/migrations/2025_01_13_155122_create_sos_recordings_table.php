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
        
        
       Schema::create('sos_recordings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('emergency_type'); // fire, crime, health, witness report
            $table->enum('sos_type', ['E1', 'E2', 'E3', 'E4']);
            $table->enum('file_type', ['audio', 'video']); // file type (audio or video)
            $table->string('file_path')->nullable(); // general file path
            $table->string('audio_file_path')->nullable(); // nullable audio path
            $table->string('video_file_path')->nullable(); // nullable video path
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sos_recordings');
    }
};
