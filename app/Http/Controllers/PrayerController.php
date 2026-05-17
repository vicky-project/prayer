<?php

namespace Modules\Prayer\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Prayer\Http\Requests\LocationRequest;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Prayer\Models\City;
use Modules\Telegram\Models\TelegramUser;

class PrayerController extends Controller
{
  public function __construct(protected PrayerTimeService $prayerService) {}

  public function index(Request $request) {
    return view('prayer::index');
  }

  public function getTimes(LocationRequest $request) {
    try {
      $telegramUser = $request->user();
      $ignoreDefault = $request->boolean('ignore_default', false);
      $times = $this->prayerService->getPrayerTimes(
        $request->input('latitude'),
        $request->input('longitude'),
        $request->input('city'),
        $telegramUser,
        $ignoreDefault
      );

      return response()->json(["success" => true, "data" => $times, "message" => "Jadwal shalat berhasil diambil"]);
    } catch (\Exception $e) {
      \Log::error("Failed to fetch prayer api", [
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
      ]);

      return response()->json(["success" => false, "message" => $e->getMessage()], 500);
    }
  }

  public function getRange(Request $request) {
    $telegramUser = $request->user('sanctum');
    $city = $request->input('city');
    $lat = $request->input('latitude');
    $lon = $request->input('longitude');

    // Cari cityModel
    $cityModel = null;
    if ($city) {
      $cityModel = $this->prayerService->findCityByName($city);
    } elseif ($lat && $lon) {
      $cityModel = $this->prayerService->findNearestCity($lat, $lon);
    } elseif ($telegramUser) {
      $prayerSettings = $telegramUser->data['prayer'] ?? [];
      $default = $prayerSettings['default_location'] ?? null;
      if ($default && isset($default['city'])) {
        $cityModel = $this->prayerService->findCityByName($default['city']);
      } elseif ($default && isset($default['latitude'], $default['longitude'])) {
        $cityModel = $this->prayerService->findNearestCity($default['latitude'], $default['longitude']);
      }
    }

    if (!$cityModel) {
      return response()->json(['success' => false, 'message' => 'Kota tidak ditemukan'], 404);
    }

    // Cek apakah ada start_date dan end_date
    $startDateInput = $request->input('start_date');
    $endDateInput = $request->input('end_date');

    if ($startDateInput && $endDateInput) {
      try {
        $startDate = Carbon::createFromFormat('d-m-Y', $startDateInput)->toDateString();
        $endDate = Carbon::createFromFormat('d-m-Y', $endDateInput)->toDateString();
      } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Format tanggal tidak valid, gunakan dd-mm-yyyy'], 400);
      }
    } else {
      $days = (int) $request->input('days', 7);
      $days = min(max($days, 1), 30);
      $startDate = Carbon::today()->toDateString();
      $endDate = Carbon::today()->addDays($days - 1)->toDateString();
    }

    try {
      $data = $this->prayerService->getPrayerTimesRange($cityModel->id, $startDate, $endDate);
      return response()->json(['success' => true, 'data' => $data]);
    } catch (\Exception $e) {
      \Log::error("Failed to get prayer times range", [
        'message' => $e->getMessage(),
        'city' => $city,
        'latitude' => $lat,
        'longitude' => $lon,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'trace' => $e->getTrace()
      ]);
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  public function settings(Request $request) {
    $telegramUser = $request->user();
    abort_if(!$telegramUser, 401, 'Unauthenticated');

    return response()->json(['success' => true, 'data' => $telegramUser->data['prayer'] ?? []]);
  }

  public function update(Request $request) {
    $telegramUser = $request->user();
    if (!$telegramUser) {
      return response()->json(["success" => false, "message" => "Telegram user tidak ditemukan"], 404);
    }

    $validator = Validator::make($request->all(), [
      "city" => "nullable|string|max:255",
      "latitude" => "nullable|numeric|between:-90,90",
      "longitude" => "nullable|numeric|between:-180,180",
      "notifications_enabled" => "boolean",
      "reminder_minutes" => "nullable|integer|min:0|max:60"
    ]);

    if ($validator->fails()) {
      return response()->json([
        "success" => false,
        "errors" => $validator->errors()
      ], 422);
    }

    try {
      $data = $telegramUser->data ?? [];
      $prayer = $data['prayer'] ?? [];
      $defaultLocation = [];

      $city = $request->input('city');
      $lat = $request->input('latitude');
      $lon = $request->input('longitude');

      if (!empty($city)) {
        $defaultLocation = ['city' => $city];
      } elseif (!empty($lat) && !empty($lon)) {
        $defaultLocation = [
          'latitude' => (float) $lat,
          'longitude' => (float) $lon
        ];
      }

      $prayer['default_location'] = $defaultLocation;
      $prayer['notifications_enabled'] = $request->boolean('notifications_enabled');
      $prayer['reminder_minutes'] = (int) $request->input('reminder_minutes', 0);

      $data['prayer'] = $prayer;
      $telegramUser->data = $data;
      $telegramUser->save();

      return response()->json([
        'success' => true,
        'message' => 'Pengaturan berhasil disimpan.',
        'data' => $data
      ]);
    } catch (\Exception $e) {
      \Log::error("Gagal menyimpan pengaturan", ['error' => $e->getMessage()]);
      return response()->json(["success" => false, "message" => $e->getMessage()], 500);
    }
  }

  public function searchCities(Request $request) {
    $query = $request->input('q');
    if (strlen($query) < 2) {
      return response()->json(['success' => true, 'data' => []]);
    }

    $cities = City::where('name', 'LIKE', $query . '%')
    ->orWhere('name', 'LIKE', '%' . $query . '%')
    ->limit(10)
    ->get(['name', 'province_name']);

    return response()->json(['success' => true, 'data' => $cities]);
  }
}