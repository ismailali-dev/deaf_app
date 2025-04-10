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
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('latitude', 10, 7)->nullable(); // Nullable latitude
            $table->decimal('longitude', 10, 7)->nullable(); // Nullable longitude
            $table->string('duration')->nullable(); // Nullable duration
            $table->integer('age_group_from')->nullable(); // Nullable age group start
            $table->integer('age_group_to')->nullable(); // Nullable age group end
            $table->enum('status', ['active', 'inactive'])->nullable(); // Nullable status
            $table->timestamp('end_time')->nullable(); // Nullable end time
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('broadcasts');
    }
};
