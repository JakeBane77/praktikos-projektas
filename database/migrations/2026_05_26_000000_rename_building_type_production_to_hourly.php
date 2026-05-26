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
        if (Schema::hasColumn('building_types', 'base_production_per_minute')) {
            Schema::table('building_types', function (Blueprint $table) {
                $table->renameColumn('base_production_per_minute', 'base_production_per_hour');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('building_types', 'base_production_per_hour')) {
            Schema::table('building_types', function (Blueprint $table) {
                $table->renameColumn('base_production_per_hour', 'base_production_per_minute');
            });
        }
    }
};
