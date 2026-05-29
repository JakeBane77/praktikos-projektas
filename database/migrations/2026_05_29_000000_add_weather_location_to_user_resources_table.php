<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_resources', function (Blueprint $table) {
            $table->decimal('weather_latitude', 8, 4)->nullable()->after('prestiges');
            $table->decimal('weather_longitude', 8, 4)->nullable()->after('weather_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('user_resources', function (Blueprint $table) {
            $table->dropColumn(['weather_latitude', 'weather_longitude']);
        });
    }
};
