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
        Schema::table('parent_approval_requests', function (Blueprint $table) {
            $table->string('ssn_number')->after('id_number');
            $table->string('parent_id_doc')->after('ssn_number');
        });
    }

    public function down(): void
    {
        Schema::table('parent_approval_requests', function (Blueprint $table) {
            $table->dropColumn(['ssn_number', 'parent_id_doc']);
        });
    }
};
