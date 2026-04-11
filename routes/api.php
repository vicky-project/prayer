<?php

use Illuminate\Support\Facades\Route;
use Modules\Prayer\Http\Controllers\PrayerController;

Route::middleware('auth:sanctum')
->prefix("prayer")
->name("prayer.")
->group(function() {
  Route::post("times", [PrayerController::class, "getTimes"])->name("times");
  Route::get("settings", [PrayerController::class, "settings"])->name("settings");
  Route::post("settings", [PrayerController::class, "update"])->middleware("telegram.miniapp")->name("update");
});