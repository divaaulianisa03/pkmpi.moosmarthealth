<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorKadarOksigen extends Model
{
    public $timestamps = false;

    protected $table = 'sensor_kadar_oksigen';

    protected $fillable = [
        'cow_id',
        'nama',
        'kadar_oksigen_persen',
        'status',
        'timestamp',
    ];

    protected $casts = [
        'kadar_oksigen_persen' => 'integer',
        'timestamp' => 'datetime',
    ];
}
