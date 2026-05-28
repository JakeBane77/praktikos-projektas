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
        Schema::table('minigames', function (Blueprint $table) {
            $table->unsignedBigInteger('resources_gained')->default(0)->after('completions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('minigames', function (Blueprint $table) {
            $table->dropColumn('resources_gained');
        });
    }
};
