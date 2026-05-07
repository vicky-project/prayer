<?php
namespace Modules\Prayer\Models;

use Illuminate\Database\Eloquent\Model;
use TarfinLabs\LaravelSpatial\Casts\LocationCast;
use TarfinLabs\LaravelSpatial\Traits\HasSpatial;

class City extends Model
{
  use HasSpatial;

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

  protected array $casts = ['coordinates' => LocationCast::class];

  public function prayers() {
    return $this->hasMany(Prayer::class);
  }
}