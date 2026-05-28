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
        Schema::table('user_resources', function (Blueprint $table) {
            $table->unsignedBigInteger('wood_minigame_completions')->default(0)->after('prestiges');
            $table->unsignedBigInteger('food_minigame_completions')->default(0)->after('wood_minigame_completions');
            $table->unsignedBigInteger('stone_minigame_completions')->default(0)->after('food_minigame_completions');
            $table->unsignedBigInteger('gold_minigame_completions')->default(0)->after('stone_minigame_completions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_resources', function (Blueprint $table) {
            $table->dropColumn([
                'wood_minigame_completions',
                'food_minigame_completions',
                'stone_minigame_completions',
                'gold_minigame_completions',
            ]);
        });
    }
};
