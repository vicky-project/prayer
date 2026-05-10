<?php

use Illuminate\Support\Facades\Route;
use Modules\Prayer\Http\Controllers\PrayerController;

Route::middleware('auth:sanctum')
->prefix("prayer")
->name("prayer.")
->group(function() {
  Route::get("settings", [PrayerController::class, "settings"])->name("settings");
  Route::get('cities/search', [PrayerController::class, 'searchCities'])->name('cities.search');
  Route::post("times", [PrayerController::class, "getTimes"])->name("times");
  Route::post('times/range', [PrayerController::class, 'getRange']);
  Route::post("settings", [PrayerController::class, "update"]);
});