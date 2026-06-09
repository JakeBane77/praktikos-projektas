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
        Schema::create('alliance_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alliance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['leader', 'officer', 'member'])->default('member');
            $table->unsignedBigInteger('total_contributed')->default(0);
            $table->timestamp('joined_at')->useCurrent();

            $table->unique('user_id');
            $table->index(['alliance_id', 'role']);
            $table->index(['alliance_id', 'total_contributed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alliance_user');
    }
};
