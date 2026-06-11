<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alliance_goals', function (Blueprint $table) {
            $table->json('stage_donor_requirements')
                ->nullable()
                ->after('stage_percentages');
        });

        DB::table('alliance_goals')
            ->whereNull('stage_donor_requirements')
            ->update([
                'stage_donor_requirements' => json_encode([1, 2, 3, 4, 6, 8], JSON_THROW_ON_ERROR),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alliance_goals', function (Blueprint $table) {
            $table->dropColumn('stage_donor_requirements');
        });
    }
};
