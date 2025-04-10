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
        Schema::create('global_emergency_recordings', function (Blueprint $table) {
            $table->id(); // Auto-incrementing ID
            $table->string('type'); // Type of emergency (e.g., "fire", "medical")
            $table->string('sentence'); // Predefined sentence for the emergency
            $table->string('voice_path'); // Path to the voice recording file (e.g., in storage)
            $table->timestamps(); // Timestamps (created_at and updated_at)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('global_emergency_recordings');
    }
};
