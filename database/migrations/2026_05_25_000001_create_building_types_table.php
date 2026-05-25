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
        Schema::create('building_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('produces_resource')->nullable();
            $table->unsignedInteger('base_production_per_minute')->default(0);
            $table->decimal('production_multiplier', 8, 2)->nullable();
            $table->string('effect_type')->nullable();
            $table->json('effects')->nullable();
            $table->json('base_costs');
            $table->decimal('upgrade_cost_multiplier', 8, 2)->default(1.25);
            $table->unsignedInteger('max_level')->nullable();
            $table->timestamps();

            $table->unique('name');
            $table->index('produces_resource');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_types');
    }
};
