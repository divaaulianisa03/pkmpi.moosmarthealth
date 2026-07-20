<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorHistory extends Model
{
    public $timestamps = false;

    protected $table = 'sensor_history';

    protected $fillable = [
        'cow_id',
        'nama',
        'suhu_celsius',
        'status_suhu',
        'kadar_oksigen_persen',
        'status_oksigen',
        'nilai_gerakan',
        'level_stress',
        'recorded_at',
    ];

    protected $casts = [
        'suhu_celsius' => 'float',
        'kadar_oksigen_persen' => 'integer',
        'nilai_gerakan' => 'float',
        'recorded_at' => 'datetime',
    ];
}