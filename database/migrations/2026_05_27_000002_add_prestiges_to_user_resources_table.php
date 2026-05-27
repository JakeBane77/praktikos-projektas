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
        Schema::table('user_resources', function (Blueprint $table) {
            $table->unsignedInteger('prestiges')->default(0)->after('manual_collects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_resources', function (Blueprint $table) {
            $table->dropColumn('prestiges');
        });
    }
};
