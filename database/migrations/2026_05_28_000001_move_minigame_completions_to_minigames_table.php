<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RESOURCES = ['wood', 'food', 'stone', 'gold'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('minigames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('resource');
            $table->unsignedBigInteger('completions')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'resource']);
            $table->index('resource');
        });

        if (Schema::hasColumn('user_resources', 'wood_minigame_completions')) {
            $now = now();

            DB::table('user_resources')
                ->select([
                    'user_id',
                    'wood_minigame_completions',
                    'food_minigame_completions',
                    'stone_minigame_completions',
                    'gold_minigame_completions',
                ])
                ->orderBy('id')
                ->each(function (object $resources) use ($now): void {
                    foreach (self::RESOURCES as $resource) {
                        DB::table('minigames')->insert([
                            'user_id' => $resources->user_id,
                            'resource' => $resource,
                            'completions' => $resources->{$resource.'_minigame_completions'},
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                });

            Schema::table('user_resources', function (Blueprint $table) {
                $table->dropColumn([
                    'wood_minigame_completions',
                    'food_minigame_completions',
                    'stone_minigame_completions',
                    'gold_minigame_completions',
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_resources', function (Blueprint $table) {
            $table->unsignedBigInteger('wood_minigame_completions')->default(0)->after('prestiges');
            $table->unsignedBigInteger('food_minigame_completions')->default(0)->after('wood_minigame_completions');
            $table->unsignedBigInteger('stone_minigame_completions')->default(0)->after('food_minigame_completions');
            $table->unsignedBigInteger('gold_minigame_completions')->default(0)->after('stone_minigame_completions');
        });

        DB::table('minigames')
            ->orderBy('id')
            ->each(function (object $minigame): void {
                if (! in_array($minigame->resource, self::RESOURCES, true)) {
                    return;
                }

                DB::table('user_resources')
                    ->where('user_id', $minigame->user_id)
                    ->update([
                        $minigame->resource.'_minigame_completions' => $minigame->completions,
                    ]);
            });

        Schema::dropIfExists('minigames');
    }
};
