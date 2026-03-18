<?php

use Illuminate\Support\Facades\Route;
use Modules\Prayer\Http\Controllers\PrayerController;

Route::prefix("prayer")->name("prayer.")->group(function() {
  Route::post("times", [PrayerController::class, "getTimes"])->name("times");
  Route::post("settings", [PrayerController::class, "update"])->name("update");
});