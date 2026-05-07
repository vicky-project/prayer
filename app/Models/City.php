<?php
namespace Modules\Prayer\Models;

use Illuminate\Database\Eloquent\Model;
use Grimzy\LaravelMysqlSpatial\Eloquent\SpatialTrait;

class City extends Model
{
  use SpatialTrait;

  protected $table = "prayer_cities";

  protected $fillable = [
    'city_id',
    'name',
    'slug',
    'province_id',
    'province_name',
    'coordinates',
    'timezone'
  ];

  protected $spatialFields = ['coordinates'];

  public function prayers() {
    return $this->hasMany(Prayer::class);
  }
}