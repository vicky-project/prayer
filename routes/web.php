<?php

use Illuminate\Support\Facades\Route;
use Modules\Prayer\Http\Controllers\PrayerController;

Route::prefix("apps")
->name("apps.")->middleware(["web", "telegram.miniapp"])
->group(function () {
  Route::get("prayer", [PrayerController::class, "index"])->name("prayer");
});