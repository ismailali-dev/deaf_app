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
        Schema::create('parent_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // child user
            $table->string('parent_name');
            $table->date('parent_dob')->nullable();
            $table->string('phone')->nullable();
            $table->string('email');
            $table->enum('id_type', ['driving_license', 'social_security']);
            $table->string('id_number');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('parent_approval_requests');
    }
};
