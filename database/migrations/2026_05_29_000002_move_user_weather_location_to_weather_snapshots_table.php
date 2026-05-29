<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weather_snapshots', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
            $table->dropUnique(['latitude', 'longitude']);
            $table->unique('user_id');
            $table->index(['latitude', 'longitude']);
        });

        if (
            Schema::hasColumn('users', 'weather_latitude')
            && Schema::hasColumn('users', 'weather_longitude')
        ) {
            DB::table('users')
                ->whereNotNull('weather_latitude')
                ->whereNotNull('weather_longitude')
                ->get(['id', 'weather_latitude', 'weather_longitude', 'weather_location_updated_at'])
                ->each(function (object $user): void {
                    DB::table('weather_snapshots')->insert([
                        'user_id' => $user->id,
                        'latitude' => $user->weather_latitude,
                        'longitude' => $user->weather_longitude,
                        'weather_code' => null,
                        'api_time' => null,
                        'created_at' => $user->weather_location_updated_at ?? now(),
                        'updated_at' => $user->weather_location_updated_at ?? now(),
                    ]);
                });

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn([
                    'weather_latitude',
                    'weather_longitude',
                    'weather_location_updated_at',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'weather_latitude')) {
                $table->decimal('weather_latitude', 8, 4)->nullable()->after('remember_token');
            }

            if (! Schema::hasColumn('users', 'weather_longitude')) {
                $table->decimal('weather_longitude', 8, 4)->nullable()->after('weather_latitude');
            }

            if (! Schema::hasColumn('users', 'weather_location_updated_at')) {
                $table->timestamp('weather_location_updated_at')->nullable()->after('weather_longitude');
            }
        });

        DB::table('weather_snapshots')
            ->whereNotNull('user_id')
            ->get(['user_id', 'latitude', 'longitude', 'updated_at'])
            ->each(function (object $snapshot): void {
                DB::table('users')
                    ->where('id', $snapshot->user_id)
                    ->update([
                        'weather_latitude' => $snapshot->latitude,
                        'weather_longitude' => $snapshot->longitude,
                        'weather_location_updated_at' => $snapshot->updated_at,
                    ]);
            });

        DB::table('weather_snapshots')
            ->whereNotNull('user_id')
            ->delete();

        Schema::table('weather_snapshots', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropUnique(['user_id']);
            $table->unique(['latitude', 'longitude']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
