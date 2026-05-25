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
        Schema::create('user_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('gold')->default(0);
            $table->unsignedBigInteger('wood')->default(0);
            $table->unsignedBigInteger('stone')->default(0);
            $table->unsignedBigInteger('food')->default(0);
            $table->unsignedBigInteger('lifetime_gold')->default(0);
            $table->unsignedBigInteger('lifetime_wood')->default(0);
            $table->unsignedBigInteger('lifetime_stone')->default(0);
            $table->unsignedBigInteger('lifetime_food')->default(0);
            $table->timestamp('last_produced_at')->nullable();
            $table->timestamp('last_collected_at')->nullable();
            $table->unsignedInteger('manual_collects_today')->default(0);
            $table->date('manual_collects_reset_date')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_resources');
    }
};
