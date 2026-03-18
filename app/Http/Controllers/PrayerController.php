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
    $telegramUser = $request->get("telegram_user");

    return view('prayer::index', compact("telegramUser"));
  }

  /**
  * Get prayer times.
  */
  public function getTimes(LocationRequest $request) {
    try {
      $telegramUser = $request->get('telegram_user');
      $times = $this->prayerService->getPrayerTimes(
        $request->lat,
        $request->lot,
        $request->city,
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
    $telegramUser = $request->get("telegram_user");
    return view("prayer::settings", compact("telegramUser"));
  }

  /**
  * Update the specified resource in storage.
  */
  public function update(Request $request) {
    $telegramUser = $request->get("telegram_user");
    if (!$telegramUser) {
      return response()->json(["success" => false, "message" => "Telegram user tidak ditemukan"], 404);
    }

    $validator = Validator::make($request->all(), [
      "city" => "nullable|string|max:255",
      "latitude" => "nullable|numeric|between:-90,90",
      "longitude" => "nullable|between:-180,180",
      "notifications_enabled" => "boolean"
    ]);

    if ($validator->fails()) {
      return response()->json([
        "success" => false,
        "erros" => $validator->errors()
      ], 422);
    }

    try {
      $telegram = TelegramUser::find($telegramUser["id"]);
      if (!$telegram) {
        return response()->json(["success" => false, "message" => "Telegram ID not found."], 500);
      }

      $data = $telegram->data ?? [];
      $defaultLocation = [];

      if ($request->filled('city')) {
        $defaultLocation['city'] = $request->city;
      } elseif ($request->filled('latitude') && $request->filled('longitude')) {
        $defaultLocation['latitude'] = (float) $request->latitude;
        $defaultLocation['longitude'] = (float) $request->longitude;
      }

      $data['default_location'] = $defaultLocation;
      $data['notifications_enabled'] = $request->boolean('notifications_enabled');

      $telegram->data = $data;
      $telegram->save();

      return response()->json([
        'success' => true,
        'message' => 'Pengaturan berhasil disimpan.'
      ]);
    } catch(\Exception $e) {
      return response()->json(["success" => false, "message" => $e->getMessage()], 500);
    }
  }
}