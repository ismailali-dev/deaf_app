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
        Schema::table('audio_files', function (Blueprint $table) {
            $table->json('features')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('audio_files', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
