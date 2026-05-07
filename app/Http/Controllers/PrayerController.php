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
    $telegramUser = $request->user('sanctum');

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
        "errors" => $validator->errors() // perbaiki typo "erros" menjadi "errors"
      ], 422);
    }

    try {
      $data = $telegramUser->data ?? [];
      // Ambil default_location yang sudah ada, jangan langsung kosong
      $defaultLocation = $data['default_location'] ?? [];

      if ($request->filled('city')) {
        $defaultLocation = ['city' => $request->city];
      } elseif ($request->filled('latitude') && $request->filled('longitude')) {
        $defaultLocation = [
          'latitude' => (float) $request->latitude,
          'longitude' => (float) $request->longitude
        ];
      }
      // Jika tidak ada keduanya, biarkan $defaultLocation tetap seperti sebelumnya

      $data['default_location'] = $defaultLocation;
      $data['notifications_prayer_enabled'] = $request->boolean('notifications_enabled');

      $telegramUser->data = $data;
      $telegramUser->save();

      return response()->json([
        'success' => true,
        'message' => 'Pengaturan berhasil disimpan.'
      ]);
    } catch(\Exception $e) {
      \Log::error("Gagal menyimpan pengaturan", ['error' => $e->getMessage()]);
      return response()->json(["success" => false, "message" => $e->getMessage()], 500);
    }
  }
}