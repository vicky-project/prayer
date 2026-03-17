<?php
namespace Modules\Prayer\Services;

use Modules\Prayer\Models\City;
use Modules\Prayer\Models\Prayer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class PrayerTimeService
{
  /**
  * Mendapatkan jadwal shalat berdasarkan kota atau koordinat
  */
  public function getPrayerTimes($latitude = null, $longitude = null, $city = null) {
    $cityModel = null;

    // 1. Cari berdasarkan nama kota jika ada
    if ($city) {
      $cityModel = $this->findCityByName($city);
    }
    // 2. Cari berdasarkan koordinat jika nama kota tidak ada
    elseif ($latitude && $longitude) {
      $cityModel = $this->findNearestCity($latitude, $longitude);
    } else {
      throw new \Exception('Parameter tidak lengkap. Kirimkan nama kota atau koordinat.');
    }

    if (!$cityModel) {
      throw new \Exception('Kota tidak ditemukan dalam database.');
    }

    // 3. Ambil jadwal untuk hari ini
    $today = Carbon::today();
    $today->tz = config("prayer.timezone");
    $today = $today->toDateString();
    $prayer = Prayer::where('city_id', $cityModel->id)
    ->where('date', $today)
    ->first();

    // Jika tidak ada untuk hari ini, ambil data pertama (fallback)
    if (!$prayer) {
      $prayer = Prayer::where('city_id', $cityModel->id)->first();
    }

    if (!$prayer) {
      throw new \Exception('Jadwal shalat tidak ditemukan untuk kota ini.');
    }

    return [
      'date' => $prayer->date->format("d-m-Y"),
      'latitude' => $cityModel->latitude,
      'longitude' => $cityModel->longitude,
      'jadwal' => [
        'imsak' => $prayer->imsak,
        'subuh' => $prayer->subuh,
        'terbit' => $prayer->terbit,
        'dhuha' => $prayer->dhuha,
        'dzuhur' => $prayer->dzuhur,
        'ashar' => $prayer->ashar,
        'maghrib' => $prayer->maghrib,
        'isya' => $prayer->isya,
      ],
      'metode' => 'Kemenag',
    ];
  }

  /**
  * Cari kota berdasarkan nama (case insensitive)
  */
  protected function findCityByName($name): City
  {
    // Coba exact match
    $city = City::whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->first();

    // Jika tidak, coba partial match
    if (!$city) {
      $city = City::where('name', 'LIKE', '%' . $name . '%')->first();
    }

    return $city;
  }

  /**
  * Cari kota terdekat berdasarkan koordinat (rumus haversine)
  */
  protected function findNearestCity($lat, $lon) {
    // Ambil semua kota yang memiliki koordinat
    $cities = City::whereNotNull('latitude')->whereNotNull('longitude')->get();

    $nearest = null;
    $minDistance = PHP_INT_MAX;

    foreach ($cities as $city) {
      $distance = $this->haversine($lat, $lon, $city->latitude, $city->longitude);
      if ($distance < $minDistance) {
        $minDistance = $distance;
        $nearest = $city;
      }
    }

    return $nearest;
  }

  /**
  * Rumus haversine (jarak dalam km)
  */
  protected function haversine($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
  }

  /**
  * Mendapatkan daftar semua provinsi yang tersedia
  * @return array
  */
  public function getProvinces(): Collection
  {
    return City::whereNotNull('province_name')
    ->distinct()
    ->unique("province_name")
    ->orderBy('province_name')
    ->get();
  }
}