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
        Schema::create('resource_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('gold')->default(0);
            $table->unsignedBigInteger('wood')->default(0);
            $table->unsignedBigInteger('stone')->default(0);
            $table->unsignedBigInteger('food')->default(0);
            $table->string('source')->default('manual');
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->index(['user_id', 'source', 'collected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_collections');
    }
};
