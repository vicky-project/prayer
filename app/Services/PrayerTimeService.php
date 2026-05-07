<?php

namespace Modules\Prayer\Services;

use Modules\Prayer\Models\City;
use Modules\Prayer\Models\Prayer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use TarfinLabs\LaravelSpatial\Types\Point;

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
      $tz = new \DateTimeZone($timezone);
      $now = new \DateTime('now', $tz);
      $offsetSeconds = $tz->getOffset($now);
      return $offsetSeconds / 60;
    } catch (\Exception $e) {
      Log::warning('Gagal mendapatkan offset timezone: ' . $e->getMessage());
      return null;
    }
  }

  /**
  * Mencari kota terdekat dari koordinat menggunakan indeks spasial
  * @param float $latitude
  * @param float $longitude
  * @return City|null
  */
  protected function findNearestCity($latitude, $longitude): ?City
  {
    $roundedLat = round($latitude, 2);
    $roundedLon = round($longitude, 2);
    $cacheKey = config("prayer.cache_prefix.city") . ":{$roundedLat}:{$roundedLon}";

    return Cache::remember($cacheKey, 86400, function () use ($latitude, $longitude) {
      $point = new Point(lat: $latitude, lng: $longitude);
      // distanceSphere() menambahkan kolom 'distance' dalam meter
      return City::whereNotNull('coordinates')
      ->withinDistanceTo('coordinates', $point, 200000) // radius 200 km
      ->orderByDistanceTo('coordinates', $point)
      ->first();
    });
  }

  /**
  * Mendapatkan model City dari defaultLocation (array bisa berisi city atau lat/lon)
  */
  protected function resolveCityFromLocation($defaultLocation): ?City
  {
    if (empty($defaultLocation)) {
      return null;
    }

    if (!empty($defaultLocation['city'])) {
      $city = $this->findCityByName($defaultLocation['city']);
      if ($city) {
        return $city;
      }
      $coords = $this->geocodeCity($defaultLocation['city']);
      if ($coords) {
        return $this->findNearestCity($coords['lat'], $coords['lon']);
      }
    }

    if (!empty($defaultLocation['latitude']) && !empty($defaultLocation['longitude'])) {
      return $this->findNearestCity($defaultLocation['latitude'], $defaultLocation['longitude']);
    }

    return null;
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
    // Prioritaskan default_location dari user jika ada
    if ($telegramUser && isset($telegramUser->data['default_location'])) {
      $default = $telegramUser->data['default_location'];
      if (isset($default['city']) && !empty($default['city'])) {
        $city = $default['city'];
        $latitude = $longitude = null;
      } elseif (isset($default['latitude'], $default['longitude'])) {
        $latitude = $default['latitude'];
        $longitude = $default['longitude'];
        $city = null;
      }
    }

    $cityModel = null;

    if ($city) {
      $cityModel = $this->findCityByName($city);
      if (!$cityModel) {
        $coords = $this->geocodeCity($city);
        if ($coords) {
          $cityModel = $this->findNearestCity($coords['lat'], $coords['lon']);
          if ($cityModel) {
            Log::info("Kota '{$city}' tidak ditemukan, menggunakan kota terdekat: {$cityModel->name} berdasarkan geocoding.");
          }
        }
      }
      if (!$cityModel && $latitude && $longitude) {
        Log::notice("Kota '{$city}' tidak ditemukan, mencari kota terdekat dari koordinat yang diberikan.");
        $cityModel = $this->findNearestCity($latitude, $longitude);
      }
    } elseif ($latitude && $longitude) {
      $cityModel = $this->findNearestCity($latitude, $longitude);
    } else {
      throw new \Exception('Parameter tidak lengkap. Kirimkan nama kota atau koordinat.');
    }

    if (!$cityModel) {
      throw new \Exception('Kota tidak ditemukan dalam database.');
    }

    $timezone = $cityModel->timezone ?? config("app.timezone");
    $today = Carbon::now($timezone)->toDateString();
    $cacheKey = config("prayer.cache_prefix.prayer_times") . ":{$cityModel->id}:{$today}";
    $ttl = $this->getTtlUntilEndOfDay($timezone);

    $prayer = Cache::remember($cacheKey, $ttl, function () use ($cityModel, $today) {
      return Prayer::where('city_id', $cityModel->id)
      ->where('date', $today)
      ->first();
    });

    if (!$prayer) {
      $prayer = Prayer::where('city_id', $cityModel->id)->first();
    }

    if (!$prayer) {
      throw new \Exception('Jadwal shalat tidak ditemukan untuk kota ini.');
    }

    $coordinates = $cityModel->coordinates;
    $lat = $coordinates ? $coordinates->getLat() : null;
    $lng = $coordinates ? $coordinates->getLng() : null;
    $timezoneOffset = $this->getTimezoneOffset($cityModel->timezone);

    return [
      'date' => $prayer->date->format("d-m-Y"),
      'hijri' => $prayer->date->toHijri()->toDateString(),
      'is_ramadhan' => $prayer->date->toHijri()->month === 9,
      'city' => $prayer->city->name,
      'latitude' => $lat,
      'longitude' => $lng,
      'timezone_offset' => $timezoneOffset,
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

    $coordinates = $cityModel->coordinates;
    $lat = $coordinates ? $coordinates->getLat() : null;
    $lng = $coordinates ? $coordinates->getLng() : null;

    return [
      'date' => $prayer->date->format("d-m-Y"),
      'city' => $cityModel->name,
      'latitude' => $lat,
      'longitude' => $lng,
      'timezone' => $timezone,
      'timezone_offset' => $this->getTimezoneOffset($timezone),
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
  * Cari kota berdasarkan nama (case insensitive)
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
  * Mendapatkan koordinat dari nama kota menggunakan geocoding (Nominatim) dengan cache
  */
  protected function geocodeCity($cityName): ?array
  {
    $cacheKey = config('prayer.cache_prefix.geocode') . md5($cityName);
    return Cache::remember($cacheKey, 86400, function () use ($cityName) {
      try {
        $response = Http::withHeaders(['User-Agent' => 'PrayerApp/1.0'])
        ->timeout(5)
        ->get('https://nominatim.openstreetmap.org/search', [
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
  * Mendapatkan timezone dari koordinat (dengan cache)
  */
  public function getTimezoneFromCoordinates($lat,
    $lon): ?string
  {
    $cacheKey = config("prayer.cache_prefix.timezone") . ":{$lat}:{$lon}";
    return Cache::remember($cacheKey,
      86400,
      function () use ($lat, $lon) {
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
        // Fallback ke RTZ server
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
        return config("app.timezone", 'Asia/Jakarta');
      });
  }

  /**
  * Hapus cache jadwal shalat untuk kota dan tanggal tertentu
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
    $cacheKey = config("prayer.cache_prefix.city") . ":{$roundedLat}:{$roundedLon}";
    Cache::forget($cacheKey);
  }
}