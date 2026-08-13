<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->string('cow_id', 10);
            $table->string('nama', 20);
            $table->string('aktivitas', 50);
            $table->enum('intensitas', ['rendah', 'sedang', 'tinggi'])->default('sedang');
            $table->dateTime('timestamp')->useCurrent();

            $table->unique('cow_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_aktivitas');
    }
};
