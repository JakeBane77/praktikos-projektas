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
        Schema::create('alliance_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alliance_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('resource_type', ['gold', 'wood', 'stone', 'food']);
            $table->unsignedBigInteger('target_amount');
            $table->unsignedBigInteger('current_amount')->default(0);
            $table->unsignedInteger('production_bonus_percent')->default(0);
            $table->unsignedInteger('bonus_duration_hours');
            $table->enum('status', ['active', 'completed', 'expired'])->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['alliance_id', 'status', 'resource_type']);
            $table->index(['status', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alliance_goals');
    }
};
