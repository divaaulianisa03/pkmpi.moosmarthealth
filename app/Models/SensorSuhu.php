<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorSuhu extends Model
{
    // Tabel ini pakai kolom "timestamp" sendiri (bukan created_at/updated_at bawaan Laravel)
    public $timestamps = false;

    protected $table = 'sensor_suhu';

    protected $fillable = [
        'cow_id',
        'nama',
        'suhu_celsius',
        'status',
        'timestamp',
    ];

    protected $casts = [
        'suhu_celsius' => 'float',
        'timestamp' => 'datetime',
    ];
}
