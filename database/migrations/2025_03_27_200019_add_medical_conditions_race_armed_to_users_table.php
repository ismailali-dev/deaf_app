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
            $table->text('medical_conditions')->nullable()->after('gender'); // Comma-separated values
            $table->string('race')->nullable()->after('medical_conditions'); // Free input text
            $table->boolean('armed')->default(false)->after('race'); // Yes/No (true/false)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['medical_conditions', 'race', 'armed']);
        });
    }
};
