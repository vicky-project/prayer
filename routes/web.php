<?php

use Illuminate\Support\Facades\Route;
use Modules\Prayer\Http\Controllers\PrayerController;

Route::prefix("apps")
->name("apps.")->middleware(["web"])
->group(function () {
  Route::get("prayer", [PrayerController::class, "index"])->name("prayer");
  Route::get("prayer/settings", [PrayerController::class, "settings"])->name("prayer.settings");
});