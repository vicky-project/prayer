<?php

namespace Modules\Prayer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
      "lat" => "required|numeric",
      "lon" => "required|numeric"
    ]);

    $lat = $request->lat;
    $lon = $request->lon;

    try {
      if (!config("prayer.base_api_url")) {
        throw new \Exception("Please provide api url in env PRAYER_BASEAPI_URL");
      }

      $res = Http::get(config("prayer.base_api_url"));

      if (!$res->successful()) {
        return response()->json(["success" => false, "message" => $res->object()->error]);
      }

      $data = $res->collect("provinces");
      \Log::debug("Data prayer", $data);

      return response()->json(["success" => true, "data" => $data]);
    } catch(\Exception $e) {
      \Log::error("Failed to fetch prayer api", [
        "message" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
      ]);

      return response()->json(["success" => false, "message" => $e->getMessage()]);
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