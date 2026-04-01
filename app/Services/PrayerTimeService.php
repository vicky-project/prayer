<?php
namespace Modules\Prayer\Services;

use Modules\Prayer\Models\City;
use Modules\Prayer\Models\Prayer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use DateTime;
use DateTimeZone;

class PrayerTimeService
{
  /**
  * Durasi cache jadwal shalat (sampai akhir hari di timezone masing-masing)
  */
  protected function getTtlUntilEndOfDay($timezone): int
  {
    $now = Carbon::now($timezone);
    $endOfDay = $now->copy()->endOfDay();
    return $now->diffInSeconds($endOfDay);
  }

  /**
  * Mendapatkan offset timezone dalam menit
  */
  protected function getTimezoneOffset($timezone): ?int
  {
    if (!$timezone) return null;
    try {
      $tz = new DateTimeZone($timezone);
      $now = new DateTime('now', $tz);
      $offsetSeconds = $tz->getOffset($now);
      return $offsetSeconds / 60;
    } catch (\Exception $e) {
      Log::warning('Gagal mendapatkan offset timezone: ' . $e->getMessage());
      return null;
    }
  }

  /**
  * Mendapatkan jadwal shalat berdasarkan kota atau koordinat (dengan cache)
  */
  public function getPrayerTimes(
    $latitude = null,
    $longitude = null,
    $city = null,
    $telegramUser = null
  ): array {
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
      // Jika tidak ditemukan, coba geocoding untuk mendapatkan koordinat lalu cari kota terdekat
      if (!$cityModel) {
        $coords = $this->geocodeCity($city);
        if ($coords) {
          $cityModel = $this->findNearestCity($coords['lat'], $coords['lon']);
          if ($cityModel) {
            Log::info("Kota '{$city}' tidak ditemukan, menggunakan kota terdekat: {$cityModel->name} berdasarkan geocoding.");
          }
        }
      }
      // Jika masih belum ditemukan dan ada koordinat, coba kota terdekat langsung
      if (!$cityModel && $latitude && $longitude) {
        Log::notice("Kota '{$city}' tidak ditemukan, mencari kota terdekat dari koordinat yang diberikan.");
        $cityModel = $this->findNearestCity($latitude, $longitude);
      }
    }
    // 2. Cari berdasarkan koordinat jika nama kota tidak ada atau tidak ditemukan
    elseif ($latitude && $longitude) {
      $cityModel = $this->findNearestCity($latitude, $longitude);
    } else {
      throw new \Exception('Parameter tidak lengkap. Kirimkan nama kota atau koordinat.');
    }

    if (!$cityModel) {
      throw new \Exception('Kota tidak ditemukan dalam database.');
    }

    // 3. Ambil jadwal untuk hari ini (dengan cache)
    $timezone = $cityModel->timezone ?? config("app.timezone");
    $today = Carbon::now($timezone)->toDateString();
    $cacheKey = config("prayer.cache_prefix.prayer_times") . ":{$cityModel->id}:{$today}";
    $ttl = $this->getTtlUntilEndOfDay($timezone);

    $prayer = Cache::remember($cacheKey, $ttl, function () use ($cityModel, $today) {
      return Prayer::where('city_id', $cityModel->id)
      ->where('date', $today)
      ->first();
    });

    // Jika tidak ada untuk hari ini, ambil data pertama (fallback) - tidak di-cache karena jarang
    if (!$prayer) {
      $prayer = Prayer::where('city_id', $cityModel->id)->first();
    }

    if (!$prayer) {
      throw new \Exception('Jadwal shalat tidak ditemukan untuk kota ini.');
    }

    $timezoneOffset = $this->getTimezoneOffset($cityModel->timezone);

    return [
      'date' => $prayer->date->format("d-m-Y"),
      'city' => $prayer->city->name,
      'latitude' => $cityModel->latitude,
      'longitude' => $cityModel->longitude,
      "timezone_offset" => $timezoneOffset,
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
  * Dengan caching per kota per hari.
  */
  public function getTodayPrayerByLocation($defaultLocation): ?array
  {
    $cityModel = $this->resolveCityFromLocation($defaultLocation);
    if (!$cityModel) {
      return null;
    }

    $timezone = $cityModel->timezone ?? config('app.timezone');
    $today = Carbon::now($timezone)->toDateString();
    $cacheKey = config("prayer.cache_prefix.prayer_times") . ":{$cityModel->id}:{$today}";
    $ttl = $this->getTtlUntilEndOfDay($timezone);

    $prayer = Cache::remember($cacheKey, $ttl, function () use ($cityModel, $today) {
      return Prayer::where('city_id', $cityModel->id)
      ->where('date', $today)
      ->first();
    });

    if (!$prayer) {
      return null;
    }

    $timezoneOffset = $this->getTimezoneOffset($cityModel->timezone);

    return [
      'date' => $prayer->date->format("d-m-Y"),
      'city' => $cityModel->name,
      'latitude' => $cityModel->latitude,
      'longitude' => $cityModel->longitude,
      'timezone' => $timezone,
      "timezone_offset" => $timezoneOffset,
      'jadwal' => [
        'imsak' => $prayer->imsak,
        'subuh' => $prayer->subuh,
        'dzuhur' => $prayer->dzuhur,
        'ashar' => $prayer->ashar,
        'maghrib' => $prayer->maghrib,
        'isya' => $prayer->isya,
      ]
    ];
  }

  /**
  * Helper: Mendapatkan model City dari defaultLocation (dengan caching optional)
  */
  protected function resolveCityFromLocation($defaultLocation): ?City
  {
    if (empty($defaultLocation)) {
      return null;
    }

    // Coba berdasarkan nama kota
    if (!empty($defaultLocation['city'])) {
      $city = $this->findCityByName($defaultLocation['city']);
      if ($city) {
        return $city;
      }
      // Jika tidak ditemukan, coba geocoding
      $coords = $this->geocodeCity($defaultLocation['city']);
      if ($coords) {
        $city = $this->findNearestCity($coords['lat'], $coords['lon']);
        if ($city) {
          Log::info("Kota '{$defaultLocation['city']}' tidak ditemukan, menggunakan kota terdekat: {$city->name}.");
          return $city;
        }
      }
    }

    // Coba berdasarkan koordinat
    if (!empty($defaultLocation['latitude']) && !empty($defaultLocation['longitude'])) {
      return $this->findNearestCity($defaultLocation['latitude'], $defaultLocation['longitude']);
    }

    return null;
  }

  /**
  * Cari kota berdasarkan nama (case insensitive) – tidak perlu cache karena data jarang berubah
  */
  protected function findCityByName($name): ?City
  {
    $city = City::whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->first();
    if (!$city) {
      $city = City::where('name', 'LIKE', '%' . $name . '%')->first();
    }
    return $city;
  }

  /**
  * Cari kota terdekat berdasarkan koordinat (dengan cache)
  */
  protected function findNearestCity($lat, $lon): ?City
  {
    // Bulatkan koordinat agar cache lebih efektif (misal 2 desimal)
    $roundedLat = round($lat, 2);
    $roundedLon = round($lon, 2);
    $cacheKey = config("prayer.cache_prefix.city") . ":{$roundedLat}:{$roundedLon}";

    return Cache::remember($cacheKey, 86400, function () use ($lat, $lon) {
      $cities = City::whereNotNull('latitude')
      ->whereNotNull('longitude')
      ->get();

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
    });
  }

  /**
  * Rumus haversine (jarak dalam km)
  */
  protected function haversine($lat1,
    $lon1,
    $lat2,
    $lon2) {
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a),
      sqrt(1-$a));
    return $earthRadius * $c;
  }

  /**
  * Mendapatkan koordinat dari nama kota menggunakan geocoding (Nominatim) dengan cache
  */
  protected function geocodeCity($cityName): ?array
  {
    $cacheKey = config('prayer.cache_prefix.geocode') . md5($cityName);
    return Cache::remember($cacheKey,
      86400,
      function () use ($cityName) {
        try {
          $response = Http::timeout(5)->get('https://nominatim.openstreetmap.org/search', [
            'q' => $cityName,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 0,
          ]);
          if ($response->successful()) {
            $data = $response->json();
            if (!empty($data)) {
              return [
                'lat' => (float) $data[0]['lat'],
                'lon' => (float) $data[0]['lon'],
              ];
            }
          }
        } catch (\Exception $e) {
          Log::error('Geocoding error: ' . $e->getMessage());
        }
        return null;
      });
  }

  /**
  * Mendapatkan daftar semua provinsi yang tersedia
  */
  public function getProvinces(): Collection
  {
    return City::whereNotNull('province_name')
    ->distinct()
    ->orderBy('province_name')
    ->get()
    ->unique("province_name");
  }

  /**
  * Mendapatkan kota berdasarkan ID
  */
  public function getCityById(int $id): City
  {
    return City::findOrFail($id);
  }

  /**
  * Mendapatkan daftar kota berdasarkan provinsi
  */
  public function getCitiesByProvinceId(int $id): Collection
  {
    $city = $this->getCityById($id);
    return City::where("province_id",
      $city->province_id)
    ->distinct()
    ->orderBy("name")
    ->get();
  }

  /**
  * Mendapatkan timezone dari koordinat (dengan cache)
  */
  public function getTimezoneFromCoordinates($lat,
    $lon): ?string
  {
    $cacheKey = config("prayer.cache_prefix.timezone") . ":{$lat}:{$lon}";
    return Cache::remember($cacheKey,
      86400,
      function () use ($lat, $lon) {
        // 1. Coba IPGeolocation API
        $apiKey = config("prayer.ipgeolocation.api_key");
        if ($apiKey) {
          try {
            $response = Http::timeout(5)->get('https://api.ipgeolocation.io/timezone', [
              'lat' => $lat,
              'lon' => $lon,
              'apiKey' => $apiKey
            ]);

            if ($response->successful()) {
              $data = $response->json();
              if (!empty($data['timezone'])) {
                return $data['timezone'];
              }
            }
            Log::warning('IPGeolocation error: ' . ($response->json()['message'] ?? 'Unknown'));
          } catch (\Exception $e) {
            Log::error('IPGeolocation API error: ' . $e->getMessage());
          }
        }

        // 2. Fallback ke RTZ server
        try {
          $response = Http::timeout(5)->get("http://tz.twitchax.com/api/v1/ned/tz/{$lon}/{$lat}");
          if ($response->successful()) {
            $data = $response->json();
            if (isset($data[0]['identifier'])) {
              return $data[0]['identifier'];
            }
          }
        } catch (\Exception $e) {
          Log::warning('RTZ server error: ' . $e->getMessage());
        }

        // 3. Fallback ke timezone server (Asia/Jakarta)
        return config("app.timezone", 'Asia/Jakarta');
      });
  }

  /**
  * Hapus cache jadwal shalat untuk kota dan tanggal tertentu
  * Panggil method ini saat data prayer diupdate (misal dari event model)
  */
  public function clearPrayerCache(int $cityId,
    string $date): void
  {
    $cacheKey = config("prayer.cache_prefix.prayer_times") . ":{$cityId}:{$date}";
    Cache::forget($cacheKey);
  }

  /**
  * Hapus cache kota terdekat untuk koordinat tertentu
  */
  public function clearNearestCityCache(float $lat,
    float $lon): void
  {
    $roundedLat = round($lat,
      2);
    $roundedLon = round($lon,
      2);
    Cache::forget(config("prayer.cache_prefix.city") . ":{$roundedLat}:{$roundedLon}");
  }
}