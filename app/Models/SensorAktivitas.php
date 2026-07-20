<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorAktivitas extends Model
{
    public $timestamps = false;

    protected $table = 'sensor_aktivitas';

    protected $fillable = [
        'cow_id',
        'nama',
        'aktivitas',
        'intensitas',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];
}
