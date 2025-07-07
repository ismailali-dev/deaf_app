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
        Schema::table('parent_approval_requests', function (Blueprint $table) {
            $table->boolean('is_approved_by_parent')->default(false)->after('id_number');
        });
    }
    
    public function down()
    {
        Schema::table('parent_approval_requests', function (Blueprint $table) {
            $table->dropColumn('is_approved_by_parent');
        });
    }
};
