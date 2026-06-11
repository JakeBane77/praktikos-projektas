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
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('target_amount');
            $table->unsignedBigInteger('current_amount')->default(0);
            $table->unsignedInteger('production_bonus_percent')->default(0);
            $table->json('stage_percentages');
            $table->json('stage_donor_requirements');
            $table->timestamp('week_starts_at');
            $table->timestamp('week_ends_at');
            $table->enum('status', ['active', 'completed', 'expired'])->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['alliance_id', 'status', 'resource_type']);
            $table->index(['alliance_id', 'week_starts_at']);
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
