<?php
namespace Modules\Prayer\Services;

use Modules\Prayer\Models\City;
use Modules\Prayer\Models\Prayer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class PrayerTimeService
{
  /**
  * Mendapatkan jadwal shalat berdasarkan kota atau koordinat
  */
  public function getPrayerTimes(
    $latitude = null,
    $longitude = null,
    $city = null,
    $telegramUser = null
  ): array
  {
    // Jika ada telegramUser dan memiliki default location, gunakan itu
    if ($telegramUser && isset($telegramUser->data['default_location'])) {
      $default = $telegramUser->data['default_location'];
      if (isset($default['city']) && !empty($default['city'])) {
        $city = $default['city'];
        $latitude = null;
        $longitude = null;
      } elseif (isset($default['latitude'], $default['longitude'])) {
        $latitude = $default['latitude'];
        $longitude = $default['longitude'];
        $city = null;
      }
    }

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
    $timezone = $cityModel->timezone ?? config("app.timezone");
    $today = Carbon::now($timezone)->toDateString();
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
      'city' => $prayer->city->name,
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
  * Mendapatkan jadwal untuk hari ini berdasarkan lokasi default (digunakan oleh notifikasi)
  */
  public function getTodayPrayerByLocation($defaultLocation) {
    $cityModel = null;
    if (!empty($defaultLocation['city'])) {
      $cityModel = $this->findCityByName($defaultLocation['city']);
    } elseif (!empty($defaultLocation['latitude']) && !empty($defaultLocation['longitude'])) {
      $cityModel = $this->findNearestCity($defaultLocation['latitude'], $defaultLocation['longitude']);
    }

    if (!$cityModel) {
      return null;
    }

    $timezone = $cityModel->timezone ?? config('app.timezone');
    $today = Carbon::now($timezone)->toDateString();

    $prayer = Prayer::where('city_id', $cityModel->id)
    ->where('date', $today)
    ->first();

    if (!$prayer) {
      return null;
    }

    return [
      'city_name' => $cityModel->name,
      'timezone' => $timezone,
      'jadwal' => [
        'imsak' => $prayer->imsak,
        'subuh' => $prayer->subuh,
        'terbit' => $prayer->terbit,
        'dhuha' => $prayer->dhuha,
        'dzuhur' => $prayer->dzuhur,
        'ashar' => $prayer->ashar,
        'maghrib' => $prayer->maghrib,
        'isya' => $prayer->isya,
      ]
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
  protected function findNearestCity($lat, $lon): ?City
  {
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
  protected function haversine(
    $lat1,
    $lon1,
    $lat2,
    $lon2
  ) {
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
    ->orderBy('province_name')
    ->get()
    ->unique("province_name");
  }

  public function getCitiesByProvinceId(int $id): Collection
  {
    $cities = $this->getCityById($id);

    return City::where("province_id", $cities
      ->province_id)
    ->distinct()
    ->orderBy("name")
    ->get();
  }

  public function getCityById(int $id):City
  {
    return City::findOrFail($id);
  }

  protected function getTimezoneFromCoordinates($lat, $lon): ?string
  {
    // 1. Coba IPGeolocation API
    $apiKey = config("prayer.ipgeolocation.api_key");
    if ($apiKey) {
      try {
        $response = Http::timeout(5)->get('https://api.ipgeolocation.io/timezone', [
          'lat' => $lat,
          'lon' => $lon,
          'apiKey' => config('prayer.ipgeolocation.api_key')
        ]);

        if ($response->successful()) {
          $data = $response->json();
          return $data['timezone'] ?? null;
        }
      } catch (\Exception $e) {
        Log::warning('IPGeolocation API error: ' . $e->getMessage());
      }
    }

    // 2. Fallback ke RTZ server
    try {
      $response = Http::timeout(5)->get("http://tz.twitchax.com/api/v1/ned/tz/{$lon}/{$lat}");
      if ($response->successful()) {
        $data = $response->json();
        return $data['identifier'] ?? null;
      }
    } catch (\Exception $e) {
      Log::warning('RTZ server error: ' . $e->getMessage());
    }

    // 3. Fallback ke timezone server (Asia/Jakarta)
    return config("app.timezone", 'Asia/Jakarta');
  }
}