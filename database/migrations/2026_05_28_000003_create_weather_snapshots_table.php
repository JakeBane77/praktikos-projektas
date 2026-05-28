<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_snapshots', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 8, 4);
            $table->decimal('longitude', 8, 4);
            $table->unsignedSmallInteger('weather_code')->nullable();
            $table->dateTime('api_time')->nullable();
            $table->timestamps();

            $table->unique(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_snapshots');
    }
};
