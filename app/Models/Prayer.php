<?php
namespace Modules\Prayer\Models;

use Illuminate\Database\Eloquent\Model;

class Prayer extends Model
{
  protected $fillable = [
    'prayer_id',
    'city_id',
    'date',
    'imsak',
    'subuh',
    'terbit',
    'dhuha',
    'dzuhur',
    'ashar',
    'maghrib',
    'isya'
  ];

  protected $casts = [
    'date' => 'date',
  ];
}