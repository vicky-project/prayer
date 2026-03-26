<?php
namespace Modules\Prayer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Prayer\Models\City;
use Modules\Prayer\Models\Prayer;
use Modules\Prayer\Services\PrayerTimeService;
use Carbon\Carbon;

class FetchPrayerData extends Command
{
  protected $signature = 'app:prayer';
  protected $description = 'Fetch prayer times data from JSON and update database';

  protected PrayerTimeService $prayerService;

  public function __construct(PrayerTimeService $prayerService) {
    parent::__construct();
    $this->prayerService = $prayerService;
  }

  public function handle() {
    $url = config("prayer.base_api_url");
    if (!$url) {
      $this->error("Api URL not found. Please provide Api URL in .env (PRAYER_BASEAPI_URL)");
      return 1;
    }

    Log::info("Command FetchPrayerData started.", [
      "url" => $url
    ]);

    $this->info('Starting prayer data fetch using url: '.$url);

    $totalProvinces = 0;

    try {
      // Download data dengan timeout panjang
      $response = Http::timeout(3600)->get($url);

      if (!$response->successful()) {
        $this->error('Failed to fetch data. HTTP status: ' . $response->status());
        return 1;
      }

      $data = $response->json();

      if (!isset($data['provinces'])) {
        $this->error('Invalid data format: missing provinces key');
        return 1;
      }

      $totalProvinces = count($data['provinces']);
      $this->info("Found {$totalProvinces} provinces. Processing...");

      $bar = $this->output->createProgressBar($totalProvinces);
      $bar->start();

      foreach ($data['provinces'] as $province) {
        $provinceId = $province['id'];
        $provinceName = $province['name'];

        foreach ($province['cities'] as $cityData) {
          // Simpan atau update city
          $city = City::updateOrCreate(
            ['city_id' => $cityData['id']],
            [
              'name' => $cityData['name'],
              'slug' => $cityData['slug'] ?? null,
              'province_id' => $provinceId,
              'province_name' => $provinceName,
              'latitude' => $cityData['coordinate']['latitude'] ?? null,
              'longitude' => $cityData['coordinate']['longitude'] ?? null,
            ]
          );

          // Jika latitude/longitude ada dan timezone belum diisi, ambil timezone
          if ($city->latitude && $city->longitude && empty($city->timezone)) {
            $timezone = $this->prayerService->getTimezoneFromCoordinates($city->latitude, $city->longitude);
            $city->timezone = $timezone;
            $city->save();
          }

          // Hapus semua prayer kota ini untuk mengisi ulang (data bisa berubah)
          Prayer::where('city_id', $city->id)->delete();

          // Insert prayers
          $prayers = [];
          foreach ($cityData['prayers'] as $prayerData) {
            $prayers[] = [
              'prayer_id' => $prayerData['id'],
              'city_id' => $city->id,
              'date' => Carbon::parse($prayerData['date'])->format('Y-m-d'),
              'imsak' => $prayerData['time']['imsak'] ?? null,
              'subuh' => $prayerData['time']['subuh'] ?? null,
              'terbit' => $prayerData['time']['terbit'] ?? null,
              'dhuha' => $prayerData['time']['dhuha'] ?? null,
              'dzuhur' => $prayerData['time']['dzuhur'] ?? null,
              'ashar' => $prayerData['time']['ashar'] ?? null,
              'maghrib' => $prayerData['time']['maghrib'] ?? null,
              'isya' => $prayerData['time']['isya'] ?? null,
              'created_at' => now(),
              'updated_at' => now(),
            ];
          }

          // Insert batch untuk efisiensi
          if (!empty($prayers)) {
            Prayer::insert($prayers);
          }
        }
        $bar->advance();
      }

      $bar->finish();
      $this->newLine();

    } catch (\Exception $e) {
      Log::error('Fetch prayer data error: ' . $e->getMessage());
      $this->error('Error: ' . $e->getMessage());
      return 1;
    }

    $this->info('Prayer data updated successfully!');
    Log::info("Command FetchPrayerData finished.", [
      "provinces" => $totalProvinces
    ]);
    return 0;
  }
}