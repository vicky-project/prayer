<?php

namespace Modules\Prayer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Suport\Facades\Http;

class PrayerController extends Controller
{
  /**
  * Display a listing of the resource.
  */
  public function index() {
    return view('prayer::index');
  }

  /**
  * Get prayer times.
  */
  public function getTimes(Request $request) {
    $request->validate([
      "latitude" => "required|numeric",
      "longitude" => "required|numeric"
    ]);

    $lat = $request->latitude;
    $lon = $request->longitude;

    try {
      $res = Http::get(config("prayer.base_api_url") . "/prayer", [
        "latitude" => $lat,
        "longitude" => $lon
      ]);

      if (!$res->successful()) {
        return response()->json(["success" => false, "error" => "Gagal mengambil data shalat"]);
      }

      $data = $res->json();

      return response()->json(["success" => true, "data" => $data]);
    } catch(\Exception $e) {
      \Log::error("Failed to fetch prayer api", [
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
      ]);

      return response()->json(["success" => false, "error" => $e->getMessage()]);
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