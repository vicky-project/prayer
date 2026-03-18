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
  public function index(Request $request, TelegramService $tgService) {
    $initData = $request->get('initData');
    $user = null;
    if ($initData) {
      parse_str($initData, $data);
      $userData = json_decode($data["user"] ?? '{}', true);
      $tgId = $userData["id"];

      $user = $tgService->getUserByTelegramId($tgId);
    }

    $view = view('prayer::index', compact('user'));

    return $user ? $view : $view->with('warning', 'Connect to akun for many feature');
  }

  /**
  * Get prayer times.
  */
  public function getTimes(LocationRequest $request) {
    try {
      $times = $this->prayerService->getPrayerTimes($request->lat, $request->lot, $request->city);

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
  public function store(Request $request) {}

  /**
  * Show the specified resource.
  */
  public function show($id) {
    return view('prayer::show');
  }

  /**
  * Show the form for editing the specified resource.
  */
  public function edit($id) {
    return view('prayer::edit');
  }

  /**
  * Update the specified resource in storage.
  */
  public function update(Request $request, $id) {}

  /**
  * Remove the specified resource from storage.
  */
  public function destroy($id) {}
}