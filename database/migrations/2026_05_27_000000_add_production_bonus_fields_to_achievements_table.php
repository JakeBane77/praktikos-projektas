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
        Schema::table('achievements', function (Blueprint $table) {
            $table->unsignedInteger('production_bonus_percent')->default(5)->after('reward_food');
            $table->foreignId('bonus_building_type_id')
                ->nullable()
                ->after('production_bonus_percent')
                ->constrained('building_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bonus_building_type_id');
            $table->dropColumn('production_bonus_percent');
        });
    }
};
