<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuelStationTime extends Model
{
    use HasFactory;


    protected $fillable = [
        "day",
        "opening_time",
        "closing_time",
        "fuel_station_id",
        "is_closed"
    ];

    protected  $casts = [
        'opening_time' => 'datetime:H:i',
        'closing_time' => 'datetime:H:i',
    ];


}
