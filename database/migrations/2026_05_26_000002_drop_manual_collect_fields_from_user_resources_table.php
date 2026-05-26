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
            if (Schema::hasColumn('user_resources', 'manual_collects_today')) {
                $table->dropColumn('manual_collects_today');
            }

            if (Schema::hasColumn('user_resources', 'manual_collects_reset_date')) {
                $table->dropColumn('manual_collects_reset_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_resources', function (Blueprint $table) {
            if (! Schema::hasColumn('user_resources', 'manual_collects_today')) {
                $table->unsignedInteger('manual_collects_today')->default(0);
            }

            if (! Schema::hasColumn('user_resources', 'manual_collects_reset_date')) {
                $table->date('manual_collects_reset_date')->nullable();
            }
        });
    }
};
