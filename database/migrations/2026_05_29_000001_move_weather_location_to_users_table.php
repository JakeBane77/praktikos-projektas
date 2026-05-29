<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('weather_latitude', 8, 4)->nullable()->after('remember_token');
            $table->decimal('weather_longitude', 8, 4)->nullable()->after('weather_latitude');
            $table->timestamp('weather_location_updated_at')->nullable()->after('weather_longitude');
        });

        if (
            Schema::hasColumn('user_resources', 'weather_latitude')
            && Schema::hasColumn('user_resources', 'weather_longitude')
        ) {
            DB::table('user_resources')
                ->whereNotNull('user_resources.weather_latitude')
                ->whereNotNull('user_resources.weather_longitude')
                ->get(['user_id', 'weather_latitude', 'weather_longitude', 'updated_at'])
                ->each(function (object $resources): void {
                    DB::table('users')
                        ->where('id', $resources->user_id)
                        ->update([
                            'weather_latitude' => $resources->weather_latitude,
                            'weather_longitude' => $resources->weather_longitude,
                            'weather_location_updated_at' => $resources->updated_at,
                        ]);
                });

            Schema::table('user_resources', function (Blueprint $table) {
                $table->dropColumn(['weather_latitude', 'weather_longitude']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_resources', function (Blueprint $table) {
            if (! Schema::hasColumn('user_resources', 'weather_latitude')) {
                $table->decimal('weather_latitude', 8, 4)->nullable()->after('prestiges');
            }

            if (! Schema::hasColumn('user_resources', 'weather_longitude')) {
                $table->decimal('weather_longitude', 8, 4)->nullable()->after('weather_latitude');
            }
        });

        DB::table('users')
            ->whereNotNull('weather_latitude')
            ->whereNotNull('weather_longitude')
            ->get(['id', 'weather_latitude', 'weather_longitude'])
            ->each(function (object $user): void {
                DB::table('user_resources')
                    ->where('user_id', $user->id)
                    ->update([
                        'weather_latitude' => $user->weather_latitude,
                        'weather_longitude' => $user->weather_longitude,
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
};
