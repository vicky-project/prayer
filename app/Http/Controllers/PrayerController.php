<?php

namespace Modules\Prayer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Prayer\Http\Requests\LocationRequest;
use Modules\Telegram\Services\TelegramService;
use Modules\Telegram\Models\TelegramUser;

class PrayerController extends Controller
{
  public function __construct(protected PrayerTimeService $prayerService) {}

  /**
  * Display a listing of the resource.
  */
  public function index(Request $request) {
    return view('prayer::index');
  }

  /**
  * Get prayer times.
  */
  public function getTimes(LocationRequest $request) {
    try {
      $telegramUser = $request->user('sanctum');
      $times = $this->prayerService->getPrayerTimes(
        $request->input('latitude'),
        $request->input('longitude'),
        $request->input('city'),
        $telegramUser
      );

      return response()->json(["success" => true, "data" => $times, "message" => "Jadwal shalat berhasil diambil"]);
    } catch(\Exception $e) {
      \Log::error("Failed to fetch prayer api", [
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
      ]);

      return response()->json(["success" => false, "message" => $e->getMessage()], 500);
    }
  }

  /**
  * Store a newly created resource in storage.
  */
  public function settings(Request $request) {
    $telegramUser = $request->user();

    return response()->json(['success' => true, 'data' => $telegramUser->data ?? []]);
  }

  /**
  * Update the specified resource in storage.
  */
  public function update(Request $request) {
    $telegramUser = $request->user("sanctum");
    if (!$telegramUser) {
      return response()->json(["success" => false, "message" => "Telegram user tidak ditemukan"], 404);
    }

    $validator = Validator::make($request->all(), [
      "city" => "nullable|string|max:255",
      "latitude" => "nullable|numeric|between:-90,90",
      "longitude" => "nullable|numeric|between:-180,180",
      "notifications_enabled" => "boolean"
    ]);

    if ($validator->fails()) {
      return response()->json([
        "success" => false,
        "errors" => $validator->errors()
      ], 422);
    }

    try {
      $data = $telegramUser->data ?? [];
      $defaultLocation = $data['default_location'] ?? [];

      // Cek apakah semua field lokasi kosong
      $city = $request->input('city');
      $lat = $request->input('latitude');
      $lon = $request->input('longitude');

      if (empty($city) && empty($lat) && empty($lon)) {
        // Hapus default location
        $defaultLocation = [];
      } elseif (!empty($city)) {
        $defaultLocation = ['city' => $city];
      } elseif (!empty($lat) && !empty($lon)) {
        $defaultLocation = [
          'latitude' => (float) $lat,
          'longitude' => (float) $lon
        ];
      }
      // Jika hanya salah satu yang diisi (misal lat kosong, lon terisi) maka tidak diubah

      $data['default_location'] = $defaultLocation;
      $data['notifications_prayer_enabled'] = $request->boolean('notifications_enabled');

      $telegramUser->data = $data;
      $telegramUser->save();

      // Hapus cache settings di frontend (via response, tapi frontend juga akan hapus sendiri)
      return response()->json([
        'success' => true,
        'message' => 'Pengaturan berhasil disimpan.',
        'data' => $data // kembalikan data terbaru
      ]);
    } catch(\Exception $e) {
      \Log::error("Gagal menyimpan pengaturan", ['error' => $e->getMessage()]);
      return response()->json(["success" => false, "message" => $e->getMessage()], 500);
    }
  }
}