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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('target_value');
            $table->unsignedInteger('reward_gold')->default(0);
            $table->unsignedInteger('reward_wood')->default(0);
            $table->unsignedInteger('reward_stone')->default(0);
            $table->unsignedInteger('reward_food')->default(0);
            $table->unsignedInteger('production_bonus_percent')->default(5);
            $table->foreignId('bonus_building_type_id')->nullable()->constrained('building_types')->nullOnDelete();
            $table->timestamps();

            $table->unique('name');
            $table->index(['type', 'resource_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
