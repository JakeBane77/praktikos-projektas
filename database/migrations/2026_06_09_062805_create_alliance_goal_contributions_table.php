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
        Schema::create('alliance_goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alliance_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('resource_type', ['gold', 'wood', 'stone', 'food']);
            $table->unsignedBigInteger('amount');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['alliance_goal_id', 'created_at']);
            $table->index(['alliance_goal_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alliance_goal_contributions');
    }
};
