<?php
namespace Modules\Prayer\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
  protected $table = "prayer_cities";

  protected $fillable = [
    'city_id',
    'name',
    'slug',
    'province_id',
    'province_name',
    'latitude',
    'longitude',
    'timezone'
  ];

  protected $casts = [
    'latitude' => 'float',
    'longitude' => 'float',
  ];

  public function prayers() {
    return $this->hasMany(Prayer::class);
  }
}