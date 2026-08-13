<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_history', function (Blueprint $table) {
            $table->id();
            $table->string('cow_id', 10);
            $table->string('nama', 20);
            $table->decimal('suhu_celsius', 4, 1);
            $table->string('status_suhu', 10);
            $table->unsignedTinyInteger('kadar_oksigen_persen');
            $table->string('status_oksigen', 10);
            $table->decimal('nilai_gerakan', 4, 1);
            $table->string('level_stress', 10);
            $table->dateTime('recorded_at')->useCurrent();

            $table->index(['cow_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_history');
    }
};