<?php

namespace Modules\Prayer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Prayer\Services\PrayerTimeService;
use Modules\Prayer\Http\Requests\LocationRequest;
use Modules\Telegram\Services\TelegramService;

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
    $request->validate([
      "city" => "nullable|string|max:255",
      "latitude" => "nullable|numeric|between:-90,90",
      "longitude" => "nullable|between:-180,180",
      "notifications_enabled" => "boolean"
    ]);
  }

  /**
  * Remove the specified resource from storage.
  */
  public function destroy($id) {}
}